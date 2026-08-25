<x-filament-widgets::widget>
    <x-filament::section>
        <div class="space-y-6">
            <div>
                <div style="max-width:42rem; min-width:0;">
                    <h2 class="text-lg font-bold mb-3"><strong>{{ $title }}</strong></h2>
                    <p style="margin:0; color:#64748b; line-height:1.7;">
                        {{ $description }}
                    </p>
                </div>
            </div>

            @if ($primaryAction)
                <a
                    href="{{ $primaryAction['url'] }}"
                    class="fi-btn fi-btn-color-warning rounded-xl px-5 py-4 shadow-sm"
                    @foreach ($primaryAction['attributes'] as $attribute => $value)
                        {{ $attribute }}="{{ $value }}"
                    @endforeach
                    style="display:flex; align-items:center; justify-content:center; min-height:72px; text-align:center; white-space:normal; overflow-wrap:anywhere; background-color:#ffd200; color:#111827;"
                >
                    {{ $primaryAction['label'] }}
                </a>
            @endif

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap:14px;">
                @foreach ($actions as $action)
                    <a
                        href="{{ $action['url'] }}"
                        class="fi-btn fi-btn-color-gray fi-btn-outlined rounded-xl px-5 py-4"
                        @foreach ($action['attributes'] as $attribute => $value)
                            {{ $attribute }}="{{ $value }}"
                        @endforeach
                        style="display:flex; align-items:center; justify-content:center; min-height:72px; text-align:center; white-space:normal; overflow-wrap:anywhere;"
                    >
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
