<x-filament-panels::page>
    @php
        $status = $report['status'] ?? 'fail';
        $statusClasses = [
            'pass' => 'border-green-200 bg-green-50 text-green-900 dark:border-green-900 dark:bg-green-950 dark:text-green-100',
            'warning' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100',
            'fail' => 'border-red-200 bg-red-50 text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100',
        ];
        $card = 'rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900';
    @endphp

    <div class="space-y-6">
        <section class="rounded-lg border p-4 {{ $statusClasses[$status] ?? $statusClasses['fail'] }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm font-medium uppercase tracking-wide">SEO Health status</p>
                    <h2 class="text-2xl font-semibold">{{ strtoupper($status) }}</h2>
                </div>
                <div class="text-right text-sm">
                    <div>{{ $report['critical_errors'] ?? 0 }} kritieke fouten</div>
                    <div>{{ $report['warnings'] ?? 0 }} waarschuwingen</div>
                </div>
            </div>
        </section>

        <section>
            <h3 class="mb-3 text-lg font-semibold">Indexability overview</h3>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach([
                    'Totaal voertuigen' => $report['overview']['total_vehicles'] ?? 0,
                    'Publieke voertuigen' => $report['overview']['public_vehicles'] ?? 0,
                    'Indexeerbaar publiek' => $report['overview']['indexable_public_garage_pages'] ?? 0,
                    'Noindex publiek' => $report['overview']['noindex_public_garage_pages'] ?? 0,
                    'Demo/outreach' => $report['overview']['demo_outreach_vehicles'] ?? 0,
                    'Met public_slug' => $report['overview']['vehicles_with_public_slug'] ?? 0,
                    'Zonder public_slug' => $report['overview']['vehicles_without_public_slug'] ?? 0,
                ] as $label => $value)
                    <div class="{{ $card }}">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-semibold">{{ $value }}</div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <div class="{{ $card }}">
                <h3 class="mb-3 text-lg font-semibold">Sitemap health</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt>URLs in sitemap-garages.xml</dt><dd>{{ $report['sitemap']['url_count'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Sitemap eligible</dt><dd>{{ $report['sitemap']['eligible_count'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Duplicate canonical URLs</dt><dd>{{ count($report['sitemap']['duplicate_canonical_urls'] ?? []) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Noindex URLs in sitemap</dt><dd>{{ count($report['sitemap']['noindex_urls'] ?? []) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Demo/outreach URLs in sitemap</dt><dd>{{ count($report['sitemap']['demo_outreach_urls'] ?? []) }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Niet eligible in sitemap</dt><dd>{{ count($report['sitemap']['not_eligible_urls'] ?? []) }}</dd></div>
                </dl>
            </div>

            <div class="{{ $card }}">
                <h3 class="mb-3 text-lg font-semibold">Structured data health</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt>WebPage schema</dt><dd>{{ $report['structured_data']['webpage_schema_pages'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Vehicle schema</dt><dd>{{ $report['structured_data']['vehicle_schema_pages'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Product schema</dt><dd>{{ $report['structured_data']['product_schema_pages'] ?? 0 }}</dd></div>
                </dl>
            </div>

            <div class="{{ $card }}">
                <h3 class="mb-3 text-lg font-semibold">Canonical health</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4"><dt>Canonical mismatches</dt><dd>{{ $report['canonical']['mismatches'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Duplicate canonicals</dt><dd>{{ $report['canonical']['duplicate_canonicals'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Querystring issues</dt><dd>{{ $report['canonical']['querystring_issues'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Host mismatches</dt><dd>{{ $report['canonical']['host_mismatches'] ?? 0 }}</dd></div>
                    <div class="flex justify-between gap-4"><dt>Redirect candidates</dt><dd>{{ $report['canonical']['redirect_candidates'] ?? 0 }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="{{ $card }}">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-lg font-semibold">Thin content / weak SEO pages</h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">Top {{ count($report['weak_pages'] ?? []) }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="py-2 pr-4">Voertuig</th>
                            <th class="py-2 pr-4">Slug</th>
                            <th class="py-2 pr-4">Eigenaar</th>
                            <th class="py-2 pr-4">Reden</th>
                            <th class="py-2 pr-4">Public URL</th>
                            <th class="py-2 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($report['weak_pages'] ?? [] as $row)
                            <tr>
                                <td class="py-3 pr-4 font-medium">{{ $row['vehicle'] }}</td>
                                <td class="py-3 pr-4">{{ $row['slug'] }}</td>
                                <td class="py-3 pr-4">{{ $row['owner'] }}</td>
                                <td class="py-3 pr-4">{{ $row['reason'] }}</td>
                                <td class="py-3 pr-4"><a class="text-primary-600" href="{{ $row['public_url'] }}" target="_blank" rel="noopener">{{ $row['public_url'] }}</a></td>
                                <td class="py-3 pr-4">{{ $row['status'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-gray-500" colspan="6">Geen weak pages gevonden.</td></tr>
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
</x-filament-panels::page>
