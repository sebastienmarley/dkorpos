<?php

namespace App\Enums;

use App\Models\LedgerAccount;

/**
 * Motifs d'ajustement d'inventaire (ADR 0011) : le sens permis, les états touchés et le compte de contrepartie du
 * compte Stock dans l'écriture.
 */
enum InventoryAdjustmentReason: string
{
    case Breakage = 'breakage';
    case Theft = 'theft';
    case LostWriteOff = 'lost_write_off';
    case StoreUse = 'store_use';
    case CountCorrection = 'count_correction';
    case OpeningBalance = 'opening_balance';
    case AnnualCountLoss = 'annual_count_loss';

    public function label(): string
    {
        return match ($this) {
            self::Breakage => __('Bris ou produit jeté'),
            self::Theft => __('Vol'),
            self::LostWriteOff => __('Perte définitive'),
            self::StoreUse => __('Usage magasin'),
            self::CountCorrection => __('Correction de comptage'),
            self::OpeningBalance => __('Stock initial'),
            self::AnnualCountLoss => __('Perte à l\'inventaire annuel'),
        };
    }

    public function allowsEntry(): bool
    {
        return in_array($this, [self::CountCorrection, self::OpeningBalance], true);
    }

    public function allowsExit(): bool
    {
        return $this !== self::OpeningBalance;
    }

    /** Les pertes de l'inventaire annuel viennent seulement de la finalisation d'une prise (AnnualInventoryFinalUpdate). */
    public function isManual(): bool
    {
        return $this !== self::AnnualCountLoss;
    }

    /** @return list<InventoryStatus> */
    public function statuses(): array
    {
        return match ($this) {
            self::Breakage => [InventoryStatus::InStock, InventoryStatus::InDemo, InventoryStatus::DefectiveStock, InventoryStatus::Lost],
            self::Theft => [InventoryStatus::InStock, InventoryStatus::InDemo, InventoryStatus::Lost],
            self::LostWriteOff => [InventoryStatus::Lost],
            self::StoreUse, self::OpeningBalance => [InventoryStatus::InStock, InventoryStatus::InDemo],
            self::CountCorrection => [InventoryStatus::InStock, InventoryStatus::InDemo, InventoryStatus::DefectiveStock, InventoryStatus::Lost],
            self::AnnualCountLoss => [InventoryStatus::InStock, InventoryStatus::InDemo, InventoryStatus::DefectiveStock],
        };
    }

    /** Clé système du compte débité à une sortie (crédité à une entrée), en contrepartie du compte Stock. */
    public function accountKey(): string
    {
        return match ($this) {
            self::Breakage, self::Theft, self::LostWriteOff, self::AnnualCountLoss => LedgerAccount::INVENTORY_SHRINKAGE,
            self::StoreUse => LedgerAccount::STORE_SUPPLIES,
            self::CountCorrection => LedgerAccount::INVENTORY_COUNT_VARIANCE,
            self::OpeningBalance => LedgerAccount::OPENING_BALANCE,
        };
    }
}
