<div style="display:flex; flex-direction:column; gap:1rem; color:#0f172a;">
    <div><strong>Aantal geselecteerde prospects:</strong> {{ $count }}</div>
    <div><strong>Verzendbaar na controle:</strong> {{ $sendableCount }}</div>
    <div><strong>Onderwerp:</strong><br>{{ $subject }}</div>
    <div style="color:#475569;">
        {{ $trackingUrlNote }}
    </div>
    <div>
        <strong>Mailbody:</strong>
        <pre style="margin-top:.5rem; white-space:pre-wrap; background:#fffdf5; border:1px solid #e5e7eb; border-radius:12px; padding:1rem; font-family:inherit;">{{ $body }}</pre>
    </div>
    <div style="display:flex; flex-direction:column; gap:.25rem; color:#7c2d12;">
        <div><strong>Wordt overgeslagen:</strong></div>
        <div>Prospects zonder e-mailadres: {{ $warningWithoutEmail }}</div>
        <div>Archived prospects: {{ $warningArchived }}</div>
        <div>Prospects zonder tracking URL: {{ $warningWithoutTrackingUrl }}</div>
    </div>
    <div style="color:#b91c1c; font-weight:700;">Deze bulk actie verstuurt echte e-mails pas na bevestigen.</div>
</div>
