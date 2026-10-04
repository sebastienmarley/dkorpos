<?php

namespace App\Livewire\Accounting;

use App\Actions\BuildPayrollReport;
use App\Actions\LockPayrollPeriod;
use App\Models\PayrollPeriod;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Payroll extends Component
{
    #[Url(as: 'periode')]
    public string $periodStart = '';

    public function mount(): void
    {
        // Par défaut : la dernière période terminée.
        $this->periodStart = filled($this->periodStart)
            ? PayrollPeriod::startFor($this->periodStart)->toDateString()
            : PayrollPeriod::startFor(now())->subDays((int) config('payroll.period_days'))->toDateString();
    }

    public function updatedPeriodStart(string $value): void
    {
        $this->periodStart = PayrollPeriod::startFor($value)->toDateString();
    }

    public function lock(LockPayrollPeriod $locker): void
    {
        $this->authorize('payroll.lock');

        $locker->lock(CarbonImmutable::parse($this->periodStart), Auth::user());

        Flux::toast(text: __('Les horaires de la période sont verrouillés.'), variant: 'success');
    }

    public function unlock(LockPayrollPeriod $locker): void
    {
        $this->authorize('payroll.lock');

        $locker->unlock(CarbonImmutable::parse($this->periodStart), Auth::user());

        Flux::toast(text: __('La période est déverrouillée.'), variant: 'success');
    }

    /**
     * Rapport CSV (séparateur « ; », décimales avec virgule, UTF-8 avec BOM) pour Excel en français.
     */
    public function export(BuildPayrollReport $report): ?StreamedResponse
    {
        $this->authorize('payroll.view');

        $start = CarbonImmutable::parse($this->periodStart);

        if (! $this->lockedPeriod()) {
            Flux::toast(text: __('Verrouillez la période avant de générer le rapport.'), variant: 'danger');

            return null;
        }

        $rows = $report->handle($start);
        $end = PayrollPeriod::endFor($start);
        $filename = 'paie_'.$start->toDateString().'_'.$end->toDateString().'.csv';
        $number = fn (float $value) => number_format($value, 2, ',', '');

        return response()->streamDownload(function () use ($rows, $number): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\u{FEFF}");
            fputcsv($output, [
                __('Employé'), __('Heures travaillées'), __('Maladie payée (h)'), __('Formation (h)'),
                __('Vacances à payer (h)'), __('Primes à verser ($)'), __('Commissions à verser ($)'),
            ], ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['user']->lastname.', '.$row['user']->firstname,
                    $number($row['worked_hours']),
                    $number($row['sick_paid_hours']),
                    $number($row['training_hours']),
                    $number($row['vacation_hours']),
                    $number($row['bonus']),
                    $number($row['commission']),
                ], ';', '"', '');
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Périodes proposées : 26 périodes passées (environ un an) et les 2 suivantes.
     *
     * @return list<array{start: string, label: string, locked: bool}>
     */
    public function periodOptions(): array
    {
        $days = (int) config('payroll.period_days');
        $current = PayrollPeriod::startFor(now());
        $locked = PayrollPeriod::query()->pluck('start_date')->map(fn ($date) => $date->toDateString())->all();

        $options = [];

        for ($i = 2; $i >= -26; $i--) {
            $start = $current->addDays($i * $days);
            $options[] = [
                'start' => $start->toDateString(),
                'label' => $start->translatedFormat('j M Y').' – '.PayrollPeriod::endFor($start)->translatedFormat('j M Y'),
                'locked' => in_array($start->toDateString(), $locked, true),
            ];
        }

        return $options;
    }

    public function lockedPeriod(): ?PayrollPeriod
    {
        return PayrollPeriod::query()->with('lockedBy')->whereDate('start_date', $this->periodStart)->first();
    }

    public function render(BuildPayrollReport $report): View
    {
        $start = CarbonImmutable::parse($this->periodStart);

        return view('livewire.accounting.payroll', [
            'rows' => $report->handle($start),
            'periodEnd' => PayrollPeriod::endFor($start),
            'period' => $this->lockedPeriod(),
        ])->layout('layouts.app', ['title' => __('Paie')]);
    }
}
