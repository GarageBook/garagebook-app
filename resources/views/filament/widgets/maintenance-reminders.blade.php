<x-filament::widget>
    <x-filament::card>
        <h2 style="font-size:20px; font-weight:700; margin-bottom:20px;">
            {{ __('reminders.widget_heading') }}
        </h2>

        <div style="display:flex; flex-direction:column; gap:12px;">
            @forelse($reminders as $reminder)
                <a href="/admin/maintenance-logs/{{ $reminder['log']->id }}/edit"
                   style="
                    padding:15px 16px;
                    border-radius:12px;
                    background:#f9fafb;
                    border:1px solid #e5e7eb;
                    text-decoration:none;
                    display:block;
                   ">

                    <div style="font-size:15px; color:#111827; font-weight:700; line-height:1.3;">
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
                    Voeg eerst een voertuig en onderhoudslogs toe.
                </div>
            @endforelse
        </div>
    </x-filament::card>
</x-filament::widget>
