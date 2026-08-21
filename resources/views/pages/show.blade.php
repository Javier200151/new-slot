@extends('layouts.metopas')

@section('title', $page->title)

@section('meta-description', $page->title . ' de Squad ALPHA.')

@section('body-class', 'public-page-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pages.css') }}">
@endpush

@section('content')
    <article class="public-page">
        <div class="container public-page__container">
            <nav class="public-page__breadcrumb" aria-label="Migas de pan">
                <a href="{{ route('home') }}">Inicio</a>
                <span aria-hidden="true">/</span>
                <span>{{ $page->title }}</span>
            </nav>

            <header class="public-page__header">
                <span>Squad ALPHA</span>
                <h1>{{ $page->title }}</h1>
            </header>

            <section class="public-page__content">
                {{ $content }}
            </section>
        </div>
    </article>
@endsection
