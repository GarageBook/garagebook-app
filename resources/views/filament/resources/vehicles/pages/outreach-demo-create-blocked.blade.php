<div class="mx-auto max-w-2xl rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <div class="space-y-5">
        <div class="space-y-2">
            <h2 class="text-xl font-semibold tracking-normal text-gray-950 dark:text-white">
                Leuk dat je een voertuig wilt toevoegen
            </h2>
            <p class="text-sm leading-6 text-gray-600 dark:text-gray-300">
                Je kijkt nu rond in een demo-account. Maak gratis je eigen GarageBook-account aan om je eigen voertuigen, onderhoud en documenten bij te houden.
            </p>
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <a
                href="{{ $registerUrl }}"
                @foreach ($analyticsAttributes as $attribute => $value)
                    {{ $attribute }}="{{ $value }}"
                @endforeach
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            >
                Gratis account aanmaken
            </a>

            <a
                href="{{ $backUrl }}"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
            >
                Terug naar demo
            </a>
        </div>
    </div>
</div>
