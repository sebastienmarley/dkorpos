<?php

namespace App\Actions;

use App\Models\PriceListItem;
use App\Models\PriceListList;
use App\Models\Product;
use App\Models\ProductUpc;
use App\Rules\UniqueCleanProductModel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Importe un CSV (séparateur « ; ») dans une liste de prix, par lots de 200 lignes.
 *
 * Les lignes sans modèle ou dont le coût est vide, invalide ou égal à 0 sont ignorées. Les autres sont toutes conservées. Quand le modèle nettoyé existe déjà chez le fournisseur, le produit
 * est mis à jour (coût avec l'escompte de la liste, IMAP, collection, description, dimensions) et son UPC est ajouté à sa liste d'UPC sans jamais en retirer (un UPC qui n'est pas composé uniquement de chiffres est vidé) ; sinon la ligne est seulement conservée.
 * Les cellules vides ne remplacent jamais une valeur existante du produit.
 */
class ImportPriceListCsv
{
    public const BATCH_SIZE = 200;

    /** @var list<string> */
    private const REQUIRED_HEADERS = ['modele', 'cout'];

    /**
     * @return array{rows: int, matched: int}
     */
    public function handle(PriceListList $list, string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException(__('Fichier illisible.'));
        }

        try {
            $columns = $this->readHeader($handle);
            $list->items()->delete();

            $totals = ['rows' => 0, 'matched' => 0];
            $batch = [];

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $record = $this->mapRow($columns, $row);

                if ($record === null) {
                    continue;
                }

                $batch[] = $record;

                if (count($batch) === self::BATCH_SIZE) {
                    $this->flush($list, $batch, $totals);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->flush($list, $batch, $totals);
            }

            return $totals;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @return array<string, int> position de chaque colonne reconnue
     */
    private function readHeader($handle): array
    {
        $header = fgetcsv($handle, 0, ';');

        if ($header === false) {
            throw new InvalidArgumentException(__('Le fichier est vide.'));
        }

        $columns = [];

        foreach ($header as $index => $label) {
            $columns[$this->normalizeHeader((string) $label)] ??= $index;
        }

        foreach (self::REQUIRED_HEADERS as $required) {
            if (! isset($columns[$required])) {
                throw new InvalidArgumentException(__('Colonne manquante : :column.', ['column' => $required]));
            }
        }

        return $columns;
    }

    /**
     * @param  array<string, int>  $columns
     * @param  list<string|null>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $columns, array $row): ?array
    {
        $text = fn (string $key): ?string => isset($columns[$key]) ? $this->text($row[$columns[$key]] ?? null) : null;
        $number = fn (string $key): ?float => isset($columns[$key]) ? $this->number($row[$columns[$key]] ?? null) : null;

        $model = $text('modele');

        $cost = $number('cout');
        $upc = $text('upc');

        if ($model === null || $cost === null || $cost <= 0) {
            return null;
        }

        return [
            'model' => $model,
            'clean_model' => UniqueCleanProductModel::clean($model),
            'cost' => $cost,
            'imap' => $number('imap'),
            'upc' => $upc !== null && ctype_digit($upc) ? $upc : null,
            'collection' => $text('collection'),
            'description' => $text('description'),
            'length' => $number('longueur'),
            'width' => $number('largeur'),
            'height' => $number('hauteur'),
            'weight' => $number('poids'),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     * @param  array{rows: int, matched: int}  $totals
     */
    private function flush(PriceListList $list, array $batch, array &$totals): void
    {
        DB::transaction(function () use ($list, $batch, &$totals): void {
            $products = Product::query()
                ->where('supplier_id', $list->priceList->supplier_id)
                ->whereIn('clean_model', array_unique(array_column($batch, 'clean_model')))
                ->get()
                ->keyBy('clean_model');

            $now = now();
            $rows = [];
            $upcs = [];

            foreach ($batch as $record) {
                $product = $products->get($record['clean_model']);

                $rows[] = $record + [
                    'price_list_list_id' => $list->id,
                    'product_id' => $product?->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if ($product !== null) {
                    $this->updateProduct($product, $record, $list->discount_percent);
                    $totals['matched']++;

                    if ($record['upc'] !== null) {
                        $upcs[] = ['product_id' => $product->id, 'upc' => $record['upc'], 'created_at' => $now, 'updated_at' => $now];
                    }
                }
            }

            PriceListItem::query()->insert($rows);
            ProductUpc::query()->insertOrIgnore($upcs);
            $totals['rows'] += count($rows);
        });
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function updateProduct(Product $product, array $record, float $discountPercent): void
    {
        $changes = array_filter(
            array_intersect_key($record, array_flip(['imap', 'collection', 'description', 'length', 'width', 'height', 'weight'])),
            fn (mixed $value): bool => $value !== null,
        );

        $changes['cost'] = max(0.01, round($record['cost'] * (1 - $discountPercent / 100), 2));

        $product->update($changes);
    }

    private function normalizeHeader(string $label): string
    {
        $label = preg_replace('/^\xEF\xBB\xBF/', '', $label) ?? $label;
        $label = strtr(mb_strtolower(trim($this->toUtf8($label))), ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'û' => 'u', 'ô' => 'o']);

        return $label;
    }

    private function text(?string $value): ?string
    {
        $value = trim($this->toUtf8((string) $value));

        return $value === '' ? null : $value;
    }

    private function number(?string $value): ?float
    {
        $value = str_replace([' ', "\u{A0}", '$'], '', (string) $value);
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    private function toUtf8(string $value): string
    {
        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
