<x-filament-panels::layout.base :livewire="null">
    <div class="fi-page">
        <div class="fi-page-header-main-ctn">
            <div class="fi-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white">SEO Health</h1>
                    <p class="fi-header-subheading mt-2 text-sm text-gray-500 dark:text-gray-400">Read-only controle van publieke garagepagina's, sitemap, canonical, structured data en contentkwaliteit.</p>
                </div>

                <x-filament::button tag="a" href="{{ route('admin.seo-health-dashboard.export') }}" color="gray" icon="heroicon-o-arrow-down-tray">
                    Export CSV
                </x-filament::button>
            </div>

            <div class="fi-page-main">
                <div class="fi-page-content">
    @php
        $status = $report['status'] ?? 'fail';
        $statusClasses = [
            'pass' => 'border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950 dark:text-green-100',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100',
            'fail' => 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100',
        ];
        $card = 'rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900';
        $metricLabel = fn (string $metric): string => str($metric)->replace('_', ' ')->headline()->toString();
        $metricValue = fn (mixed $value): string => is_array($value)
            ? collect($value)->map(fn ($count, $band) => str_replace('_', '-', $band).': '.$count)->implode(' | ')
            : (string) $value;
        $tabs = [
            'critical' => 'Critical',
            'warning' => 'Warnings',
            'opportunity' => 'Opportunities',
            'missing_photo' => 'Zonder foto',
            'no_maintenance_logs' => 'Zonder onderhoud',
            'short_log_descriptions' => 'Korte omschrijvingen',
            'low_quality_score' => 'Lage score',
            'missing_from_sitemap' => 'Ontbreekt in sitemap',
        ];
    @endphp

    <div class="space-y-6">
        <section class="rounded-lg border p-4 {{ $statusClasses[$status] ?? $statusClasses['fail'] }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide">SEO Health status</p>
                    <h2 class="text-2xl font-semibold">{{ strtoupper($status) }}</h2>
                    <p class="mt-1 text-sm">Alleen critical en warning bepalen deze status. Opportunities zijn contentkansen.</p>
                </div>
                <div class="text-right text-sm">
                    <div>{{ $report['critical_errors'] ?? 0 }} critical</div>
                    <div>{{ $report['warnings'] ?? 0 }} warnings</div>
                    <div>{{ $report['opportunity_count'] ?? 0 }} opportunities</div>
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="{{ $card }}">
                <h3 class="mb-1 text-lg font-semibold">Technische fouten</h3>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Critical blokkeert of vervuilt indexatie. Warning wijst op technische inconsistentie die herstel verdient.</p>
                <dl class="space-y-2 text-sm">
                    @foreach(($report['critical'] ?? []) as $metric => $value)
                        <div class="flex justify-between gap-4"><dt>{{ $metricLabel($metric) }}</dt><dd>{{ $metricValue($value) }}</dd></div>
                    @endforeach
                    @foreach(($report['warning_counts'] ?? []) as $metric => $value)
                        <div class="flex justify-between gap-4"><dt>{{ $metricLabel($metric) }}</dt><dd>{{ $metricValue($value) }}</dd></div>
                    @endforeach
                </dl>
            </div>

            <div class="{{ $card }}">
                <h3 class="mb-1 text-lg font-semibold">Product coverage</h3>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Informatieve productdekking. Deze cijfers zetten de SEO-status niet op warning.</p>
                <dl class="space-y-2 text-sm">
                    @foreach(($report['product_metrics'] ?? []) as $metric => $value)
                        <div class="flex justify-between gap-4"><dt>{{ $metricLabel($metric) }}</dt><dd>{{ $metricValue($value) }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <div class="{{ $card }}">
                <h3 class="mb-1 text-lg font-semibold">Indexability</h3>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Controleert of publieke, indexeerbare garagepagina's consistent indexeerbaar zijn.</p>
                <dl class="space-y-2 text-sm">
                    @foreach(($report['indexability_metrics'] ?? []) as $metric => $value)
                        <div class="flex justify-between gap-4"><dt>{{ $metricLabel($metric) }}</dt><dd>{{ $metricValue($value) }}</dd></div>
                    @endforeach
                </dl>
            </div>

            <div class="{{ $card }}">
                <h3 class="mb-1 text-lg font-semibold">Sitemap</h3>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">De sitemap hoort alleen canonical, indexeerbare garage-URL's te bevatten.</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt>URLs in sitemap-garages.xml</dt><dd>{{ $report['sitemap']['url_count'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Sitemap eligible</dt><dd>{{ $report['sitemap']['eligible_count'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Noindex in sitemap</dt><dd>{{ count($report['sitemap']['noindex_urls'] ?? []) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Demo/outreach in sitemap</dt><dd>{{ count($report['sitemap']['demo_outreach_urls'] ?? []) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Eligible ontbreekt</dt><dd>{{ count($report['sitemap']['eligible_missing_urls'] ?? []) }}</dd></div>
                </dl>
            </div>

            <div class="{{ $card }}">
                <h3 class="mb-1 text-lg font-semibold">Content opportunities</h3>
                <p class="mb-3 text-sm text-gray-500 dark:text-gray-400">Kansen om publieke garagepagina's vollediger en bruikbaarder te maken.</p>
                <dl class="space-y-2 text-sm">
                    @foreach(($report['content_quality_metrics'] ?? []) as $metric => $value)
                        <div class="flex justify-between gap-4"><dt>{{ $metricLabel($metric) }}</dt><dd>{{ $metricValue($value) }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </section>

        <section class="{{ $card }}">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-semibold">Action items</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Filterbare technische issues en contentkansen per officiële publieke garage-URL.</p>
                </div>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ count($report['action_items'] ?? []) }} items</span>
            </div>

            <div class="mb-4 flex flex-wrap gap-2 text-sm">
                @foreach($tabs as $code => $label)
                    <a class="rounded-md border border-gray-200 px-3 py-1 text-gray-700 dark:border-gray-700 dark:text-gray-200" href="#{{ $code }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1100px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">ID</th>
                            <th class="py-2 pr-4">Voertuig</th>
                            <th class="py-2 pr-4">Severity</th>
                            <th class="py-2 pr-4">Score</th>
                            <th class="py-2 pr-4">Indexability</th>
                            <th class="py-2 pr-4">Sitemap</th>
                            <th class="py-2 pr-4">Reasons</th>
                            <th class="py-2 pr-4">Publiek</th>
                            <th class="py-2 pr-4">Admin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($report['action_items'] ?? [] as $row)
                            <tr id="{{ $row['severity'] }}">
                                <td class="py-3 pr-4">{{ $row['vehicle_id'] }}</td>
                                <td class="py-3 pr-4 font-medium">{{ $row['vehicle_label'] }}</td>
                                <td class="py-3 pr-4">{{ $row['severity'] }}</td>
                                <td class="py-3 pr-4">{{ $row['quality_score'] }}</td>
                                <td class="py-3 pr-4">{{ $row['indexability_status'] }}</td>
                                <td class="py-3 pr-4">{{ $row['sitemap_status'] }}</td>
                                <td class="py-3 pr-4">{{ implode(', ', $row['reason_codes']) }}</td>
                                <td class="py-3 pr-4"><a class="text-primary-600" href="{{ $row['public_url'] }}" target="_blank" rel="noopener">Publieke pagina</a></td>
                                <td class="py-3 pr-4"><a class="text-primary-600" href="{{ $row['admin_url'] }}">Beheer voertuig</a></td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-gray-500" colspan="9">Geen action items gevonden.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="{{ $card }}">
            <h3 class="mb-3 text-lg font-semibold">GSC validation shortlist</h3>
            @if(($report['validation_shortlist'] ?? []) !== [])
                <ul class="space-y-2 text-sm">
                    @foreach($report['validation_shortlist'] as $url)
                        <li><a class="text-primary-600" href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a></li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Geen URLs die op basis van lokale checks opnieuw gevalideerd moeten worden.</p>
            @endif
        </section>
    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::layout.base>
