@php
    $accentByIndex = ['sky', 'cyan', 'indigo', 'emerald', 'teal', 'amber', 'violet', 'rose'];

    $accentClasses = [
        'sky' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-500/20',
        'cyan' => 'bg-cyan-50 text-cyan-700 ring-cyan-200 dark:bg-cyan-500/10 dark:text-cyan-200 dark:ring-cyan-500/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-200 dark:ring-indigo-500/20',
        'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20',
        'teal' => 'bg-teal-50 text-teal-700 ring-teal-200 dark:bg-teal-500/10 dark:text-teal-200 dark:ring-teal-500/20',
        'amber' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/20',
        'violet' => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-200 dark:ring-violet-500/20',
        'rose' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-500/20',
    ];
@endphp

<x-filament-widgets::widget>
    <section class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-4 border-b border-slate-200/80 px-6 py-5 dark:border-white/10 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-1">
                <h2 class="text-base font-semibold text-slate-950 dark:text-white">KPI-overzicht</h2>
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Bezoekers- en registratiecijfers uit lokaal opgeslagen analytics- en gebruikersdata.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600 dark:bg-white/5 dark:text-slate-300">
                Laatste 30 dagen
            </span>
        </div>

        <div class="grid gap-4 px-6 py-6 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $index => $card)
                @php
                    $accent = $accentByIndex[$index] ?? 'sky';
                    $displayValue = $card['is_available']
                        ? (is_numeric($card['value']) ? number_format((float) $card['value'], str_contains((string) ($card['suffix'] ?? ''), '%') ? 1 : 0, ',', '.') : $card['value'])
                        : 'niet beschikbaar';

                    if ($card['is_available'] && isset($card['suffix']) && is_numeric($card['value'])) {
                        $displayValue .= $card['suffix'];
                    }
                @endphp
                <article class="rounded-2xl border border-slate-200/80 bg-gradient-to-br from-white to-slate-50 p-5 shadow-sm transition hover:border-slate-300 dark:border-white/10 dark:from-gray-900 dark:to-slate-900">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-3">
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $card['label'] }}</p>
                            <p class="text-2xl font-semibold tracking-tight text-slate-950 dark:text-white">{{ $displayValue }}</p>
                            @if (! empty($card['meta']))
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $card['meta'] }}</p>
                            @endif
                        </div>
                        <span class="inline-flex min-w-[3rem] justify-center rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $accentClasses[$accent] }}">
                            {{ $index === 6 ? 'CR' : ($index === 7 ? 'Live' : (($index + 1) . '')) }}
                        </span>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
