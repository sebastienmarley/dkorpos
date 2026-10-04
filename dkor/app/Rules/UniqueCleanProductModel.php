<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueCleanProductModel implements ValidationRule
{
    public function __construct(
        private readonly int|string $supplierId,
        private readonly ?int $ignoreProductId = null,
        private readonly string $column = 'clean_model',
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $clean = self::clean((string) $value);

        $exists = DB::table('products')
            ->where('supplier_id', $this->supplierId)
            ->where($this->column, $clean)
            ->when($this->ignoreProductId, fn ($q) => $q->where('id', '!=', $this->ignoreProductId))
            ->exists();

        if ($exists) {
            $fail(__('Un produit avec un modèle similaire existe déjà chez ce fournisseur.'));
        }
    }

    public static function clean(string $model): string
    {
        return mb_strtolower(preg_replace('/[\s.,\-_\/\\\\|]+/', '', $model) ?? '');
    }
}
