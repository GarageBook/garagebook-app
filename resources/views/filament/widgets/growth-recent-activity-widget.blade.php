@php
    $sections = [
        [
            'title' => 'Laatste registraties',
            'rows' => $registrations,
            'empty' => 'Nog geen registraties beschikbaar.',
        ],
        [
            'title' => 'Laatste voertuigen',
            'rows' => $vehicles,
            'empty' => 'Nog geen voertuigen beschikbaar.',
        ],
        [
            'title' => 'Laatste onderhoudslogs',
            'rows' => $maintenance_logs,
            'empty' => 'Nog geen onderhoudslogs beschikbaar.',
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-3 border-b border-slate-200/80 px-6 py-5 dark:border-white/10 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Recente activiteit</h3>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Laatste registraties, voertuigen en onderhoudslogs uit de beheerdatabase.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-white/5 dark:text-slate-300">
                Realtime-ish overzicht
            </span>
        </div>

        <div class="grid gap-6 px-6 py-6 xl:grid-cols-3">
            @foreach ($sections as $section)
                <article class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-5 dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $section['title'] }}</h4>
                        <span class="text-xs font-medium text-slate-400 dark:text-slate-500">Laatste 5</span>
                    </div>

                    @if (count($section['rows']) === 0)
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white/80 px-4 py-8 text-center dark:border-white/10 dark:bg-white/[0.02]">
                            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $section['empty'] }}</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($section['rows'] as $row)
                                <div class="flex items-start justify-between gap-3 rounded-2xl bg-white/90 px-4 py-3 shadow-sm ring-1 ring-slate-200/70 dark:bg-gray-950/40 dark:ring-white/10">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $row['label'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $row['timestamp'] ?: '—' }}</p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        @if (! empty($row['source']) && $row['source'] !== '—')
                                            <span class="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-medium text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-500/20">
                                                {{ $row['source'] }}
                                            </span>
                                        @else
                                            <span class="text-xs text-slate-400 dark:text-slate-500">—</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
