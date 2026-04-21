<x-filament::widget>
    <x-filament::card>
        <h2 style="font-size:20px; font-weight:700; margin-bottom:20px;">
            Toekomstig onderhoud
        </h2>

        <div style="display:flex; flex-direction:column; gap:12px;">
            @forelse($reminders as $reminder)
                <a href="/admin/maintenance-logs/{{ $reminder['log']->id }}/edit"
                   style="
                    padding:16px;
                    border-radius:12px;
                    background:#f9fafb;
                    border:1px solid #e5e7eb;
                    text-decoration:none;
                    display:block;
                   ">

                    <div style="font-weight:600;">
                        {{ $reminder['log']->vehicle->brand }} {{ $reminder['log']->vehicle->model }}
                    </div>

                    <div style="font-size:14px; color:#6b7280;">
                        {{ $reminder['log']->description }}
                    </div>

                    <div style="font-size:13px; margin-top:6px;">
                        @if($reminder['status']['type'] === 'overdue')
                            ⏰ {{ $reminder['status']['text'] }}
                        @else
                            📅 {{ $reminder['status']['text'] }}
                        @endif
                    </div>
                </a>
            @empty
                <div style="color:#9ca3af;">
                    Geen aankomende onderhoudsmomenten
                </div>
            @endforelse
        </div>
    </x-filament::card>
</x-filament::widget>
