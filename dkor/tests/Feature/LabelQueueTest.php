<?php

use App\Enums\LabelReason;
use App\Livewire\LabelQueueForm;
use App\Livewire\Labels\Index as LabelsIndex;
use App\Models\InventoryStock;
use App\Models\LabelRequest;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->withRole('admin')->create();
    $this->actingAs($this->user);
});

function visiteurAvec(string ...$permissions): User
{
    Role::firstOrCreate(['name' => 'visiteur', 'guard_name' => 'web'], ['label' => 'Visiteur', 'level' => 0]);
    $user = User::factory()->withRole('visiteur')->create();
    $user->givePermissionTo($permissions);

    return $user;
}

describe('file d\'étiquettes', function () {
    it('cumule les demandes en attente d\'un même produit et d\'une même raison', function () {
        $product = Product::factory()->create();

        LabelRequest::enqueue($product, 2);
        LabelRequest::enqueue($product, 1);
        LabelRequest::enqueue($product, 3, LabelReason::PriceChange);

        expect(LabelRequest::pending()->where('reason', LabelReason::Manual)->sole()->quantity)->toBe(3)
            ->and(LabelRequest::pending()->count())->toBe(2);
    });

    it('crée une nouvelle demande quand la précédente est imprimée', function () {
        $printed = LabelRequest::factory()->printed()->create(['quantity' => 2]);

        LabelRequest::enqueue($printed->product, 1);

        expect($printed->fresh()->quantity)->toBe(2)
            ->and(LabelRequest::pending()->sole()->quantity)->toBe(1);
    });
});

describe('modale d\'ajout', function () {
    it('propose par défaut le nombre d\'unités en démo et ajoute à la file', function () {
        $product = Product::factory()->create();
        InventoryStock::query()->updateOrCreate(['product_id' => $product->id], ['quantity_in_demo' => 2]);

        Livewire::test(LabelQueueForm::class)
            ->dispatch('queue-label', productId: $product->id)
            ->assertSet('quantity', 2)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('label-queued');

        expect(LabelRequest::pending()->sole())
            ->product_id->toBe($product->id)
            ->quantity->toBe(2)
            ->requested_by->toBe($this->user->id);
    });

    it('refuse une quantité nulle', function () {
        Livewire::test(LabelQueueForm::class)
            ->dispatch('queue-label', productId: Product::factory()->create()->id)
            ->set('quantity', 0)
            ->call('save')
            ->assertHasErrors('quantity');
    });

    it('refuse l\'ajout sans la permission', function () {
        $this->actingAs(visiteurAvec('products.view'));

        Livewire::test(LabelQueueForm::class)
            ->dispatch('queue-label', productId: Product::factory()->create()->id)
            ->assertForbidden();
    });
});

describe('étiquettes à imprimer', function () {
    it('n\'affiche que les demandes en attente', function () {
        $pending = LabelRequest::factory()->create();
        $printed = LabelRequest::factory()->printed()->create();

        $this->get(route('labels.index'))
            ->assertOk()
            ->assertSee($pending->product->model)
            ->assertDontSee($printed->product->model);
    });

    it('imprime une page par étiquette demandée', function () {
        $request = LabelRequest::factory()->create(['quantity' => 3]);

        $response = $this->get(route('labels.print', ['ids' => [$request->id]]))->assertOk();

        expect(substr_count($response->getContent(), '<section class="label">'))->toBe(3);
    });

    it('marque les étiquettes imprimées après confirmation seulement', function () {
        $printedLater = LabelRequest::factory()->create();
        $leftOut = LabelRequest::factory()->create();

        $component = Livewire::test(LabelsIndex::class)
            ->set('selected', [$printedLater->id])
            ->call('print')
            ->assertDispatched('print-labels')
            ->call('askConfirmation')
            ->assertSet('showConfirmModal', true);

        expect($printedLater->fresh()->printed_at)->toBeNull();

        $component->call('confirmPrinted');

        expect($printedLater->fresh())->printed_at->not->toBeNull()->printed_by->toBe($this->user->id)
            ->and($leftOut->fresh()->printed_at)->toBeNull();
    });

    it('garde les étiquettes dans la file si l\'impression n\'est pas confirmée', function () {
        $request = LabelRequest::factory()->create();

        Livewire::test(LabelsIndex::class)
            ->call('print')
            ->call('askConfirmation')
            ->call('cancelPrinted')
            ->assertSet('showConfirmModal', false);

        expect($request->fresh()->printed_at)->toBeNull();
    });

    it('refuse l\'impression de la file sans la permission', function () {
        $this->actingAs(visiteurAvec('labels.view'));

        Livewire::test(LabelsIndex::class)->call('print')->assertForbidden();
        $this->get(route('labels.print', ['ids' => [LabelRequest::factory()->create()->id]]))->assertForbidden();
    });
});
