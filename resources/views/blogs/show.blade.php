@extends('layouts.public')

@section('title', $blog->title . ' - GarageBook')
@section('meta_description', $blog->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($blog->rendered_content), 155))
@section('og_type', 'article')
@section('structured_data')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $blog->title,
            'description' => $blog->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($blog->rendered_content), 155),
            'url' => url('/blogs/' . $blog->slug),
            'datePublished' => optional($blog->published_at)->toAtomString(),
            'dateModified' => optional($blog->updated_at)->toAtomString(),
            'inLanguage' => 'nl-NL',
            'author' => [
                '@type' => 'Organization',
                'name' => 'GarageBook',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'GarageBook',
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('images/garagebook-logo.png'),
                ],
            ],
            'image' => $blog->hero_image ? [url('/blog-image/' . $blog->hero_image)] : null,
            'mainEntityOfPage' => url('/blogs/' . $blog->slug),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
    </script>
@endsection

@section('content')

<article class="gb-blog-detail">

    <a href="/blogs" class="gb-blog-detail__back">
        ← Terug naar blogs
    </a>

    <h1 class="gb-blog-detail__title">
        {{ $blog->title }}
    </h1>

    @if($blog->hero_image)
        <img
            src="/blog-image/{{ $blog->hero_image }}"
            class="gb-blog-detail__hero"
            alt="{{ $blog->title }}"
        >
    @endif

    <div class="gb-blog-detail__content">
        {!! $blog->rendered_content !!}
    </div>

</article>

@endsection
