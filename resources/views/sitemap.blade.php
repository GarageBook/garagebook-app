<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ \App\Support\PublicSeoUrl::root() }}</loc>
    </url>
    <url>
        <loc>{{ \App\Support\PublicSeoUrl::blogIndex() }}</loc>
    </url>
    @foreach($pages as $page)
    <url>
        <loc>{{ \App\Support\PublicSeoUrl::page($page->slug) }}</loc>
        <lastmod>{{ $page->updated_at?->toAtomString() }}</lastmod>
    </url>
    @endforeach
    @foreach($blogs as $blog)
    <url>
        <loc>{{ \App\Support\PublicSeoUrl::blog($blog->slug) }}</loc>
        <lastmod>{{ $blog->updated_at?->toAtomString() }}</lastmod>
    </url>
    @endforeach
</urlset>
