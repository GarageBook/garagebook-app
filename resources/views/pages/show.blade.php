@extends('layouts.public')

@section('title', $page->title)

@section('content')

<div class="gb-content-shell">

    <h1 class="gb-page-title">
        {{ $page->title }}
    </h1>

    @if($page->hero_image)
        <img
            src="{{ asset('storage/' . $page->hero_image) }}"
            alt="{{ $page->title }}"
            class="gb-hero"
        >
    @endif

    <div>
        {!! $page->content !!}
    </div>

</div>

@endsection
