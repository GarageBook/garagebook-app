<x-filament-panels::page>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="rounded-xl bg-gray-900 p-6 shadow">
            <div class="text-xl font-bold">
                Welkom terug, {{ auth()->user()->name }}
            </div>
            <div class="text-sm text-gray-400 mt-1">
                
            </div>
        </div>
    </div>
</x-filament-panels::page>
