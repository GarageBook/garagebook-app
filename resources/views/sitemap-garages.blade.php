<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($vehicles as $vehicle)
    <url>
        <loc>{{ \App\Support\PublicSeoUrl::garage($vehicle->public_slug) }}</loc>
        <lastmod>{{ $vehicle->updated_at?->toAtomString() }}</lastmod>
    </url>
@endforeach
</urlset>
