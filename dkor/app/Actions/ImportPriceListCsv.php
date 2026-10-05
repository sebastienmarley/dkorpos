<?php

namespace App\Actions;

use App\Models\PriceListItem;
use App\Models\PriceListList;
use App\Rules\UniqueCleanProductModel;
use InvalidArgumentException;

/**
 * Importe un CSV (séparateur « ; ») dans une liste de prix, par lots de 200 lignes.
 *
 * Les lignes sans modèle ou dont le coût est vide, invalide ou égal à 0 sont ignorées, de même qu'un modèle déjà présent
 * dans une autre liste de la même liste de prix (ou répété dans le fichier) : il est unique par liste de prix.
 * Un UPC qui n'est pas composé uniquement de chiffres est vidé. Les autres lignes sont toutes conservées.
 * Les produits sont mis à jour tout de suite si la liste de prix a déjà débuté, sinon à sa date de début (voir ApplyPriceLists).
 */
class ImportPriceListCsv
{
    public const BATCH_SIZE = 200;

    /** @var list<string> */
    private const REQUIRED_HEADERS = ['modele', 'cout'];

    public function __construct(private readonly ApplyPriceListItems $apply) {}

    /**
     * @return array{rows: int, matched: int, duplicates: int, pending: bool}
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
            $list->update(['applied_at' => null]);

            $taken = PriceListItem::query()
                ->whereHas('priceListList', fn ($query) => $query
                    ->where('price_list_id', $list->price_list_id)
                    ->whereKeyNot($list->id))
                ->pluck('clean_model')
                ->flip()
                ->all();

            $totals = ['rows' => 0, 'matched' => 0, 'duplicates' => 0, 'pending' => false];
            $batch = [];

            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                $record = $this->mapRow($columns, $row);

                if ($record === null) {
                    continue;
                }

                if (isset($taken[$record['clean_model']])) {
                    $totals['duplicates']++;

                    continue;
                }

                $taken[$record['clean_model']] = true;
                $batch[] = $record;

                if (count($batch) === self::BATCH_SIZE) {
                    $this->flush($list, $batch, $totals);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $this->flush($list, $batch, $totals);
            }

            if ($list->priceList->starts_on->isFuture()) {
                $totals['pending'] = true;
            } else {
                $totals['matched'] = $this->apply->handle($list);
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
     * @param  array{rows: int, matched: int, duplicates: int, pending: bool}  $totals
     */
    private function flush(PriceListList $list, array $batch, array &$totals): void
    {
        $now = now();

        PriceListItem::query()->insert(array_map(fn (array $record): array => $record + [
            'price_list_list_id' => $list->id,
            'created_at' => $now,
            'updated_at' => $now,
        ], $batch));

        $totals['rows'] += count($batch);
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
