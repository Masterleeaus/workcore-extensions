<x-layouts.app>
    <main
        class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8"
        data-workcore-workspace="{{ $workspace['key'] }}"
        data-workcore-company="{{ $companyId }}"
        data-workcore-entitlement-revision="{{ $entitlementRevision }}"
    >
        <header class="mb-6 flex flex-col gap-3 border-b border-solid border-black/10 pb-6 dark:border-white/10 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <p class="mb-2 text-xs font-semibold uppercase tracking-[0.14em] text-foreground/55">
                    {{ __('workcore::navigation.workspace') }}
                </p>
                <h1 class="m-0 text-3xl font-semibold tracking-tight text-foreground sm:text-4xl">
                    {{ $selected['label'] }}
                </h1>
                <p class="mb-0 mt-3 max-w-2xl text-sm leading-6 text-foreground/65 sm:text-base">
                    {{ $selected['description'] }}
                </p>
            </div>
            <div class="text-xs text-foreground/45">
                {{ __('workcore::navigation.company_context', ['company' => $companyId]) }}
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)]">
            <nav aria-label="{{ __('workcore::navigation.sections', ['workspace' => $workspace['label']]) }}">
                <div class="sticky top-4 rounded-2xl border border-solid border-black/10 bg-background p-2 dark:border-white/10">
                    <a
                        class="flex items-start gap-3 rounded-xl px-3 py-3 text-sm transition hover:bg-foreground/5 {{ $selected['key'] === $workspace['key'] ? 'bg-foreground/5 font-semibold' : '' }}"
                        href="{{ route($workspace['route_name']) }}"
                        aria-current="{{ $selected['key'] === $workspace['key'] ? 'page' : 'false' }}"
                    >
                        <span class="mt-0.5 text-foreground/60" aria-hidden="true">●</span>
                        <span>{{ __('workcore::navigation.overview') }}</span>
                    </a>

                    @foreach ($sections as $section)
                        <a
                            class="mt-1 flex items-start gap-3 rounded-xl px-3 py-3 text-sm transition hover:bg-foreground/5 {{ $selected['key'] === $section['key'] ? 'bg-foreground/5 font-semibold' : '' }}"
                            href="{{ route($section['route_name']) }}"
                            aria-current="{{ $selected['key'] === $section['key'] ? 'page' : 'false' }}"
                        >
                            <span class="mt-0.5 text-foreground/60" aria-hidden="true">●</span>
                            <span>{{ $section['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </nav>

            <section class="min-w-0" aria-labelledby="workcore-section-title">
                <div class="rounded-2xl border border-solid border-black/10 bg-background p-5 dark:border-white/10 sm:p-7">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 id="workcore-section-title" class="m-0 text-xl font-semibold text-foreground">
                                {{ $selected['label'] }}
                            </h2>
                            <p class="mb-0 mt-2 max-w-3xl text-sm leading-6 text-foreground/65">
                                {{ $selected['description'] }}
                            </p>
                        </div>
                        <span class="inline-flex w-fit rounded-full border border-solid border-black/10 px-3 py-1 text-xs font-medium text-foreground/60 dark:border-white/10">
                            {{ __('workcore::navigation.foundation_ready') }}
                        </span>
                    </div>

                    <div class="mt-8 border-t border-solid border-black/10 pt-6 dark:border-white/10">
                        <h3 class="m-0 text-sm font-semibold text-foreground">
                            {{ __('workcore::navigation.available_capabilities') }}
                        </h3>
                        <ul class="mt-3 flex list-none flex-wrap gap-2 p-0" aria-label="{{ __('workcore::navigation.available_capabilities') }}">
                            @foreach ($selected['capabilities'] as $capability)
                                <li class="rounded-lg bg-foreground/5 px-2.5 py-1.5 font-mono text-xs text-foreground/65">
                                    {{ $capability }}
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <p class="mb-0 mt-8 rounded-xl bg-foreground/[0.035] p-4 text-sm leading-6 text-foreground/60">
                        {{ __('workcore::navigation.structural_shell_notice') }}
                    </p>
                </div>
            </section>
        </div>
    </main>
</x-layouts.app>
