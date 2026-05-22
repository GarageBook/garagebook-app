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
    <section class="overflow-hidden rounded-[2rem] border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5">
        <div class="border-b border-slate-200/80 px-6 py-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex min-w-0 items-start gap-3">
                    <span class="inline-flex shrink-0 rounded-2xl bg-violet-50 p-3 text-violet-700 ring-1 ring-violet-200">
                        <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h3 class="text-base font-semibold text-slate-950">Recente activiteit</h3>
                        <p class="mt-1 text-sm text-slate-500">Laatste registraties, voertuigen en onderhoudslogs uit de beheerdatabase.</p>
                    </div>
                </div>

                <span class="inline-flex items-center self-start rounded-full border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-600">
                    Realtime-ish overzicht
                </span>
            </div>
        </div>

        <div class="px-6 py-6">
            <div class="grid gap-6 xl:grid-cols-3">
                @foreach ($sections as $section)
                    <article class="rounded-[1.75rem] border border-slate-200/80 bg-gradient-to-b from-white to-slate-50/70 p-5 shadow-sm">
                        <div class="mb-4 flex items-center justify-between gap-3">
                            <h4 class="text-sm font-semibold text-slate-900">{{ $section['title'] }}</h4>
                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-500">Laatste 5</span>
                        </div>

                        @if (count($section['rows']) === 0)
                            <div class="flex min-h-[15rem] items-center justify-center rounded-[1.5rem] border border-dashed border-slate-300 bg-white px-4 py-8 text-center">
                                <p class="text-sm text-slate-500">{{ $section['empty'] }}</p>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($section['rows'] as $row)
                                    <div class="flex min-h-[4.5rem] items-start justify-between gap-3 rounded-[1.25rem] border border-slate-200/80 bg-white px-4 py-3 shadow-sm">
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-medium text-slate-900">{{ $row['label'] }}</p>
                                            <p class="mt-1 text-xs text-slate-500">{{ $row['timestamp'] ?: '—' }}</p>
                                        </div>
                                        <div class="shrink-0 self-start text-right">
                                            @if (! empty($row['source']) && $row['source'] !== '—')
                                                <span class="inline-flex max-w-[7.5rem] items-center truncate rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-medium text-sky-700 ring-1 ring-sky-200">{{ $row['source'] }}</span>
                                            @else
                                                <span class="text-xs text-slate-400">—</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</x-filament-widgets::widget>
