<x-filament::widget>
    <x-filament::card>
        <div style="display:flex; flex-wrap:wrap; align-items:flex-start; justify-content:space-between; gap:16px; margin-bottom:20px;">
            <div style="max-width:40rem; min-width:0;">
                <h2 style="font-size:20px; font-weight:700; margin:0 0 8px; overflow-wrap:anywhere;">
                    {{ __('reminders.widget_heading') }}
                </h2>
                <div style="color:#111827; font-weight:600; line-height:1.5;">
                    {{ $headline }}
                </div>
                <p style="margin:8px 0 0; color:#6b7280; line-height:1.6;">
                    {{ $supporting_text }}
                </p>
            </div>

            @if ($cta)
                <a
                    href="{{ $cta['url'] }}"
                    class="fi-btn fi-btn-color-gray fi-btn-outlined rounded-xl px-5 py-3"
                    style="display:inline-flex; align-items:center; justify-content:center; min-width:fit-content; text-align:center;"
                >
                    {{ $cta['label'] }}
                </a>
            @endif
        </div>

        <div style="display:flex; flex-direction:column; gap:12px;">
            @forelse($reminders as $reminder)
                <a href="{{ \App\Filament\Resources\MaintenanceLogs\MaintenanceLogResource::getUrl('edit', ['record' => $reminder['log']]) }}?with_reminder=1"
                   style="
                    padding:15px 16px;
                    min-width:0;
                    border-radius:12px;
                    background:#f9fafb;
                    border:1px solid #e5e7eb;
                    text-decoration:none;
                    display:block;
                   ">

                    <div style="font-size:15px; color:#111827; font-weight:700; line-height:1.3; overflow-wrap:anywhere;">
                        {{ $reminder['log']->vehicle->brand }} {{ $reminder['log']->vehicle->model }}: {{ $reminder['status']['heading'] }}
                    </div>

                    <div style="display:flex; align-items:flex-start; gap:6px; font-size:13px; color:#6b7280; margin-top:4px; line-height:1.35;">
                        <span style="font-weight:700; color:#94a3b8;">›</span>
                        <span>{{ $reminder['status']['text'] }}</span>
                    </div>
                </a>
            @empty
                <div style="color:#9ca3af; line-height:1.5;">
                    {{ __('reminders.empty_state') }}
                </div>
            @endforelse
        </div>
    </x-filament::card>
</x-filament::widget>
