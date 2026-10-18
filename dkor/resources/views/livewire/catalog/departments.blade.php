<div>
    <div class="mb-4 flex items-center justify-between gap-3">
        <flux:text class="text-zinc-500">{{ __('Chaque catégorie appartient à un département. Le département d\'un produit est déduit de sa catégorie.') }}</flux:text>

        <div class="flex shrink-0 gap-2">
            @can('categories.create')
                <flux:button icon="plus" wire:click="openCreateCategory" :disabled="$departments->isEmpty()">
                    {{ __('Nouvelle catégorie') }}
                </flux:button>
            @endcan
            @can('departments.create')
                <flux:button variant="primary" icon="plus" wire:click="openCreateDepartment">
                    {{ __('Nouveau département') }}
                </flux:button>
            @endcan
        </div>
    </div>

    <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm dark:divide-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
        @forelse ($departments as $department)
            <div wire:key="department-{{ $department->id }}" x-data="{ open: true }">
                <div class="flex items-center gap-3 px-4 py-3">
                    <button type="button" class="flex flex-1 items-center gap-2 text-left" @click="open = ! open">
                        <flux:icon name="chevron-right" variant="micro" class="text-zinc-400 transition-transform" ::class="open && 'rotate-90'" />
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $department->name }}</span>
                        <flux:badge size="sm" color="zinc">{{ trans_choice(':count produit|:count produits', $department->products_count) }}</flux:badge>
                    </button>

                    @can('categories.create')
                        <flux:button variant="ghost" size="sm" icon="plus" wire:click="openCreateCategory({{ $department->id }})" :tooltip="__('Ajouter une catégorie')" />
                    @endcan
                    @can('departments.edit')
                        <flux:button variant="ghost" size="sm" icon="pencil" wire:click="openEditDepartment({{ $department->id }})" :tooltip="__('Modifier le département')" />
                    @endcan
                </div>

                <div x-show="open" class="border-t border-zinc-100 bg-zinc-50/60 dark:border-zinc-800 dark:bg-zinc-800/30">
                    @forelse ($department->categories as $category)
                        <div wire:key="category-{{ $category->id }}" class="flex items-center gap-3 py-2 pl-11 pr-4">
                            <span class="flex-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $category->name }}</span>
                            <flux:text class="text-sm text-zinc-400">{{ $category->products_count }}</flux:text>
                            @can('categories.edit')
                                <flux:button variant="ghost" size="xs" icon="pencil" wire:click="openEditCategory({{ $category->id }})" :tooltip="__('Modifier la catégorie')" />
                            @endcan
                        </div>
                    @empty
                        <flux:text class="py-2 pl-11 pr-4 text-sm text-zinc-400">{{ __('Aucune catégorie.') }}</flux:text>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center gap-2 py-12">
                <flux:icon name="tag" class="h-8 w-8 text-zinc-300" />
                <flux:text class="text-zinc-400">{{ __('Aucun département.') }}</flux:text>
            </div>
        @endforelse
    </div>

    <flux:modal wire:model="showDepartmentModal" class="w-full max-w-sm">
        <flux:heading class="mb-1">{{ $editingDepartmentId ? __('Modifier le département') : __('Nouveau département') }}</flux:heading>

        <form wire:submit="saveDepartment" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="departmentName" type="text" required autofocus />
                <flux:error name="departmentName" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showDepartmentModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingDepartmentId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model="showCategoryModal" class="w-full max-w-sm">
        <flux:heading class="mb-1">{{ $editingCategoryId ? __('Modifier la catégorie') : __('Nouvelle catégorie') }}</flux:heading>

        <form wire:submit="saveCategory" class="mt-6 space-y-4">
            <flux:field>
                <flux:label>{{ __('Département') }}</flux:label>
                <flux:select wire:model="categoryDepartmentId" required>
                    <flux:select.option value="">{{ __('Choisir…') }}</flux:select.option>
                    @foreach ($departments as $department)
                        <flux:select.option :value="$department->id">{{ $department->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="categoryDepartmentId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Nom') }}</flux:label>
                <flux:input wire:model="categoryName" type="text" required autofocus />
                <flux:error name="categoryName" />
            </flux:field>

            <div class="flex justify-end gap-3 pt-2">
                <flux:button type="button" variant="ghost" wire:click="$set('showCategoryModal', false)">{{ __('Annuler') }}</flux:button>
                <flux:button type="submit" variant="primary">{{ $editingCategoryId ? __('Mettre à jour') : __('Créer') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
