@extends('layouts.public')

@section('title', $blog->title . ' - GarageBook')

@section('content')

<div class="gb-blog-detail">

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

</div>

@endsection
