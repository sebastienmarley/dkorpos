<?php

use App\Livewire\Labels\Index as LabelsIndex;
use App\Livewire\NotificationsBell;
use App\Models\LabelRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LabelsToPrint;
use Illuminate\Support\Collection;
use Livewire\Livewire;

beforeEach(function () {
    $this->printer = User::factory()->withRole('admin')->create(['is_active' => true]);
});

function labelReminders(User $user): Collection
{
    return $user->unreadNotifications()->where('type', LabelsToPrint::Type)->get();
}

it('avise chaque jour les responsables de l\'impression des étiquettes en attente', function () {
    Role::create(['name' => 'visiteur', 'label' => 'Visiteur', 'level' => 0, 'guard_name' => 'web']);
    $viewer = User::factory()->withRole('visiteur')->create(['is_active' => true]);
    $viewer->givePermissionTo('labels.view');
    $inactive = User::factory()->withRole('admin')->create(['is_active' => false]);
    LabelRequest::factory()->create(['quantity' => 2]);
    LabelRequest::factory()->create(['quantity' => 1]);

    $this->artisan('labels:queue-price-changes')->assertSuccessful();

    expect(labelReminders($this->printer)->sole()->data)
        ->title->toBe('3 étiquettes à imprimer')
        ->url->toBe(route('labels.index'))
        ->and(labelReminders($viewer))->toBeEmpty()
        ->and(labelReminders($inactive))->toBeEmpty();
});

it('remplace le rappel non lu au lieu d\'en empiler un par jour', function () {
    LabelRequest::factory()->create();

    $this->artisan('labels:queue-price-changes');
    $this->artisan('labels:queue-price-changes');

    expect(labelReminders($this->printer))->toHaveCount(1);
});

it('n\'avise personne quand la file est vide', function () {
    $this->artisan('labels:queue-price-changes');

    expect(labelReminders($this->printer))->toBeEmpty();
});

it('retire le rappel une fois toutes les étiquettes imprimées', function () {
    $first = LabelRequest::factory()->create();
    $second = LabelRequest::factory()->create();
    $this->artisan('labels:queue-price-changes');
    $this->actingAs($this->printer);

    $print = fn (LabelRequest $request) => Livewire::test(LabelsIndex::class)
        ->set('selected', [$request->id])
        ->call('print')
        ->call('askConfirmation')
        ->call('confirmPrinted');

    $print($first);
    expect(labelReminders($this->printer))->toHaveCount(1);

    $print($second);
    expect(labelReminders($this->printer))->toBeEmpty();
});

it('ouvre une notification depuis la cloche et la marque comme lue', function () {
    LabelRequest::factory()->create(['quantity' => 1]);
    $this->artisan('labels:queue-price-changes');
    $this->actingAs($this->printer);

    Livewire::test(NotificationsBell::class)
        ->assertSee('1 étiquette à imprimer')
        ->call('open', labelReminders($this->printer)->sole()->id)
        ->assertRedirect(route('labels.index'));

    expect(labelReminders($this->printer))->toBeEmpty();
});

it('ne permet pas d\'ouvrir la notification d\'un autre usager', function () {
    LabelRequest::factory()->create();
    $this->artisan('labels:queue-price-changes');
    $this->actingAs(User::factory()->create());

    Livewire::test(NotificationsBell::class)
        ->call('open', labelReminders($this->printer)->sole()->id)
        ->assertNotFound();

    expect(labelReminders($this->printer))->toHaveCount(1);
});
