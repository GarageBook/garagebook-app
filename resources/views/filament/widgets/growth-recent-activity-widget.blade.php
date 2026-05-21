@php
    $sections = [
        [
            'title' => 'Laatste registraties',
            'rows' => collect($registrations)->take(5),
            'empty' => 'Nog geen registraties beschikbaar.',
            'tone' => 'bg-sky-50 text-sky-700',
        ],
        [
            'title' => 'Laatste voertuigen',
            'rows' => collect($vehicles)->take(5),
            'empty' => 'Nog geen voertuigen beschikbaar.',
            'tone' => 'bg-violet-50 text-violet-700',
        ],
        [
            'title' => 'Laatste onderhoudslogs',
            'rows' => collect($maintenance_logs)->take(5),
            'empty' => 'Nog geen onderhoudslogs beschikbaar.',
            'tone' => 'bg-emerald-50 text-emerald-700',
        ],
    ];
@endphp

<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-start justify-between gap-4 border-b border-gray-100 p-5 dark:border-white/10">
            <div class="flex items-start gap-3">
                <span class="inline-flex rounded-xl bg-gray-50 p-2 text-gray-700 dark:bg-white/5 dark:text-white/80">
                    <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5" />
                </span>
                <div class="space-y-1">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Recente activiteit</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Laatste registraties, voertuigen en onderhoudslogs uit de beheerdatabase.</p>
                </div>
            </div>
            <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                Realtime-ish overzicht
            </span>
        </div>

        <div class="grid grid-cols-1 gap-5 p-5 xl:grid-cols-3">
            @foreach ($sections as $section)
                <article class="rounded-2xl border border-gray-200 bg-gray-50/70 p-4 dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h4>
                        <span class="text-xs text-gray-400 dark:text-gray-500">Laatste {{ $section['rows']->count() ?: 5 }}</span>
                    </div>

                    @if ($section['rows']->isEmpty())
                        <div class="rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-white/10 dark:bg-white/[0.02] dark:text-gray-400">
                            {{ $section['empty'] }}
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach ($section['rows'] as $row)
                                <div class="flex items-start justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3 shadow-sm dark:border-white/10 dark:bg-gray-950/40">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium text-gray-900 dark:text-white" title="{{ $row['label'] }}">{{ $row['label'] }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $row['timestamp'] ?: '—' }}</p>
                                    </div>
                                    <div class="shrink-0">
                                        @if (! empty($row['source']) && $row['source'] !== '—')
                                            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium {{ $section['tone'] }} dark:bg-white/5 dark:text-white/80">
                                                {{ $row['source'] }}
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
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
</x-filament-widgets::widget>
