<section class="mx-auto w-full max-w-3xl p-6">
    <div class="mb-6">
        <flux:heading level="1">{{ $user ? 'Edit user' : 'Create user' }}</flux:heading>
    </div>

    @if ($showDuplicatePrompt && $existingUser)
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-medium">A user with the username "{{ $username }}" already exists.</p>
            <p class="mt-1">Is this a new employee or a returning employee?</p>

            <div class="mt-4 flex flex-wrap gap-3">
                <flux:button type="button" variant="primary" wire:click="confirmNewEmployee">New employee</flux:button>
                <flux:button type="button" variant="filled" wire:click="confirmReturningEmployee">Returning employee</flux:button>
            </div>
        </div>
    @endif

    <form wire:submit="save" class="space-y-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="firstname" :label="__('First name')" type="text" required autofocus />
            <flux:input wire:model="lastname" :label="__('Last name')" type="text" required />
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="email" :label="__('Email')" type="email" required />
            <div>
                <label for="role" class="mb-1.5 block text-sm font-medium text-zinc-700">{{ __('Role') }}</label>
                <select id="role" wire:model="role" class="w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm focus:border-zinc-400 focus:outline-none">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="username" class="mb-1.5 block text-sm font-medium text-zinc-700">{{ __('Username') }}</label>
                <input id="username" type="text" value="{{ $username }}" class="w-full rounded-md border border-zinc-300 bg-zinc-50 px-3 py-2 text-sm text-zinc-900 shadow-sm" readonly />
            </div>

            <label class="flex items-center gap-3 pt-7">
                <input type="checkbox" wire:model="is_active" class="h-4 w-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500" />
                <span class="text-sm font-medium text-zinc-700">{{ __('Active') }}</span>
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <flux:input wire:model="first_day" :label="__('First day')" type="date" />
            <flux:input wire:model="last_day" :label="__('Last day')" type="date" />
        </div>

        <div class="flex items-center justify-end gap-3">
            <flux:button type="button" variant="filled" x-on:click="window.history.back()">
                {{ __('Cancel') }}
            </flux:button>
            <flux:button type="submit" variant="primary">
                {{ $user ? __('Save changes') : __('Create user') }}
            </flux:button>
        </div>
    </form>
</section>
