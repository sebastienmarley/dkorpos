<x-layouts::app :title="__('Home')">
    <div class="flex min-h-[70vh] items-center justify-center px-4">
        <div class="w-full max-w-3xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-6 flex items-center justify-between gap-4">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.2em] text-slate-500 dark:text-slate-400">
                        {{ __('Overview') }}
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">
                        {{ __('Welcome home') }}
                    </h1>
                </div>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                    {{ __('Online') }}
                </span>
            </div>

            <p class="text-base text-slate-600 dark:text-slate-300">
                {{ __('This is the new home page. The dashboard route has been replaced with a dedicated landing page for authenticated users.') }}
            </p>

            <div class="mt-8 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl bg-slate-100 p-4 dark:bg-slate-800">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Status') }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ __('Ready') }}</p>
                </div>
                <div class="rounded-xl bg-slate-100 p-4 dark:bg-slate-800">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Access') }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ __('Secure') }}</p>
                </div>
                <div class="rounded-xl bg-slate-100 p-4 dark:bg-slate-800">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Area') }}</p>
                    <p class="mt-2 text-2xl font-bold text-slate-900 dark:text-white">{{ __('Home') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
