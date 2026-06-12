<div class="space-y-4">
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
        <p><strong>Previewcontext:</strong> {{ $previewUser->email }}</p>
        <p><strong>CTA-bestemming:</strong> {{ $ctaDestination }}</p>
        @if ($usesGreetingFallback)
            <p><strong>Fallback actief:</strong> geen voornaam gevonden, de mail toont "Hoi,".</p>
        @endif
    </div>

    <div style="background:#f8fafc; padding:24px 12px; border-radius:16px;">
        @include('emails.partials.lifecycle-card', [
            'template' => $template,
            'bodyHtml' => $bodyHtml,
            'ctaUrl' => $ctaUrl,
            'unsubscribeUrl' => $unsubscribeUrl,
        ])
    </div>
</div>
