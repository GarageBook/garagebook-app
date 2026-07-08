<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($slugs as $slug)
    <url>
        <loc>{{ url('/onderhoud/' . $slug) }}</loc>
    </url>
@endforeach
</urlset>
