<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach($models as $model)
    <url>
        <loc>{{ url('/onderhoud/' . $model->slug) }}</loc>
        @if($model->updated_at)
        <lastmod>{{ $model->updated_at->toAtomString() }}</lastmod>
        @endif
    </url>
@endforeach
</urlset>
