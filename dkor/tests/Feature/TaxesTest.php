<?php

use App\Enums\Province;
use App\Livewire\Accounting\Taxes;
use App\Models\Role;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->withRole('admin')->create());
});

it('crée une taxe avec la date de fin par défaut', function () {
    Livewire::test(Taxes::class)
        ->call('openCreate')
        ->assertSet('endDate', '2100-12-31')
        ->set('province', 'QC')
        ->set('name', ' TVQ ')
        ->set('rate', '9.975')
        ->set('isCompound', true)
        ->set('startDate', '2013-01-01')
        ->call('save')
        ->assertHasNoErrors();

    $tax = Tax::sole();
    expect($tax->province)->toBe(Province::Quebec)
        ->and($tax->name)->toBe('TVQ')
        ->and($tax->rate)->toBe('9.975')
        ->and($tax->is_compound)->toBeTrue()
        ->and($tax->end_date->toDateString())->toBe('2100-12-31');
});

it('permet plusieurs taxes par province et la même taxe dans deux provinces', function () {
    Tax::factory()->create(['province' => 'QC', 'name' => 'TPS']);
    Tax::factory()->create(['province' => 'QC', 'name' => 'TVQ']);

    Livewire::test(Taxes::class)
        ->call('openCreate')
        ->set('province', 'ON')
        ->set('name', 'TPS')
        ->set('rate', '13')
        ->set('startDate', '2020-01-01')
        ->call('save')
        ->assertHasNoErrors();

    expect(Tax::count())->toBe(3);
});

it('valide les champs', function (array $overrides, string $field) {
    Livewire::test(Taxes::class)
        ->call('openCreate')
        ->set(['province' => 'QC', 'name' => 'TPS', 'rate' => '5', 'startDate' => '2020-01-01', 'endDate' => '2100-12-31', ...$overrides])
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'nom requis' => [['name' => ''], 'name'],
    'province invalide' => [['province' => 'XX'], 'province'],
    'taux négatif' => [['rate' => '-1'], 'rate'],
    'taux supérieur à 100' => [['rate' => '101'], 'rate'],
    'trop de décimales' => [['rate' => '5.1234'], 'rate'],
    'fin avant début' => [['startDate' => '2025-01-02', 'endDate' => '2025-01-01'], 'endDate'],
]);

it('refuse une taxe de même nom qui chevauche une taxe existante dans la province', function () {
    Tax::factory()->create(['province' => 'QC', 'name' => 'TPS', 'start_date' => '2020-01-01', 'end_date' => '2100-12-31']);

    Livewire::test(Taxes::class)
        ->call('openCreate')
        ->set('province', 'QC')
        ->set('name', 'TPS')
        ->set('rate', '4')
        ->set('startDate', '2026-01-01')
        ->call('save')
        ->assertHasErrors('name');

    expect(Tax::count())->toBe(1);
});

it('remplace un taux en faisant expirer l\'ancien puis en créant le nouveau', function () {
    $this->travelTo(Carbon::parse('2026-06-15'));
    $old = Tax::factory()->create(['province' => 'QC', 'name' => 'TPS', 'rate' => 5, 'start_date' => '2008-01-01']);

    Livewire::test(Taxes::class)
        ->call('openExpire', $old->id)
        ->assertSet('expireDate', '2026-06-15')
        ->set('expireDate', '2026-06-30')
        ->call('expire')
        ->assertHasNoErrors();

    Livewire::test(Taxes::class)
        ->call('openCreate')
        ->set('province', 'QC')
        ->set('name', 'TPS')
        ->set('rate', '6')
        ->set('startDate', '2026-07-01')
        ->call('save')
        ->assertHasNoErrors();

    expect(Tax::activeOn('2026-06-30')->sole()->rate)->toBe('5.000')
        ->and(Tax::activeOn('2026-07-01')->sole()->rate)->toBe('6.000');
});

it('refuse une date d\'expiration passée, avant le début ou qui prolonge la taxe', function (string $date) {
    $this->travelTo(Carbon::parse('2026-06-15'));
    $tax = Tax::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

    Livewire::test(Taxes::class)
        ->call('openExpire', $tax->id)
        ->set('expireDate', $date)
        ->call('expire')
        ->assertHasErrors('expireDate');

    expect($tax->fresh()->end_date->toDateString())->toBe('2026-12-31');
})->with(['dans le passé' => '2026-06-14', 'prolongation' => '2027-01-31', 'même fin' => '2026-12-31']);

it('ne permet jamais de changer le taux ni de supprimer une taxe', function () {
    $tax = Tax::factory()->create(['rate' => 5]);

    expect(fn () => $tax->update(['rate' => 6]))->toThrow(LogicException::class)
        ->and(fn () => $tax->update(['name' => 'Autre']))->toThrow(LogicException::class)
        ->and(fn () => $tax->delete())->toThrow(LogicException::class);

    expect($tax->fresh()->rate)->toBe('5.000');
});

it('trouve les taxes en vigueur à une date, bornes incluses', function () {
    Tax::factory()->create(['name' => 'Ancienne', 'start_date' => '2010-01-01', 'end_date' => '2019-12-31']);
    Tax::factory()->create(['name' => 'Actuelle', 'start_date' => '2020-01-01']);

    expect(Tax::activeOn('2019-12-31')->pluck('name')->all())->toBe(['Ancienne'])
        ->and(Tax::activeOn('2020-01-01')->pluck('name')->all())->toBe(['Actuelle'])
        ->and(Tax::activeOn('2009-12-31')->count())->toBe(0);
});

it('filtre la liste par province et par date', function () {
    Tax::factory()->create(['province' => 'QC', 'name' => 'TVQ', 'start_date' => '2013-01-01']);
    Tax::factory()->create(['province' => 'ON', 'name' => 'TVH', 'start_date' => '2010-07-01', 'end_date' => '2015-12-31']);

    Livewire::test(Taxes::class)->assertSee('TVQ')->assertSee('TVH')
        ->set('filterProvince', 'ON')->assertSee('TVH')->assertDontSee('TVQ')
        ->set('filterProvince', '')->set('filterDate', '2020-01-01')->assertSee('TVQ')->assertDontSee('TVH');
});

it('réserve la création et l\'expiration aux détenteurs des permissions', function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $viewer = User::factory()->withRole('visiteur')->create()->givePermissionTo('taxes.view');
    $tax = Tax::factory()->create();

    $this->actingAs($viewer);

    Livewire::test(Taxes::class)->call('openCreate')->assertForbidden();
    Livewire::test(Taxes::class)->call('openExpire', $tax->id)->assertForbidden();
});
