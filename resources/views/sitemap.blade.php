<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ url('/') }}</loc>
    </url>
    <url>
        <loc>{{ url('/blogs') }}</loc>
    </url>
    @foreach($pages as $page)
    <url>
        <loc>{{ url('/' . $page->slug) }}</loc>
        <lastmod>{{ $page->updated_at?->toAtomString() }}</lastmod>
    </url>
    @endforeach
    @foreach($blogs as $blog)
    <url>
        <loc>{{ url('/blogs/' . $blog->slug) }}</loc>
        <lastmod>{{ ($blog->updated_at ?? $blog->published_at)?->toAtomString() }}</lastmod>
    </url>
    @endforeach
</urlset>
