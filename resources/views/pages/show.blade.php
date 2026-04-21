@extends('layouts.public')

@section('title', $page->title)

@section('content')

<div class="gb-content-shell">

    <h1 class="gb-page-title">
        {{ $page->title }}
    </h1>

    <div>
        {!! $page->content !!}
    </div>

</div>

@endsection
