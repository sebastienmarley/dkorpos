<?php

namespace App\Livewire\Stores;

use App\Enums\StoreType;
use App\Models\Store;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Show extends Component
{
    public Store $store;

    public string $activeTab = 'identification';

    // Identification
    public string $name = '';

    public string $type = 'physical';

    /** @var array{civic: string, apartment: string, street: string, city: string, province: string, country: string, postal_code: string} */
    public array $address = [
        'civic' => '', 'apartment' => '', 'street' => '',
        'city' => '', 'province' => '', 'country' => 'CA', 'postal_code' => '',
    ];

    public string $phone = '';

    public string $email = '';

    // Comptabilité
    public string $gstNumber = '';

    public string $qstNumber = '';

    public string $bankAccount = '';

    public string $vacationAccrualMonth = '';

    public string $vacationAccrualDay = '';

    public string $sickAccrualMonth = '';

    public string $sickAccrualDay = '';

    public string $sickDaysFullTime = '';

    public string $sickDaysPartTime = '';

    // Heures d'ouverture

    /** @var array<string, array{open: bool, from: string, to: string}> */
    public array $openingHours = [];

    // Paramètres
    public string $warehouseStoreId = '';

    public string $shippingWarehouseId = '';

    public bool $isActive = true;

    public function mount(Store $store): void
    {
        $this->store = $store;
        $this->fillFromModel();
    }

    public function updatedAddressCountry(): void
    {
        $this->address['province'] = '';
    }

    public function saveIdentification(): void
    {
        $this->authorize('stores.edit');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(StoreType::class)],
            'address.civic' => ['nullable', 'string', 'max:20'],
            'address.apartment' => ['nullable', 'string', 'max:20'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.province' => ['nullable', 'string', 'size:2'],
            'address.country' => ['nullable', 'string', 'in:CA,US'],
            'address.postal_code' => ['nullable', 'string', 'max:6'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{3}\)\d{3}-\d{4}$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $this->store->fill([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'address_civic' => filled($this->address['civic']) ? $this->address['civic'] : null,
            'address_apartment' => filled($this->address['apartment']) ? $this->address['apartment'] : null,
            'address_street' => filled($this->address['street']) ? $this->address['street'] : null,
            'address_city' => filled($this->address['city']) ? $this->address['city'] : null,
            'address_province' => filled($this->address['province']) ? $this->address['province'] : null,
            'address_country' => filled($this->address['country']) ? $this->address['country'] : null,
            'address_postal_code' => filled($this->address['postal_code']) ? $this->address['postal_code'] : null,
            'phone' => filled($this->phone) ? $this->phone : null,
            'email' => filled($this->email) ? $this->email : null,
        ])->save();

        Flux::toast(text: __('Identification sauvegardée.'), variant: 'success');
    }

    public function saveAccounting(): void
    {
        $this->authorize('stores.edit');

        $this->validate([
            'gstNumber' => ['nullable', 'string', 'max:255'],
            'qstNumber' => ['nullable', 'string', 'max:255'],
            'bankAccount' => ['nullable', 'string', 'max:255'],
            'vacationAccrualMonth' => ['nullable', 'integer', 'between:1,12', 'required_with:vacationAccrualDay'],
            'vacationAccrualDay' => ['nullable', 'integer', 'between:1,31', 'required_with:vacationAccrualMonth', $this->validDay($this->vacationAccrualMonth)],
            'sickAccrualMonth' => ['nullable', 'integer', 'between:1,12', 'required_with:sickAccrualDay'],
            'sickAccrualDay' => ['nullable', 'integer', 'between:1,31', 'required_with:sickAccrualMonth', $this->validDay($this->sickAccrualMonth)],
            'sickDaysFullTime' => ['nullable', 'integer', 'min:0', 'max:365'],
            'sickDaysPartTime' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $this->store->fill([
            'gst_number' => filled($this->gstNumber) ? $this->gstNumber : null,
            'qst_number' => filled($this->qstNumber) ? $this->qstNumber : null,
            'bank_account' => filled($this->bankAccount) ? $this->bankAccount : null,
            'vacation_accrual_start' => $this->monthDay($this->vacationAccrualMonth, $this->vacationAccrualDay),
            'sick_accrual_start' => $this->monthDay($this->sickAccrualMonth, $this->sickAccrualDay),
            'sick_days_full_time' => filled($this->sickDaysFullTime) ? $this->sickDaysFullTime : null,
            'sick_days_part_time' => filled($this->sickDaysPartTime) ? $this->sickDaysPartTime : null,
        ])->save();

        Flux::toast(text: __('Comptabilité sauvegardée.'), variant: 'success');
    }

    public function saveOpeningHours(): void
    {
        $this->authorize('stores.edit');

        $rules = [];
        foreach (Store::DAYS as $day) {
            $rules["openingHours.$day.open"] = ['boolean'];
            $rules["openingHours.$day.from"] = [Rule::requiredIf($this->openingHours[$day]['open'] ?? false), 'nullable', 'date_format:H:i'];
            $rules["openingHours.$day.to"] = [
                Rule::requiredIf($this->openingHours[$day]['open'] ?? false),
                'nullable', 'date_format:H:i',
                ($this->openingHours[$day]['open'] ?? false) ? 'after:openingHours.'.$day.'.from' : 'nullable',
            ];
        }

        $this->validate($rules);

        $this->store->fill(['opening_hours' => $this->openingHours])->save();

        Flux::toast(text: __('Heures d\'ouverture sauvegardées.'), variant: 'success');
    }

    public function saveParameters(): void
    {
        $this->authorize('stores.edit');

        $this->validate([
            'warehouseStoreId' => ['nullable', 'integer', Rule::exists('stores', 'id')->where('type', StoreType::Physical->value)],
            'shippingWarehouseId' => ['nullable', 'integer', Rule::exists('stores', 'id')->where('type', StoreType::Physical->value)],
            'isActive' => ['boolean'],
        ]);

        $this->store->fill([
            'warehouse_store_id' => filled($this->warehouseStoreId) ? $this->warehouseStoreId : null,
            'shipping_warehouse_id' => filled($this->shippingWarehouseId) ? $this->shippingWarehouseId : null,
            'is_active' => $this->isActive,
        ])->save();

        Flux::toast(text: __('Paramètres sauvegardés.'), variant: 'success');
    }

    /** @return array<int, StoreType> */
    public function getStoreTypes(): array
    {
        return StoreType::cases();
    }

    /**
     * Le jour doit exister dans le mois choisi (le 29 février n'est pas accepté : l'année est ignorée).
     */
    private function validDay(string $month): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($month): void {
            if (filled($month) && filled($value) && ! checkdate((int) $month, (int) $value, 2001)) {
                $fail(__('Ce jour n\'existe pas dans le mois choisi.'));
            }
        };
    }

    private function monthDay(string $month, string $day): ?string
    {
        return filled($month) && filled($day) ? sprintf('%02d-%02d', $month, $day) : null;
    }

    /** @return array{0: string, 1: string} */
    private function splitMonthDay(?string $value): array
    {
        return $value === null ? ['', ''] : [(string) (int) substr($value, 0, 2), (string) (int) substr($value, 3, 2)];
    }

    private function fillFromModel(): void
    {
        $this->name = $this->store->name;
        $this->type = $this->store->type->value;
        $this->address = [
            'civic' => $this->store->address_civic ?? '',
            'apartment' => $this->store->address_apartment ?? '',
            'street' => $this->store->address_street ?? '',
            'city' => $this->store->address_city ?? '',
            'province' => $this->store->address_province ?? '',
            'country' => $this->store->address_country ?? 'CA',
            'postal_code' => $this->store->address_postal_code ?? '',
        ];
        $this->phone = $this->store->phone ?? '';
        $this->email = $this->store->email ?? '';
        $this->gstNumber = $this->store->gst_number ?? '';
        $this->qstNumber = $this->store->qst_number ?? '';
        $this->bankAccount = $this->store->bank_account ?? '';
        [$this->vacationAccrualMonth, $this->vacationAccrualDay] = $this->splitMonthDay($this->store->vacation_accrual_start);
        [$this->sickAccrualMonth, $this->sickAccrualDay] = $this->splitMonthDay($this->store->sick_accrual_start);
        $this->sickDaysFullTime = (string) ($this->store->sick_days_full_time ?? '');
        $this->sickDaysPartTime = (string) ($this->store->sick_days_part_time ?? '');
        $this->openingHours = array_replace_recursive(Store::defaultOpeningHours(), $this->store->opening_hours ?? []);
        $this->warehouseStoreId = (string) ($this->store->warehouse_store_id ?? '');
        $this->shippingWarehouseId = (string) ($this->store->shipping_warehouse_id ?? '');
        $this->isActive = $this->store->is_active;
    }

    public function render(): View
    {
        return view('livewire.stores.show', [
            'months' => collect(range(1, 12))->mapWithKeys(fn (int $month) => [
                $month => Carbon::create(2001, $month, 1)->locale('fr')->isoFormat('MMMM'),
            ])->all(),
            'physicalStores' => Store::query()
                ->where('type', StoreType::Physical)
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $this->store->warehouse_store_id)->orWhere('id', $this->store->shipping_warehouse_id))
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', ['title' => $this->store->name]);
    }
}
