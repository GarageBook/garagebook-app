<div style="font-family: Arial, sans-serif; line-height: 1.6; color: #111827;">
    @foreach (preg_split('/\r\n|\r|\n/', $bodyText) as $line)
        @if ($line === '')
            <br>
        @else
            <p style="margin: 0 0 12px 0;">{{ $line }}</p>
        @endif
    @endforeach
</div>
