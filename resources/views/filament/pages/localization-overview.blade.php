<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-950">{{ __('app.locales.available_languages') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('app.locales.read_only_notice') }}</p>
                </div>
                <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">
                    {{ __('app.locales.read_only_badge') }}
                </span>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">{{ __('app.locales.columns.code') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">{{ __('app.locales.columns.name') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">{{ __('app.locales.columns.status') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">{{ __('app.locales.columns.default') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">{{ __('app.locales.columns.fallback') }}</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-700">{{ __('app.locales.columns.translation_count') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($localeSummaries as $locale)
                            <tr>
                                <td class="px-4 py-3 font-mono text-xs text-gray-800">{{ $locale['code'] }}</td>
                                <td class="px-4 py-3 text-gray-900">{{ $locale['native_name'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $locale['enabled'] ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $locale['enabled'] ? __('app.locales.status_active') : __('app.locales.status_inactive') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">
                                    {{ $locale['default'] ? __('app.locales.default_yes') : __('app.locales.default_no') }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $locale['fallback_locale'] }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ $locale['translation_count'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-sm font-medium text-gray-700">{{ __('app.locales.translation_files') }}</span>

                @forelse ($availableFiles as $file)
                    <button
                        type="button"
                        wire:click="$set('activeFile', '{{ $file }}')"
                        class="rounded-full px-3 py-1.5 text-xs font-medium transition {{ $activeFile === $file ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}"
                    >
                        {{ $file }}
                    </button>
                @empty
                    <span class="text-sm text-gray-500">{{ __('app.locales.no_file_selected') }}</span>
                @endforelse
            </div>

            @if ($activeFile)
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-700">{{ __('app.locales.columns.translation_key') }}</th>
                                @foreach ($localeSummaries as $locale)
                                    <th class="px-4 py-3 text-left font-medium text-gray-700">{{ $locale['code'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse ($translationRows as $row)
                                <tr>
                                    <td class="px-4 py-3 align-top font-mono text-xs text-gray-800">{{ $row['key'] }}</td>
                                    @foreach ($localeSummaries as $locale)
                                        @php($value = $row['values'][$locale['code']] ?? null)
                                        <td class="px-4 py-3 align-top text-gray-700">
                                            @if (filled($value))
                                                <div class="max-w-md whitespace-pre-wrap break-words">{{ $value }}</div>
                                            @else
                                                <span class="text-xs text-amber-700">{{ __('app.locales.translation_missing') }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($localeSummaries) + 1 }}" class="px-4 py-6 text-sm text-gray-500">
                                        {{ __('app.locales.no_translations_for_file') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
