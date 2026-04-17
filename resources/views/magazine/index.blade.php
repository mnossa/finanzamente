@extends('layouts.guest')

@section('content')
    <!-- Hero Magazine -->
    <section class="pt-24 sm:pt-32 pb-10 bg-gradient-to-br from-primary-50 via-white to-accent-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold text-primary-600 uppercase tracking-wider mb-2">Magazine</p>
                <h1 class="text-3xl sm:text-4xl font-bold text-surface-900 mb-4 leading-tight">
                    Guide pratiche sulla<br>
                    <span class="bg-gradient-to-r from-primary-600 to-primary-800 bg-clip-text text-transparent">finanza personale</span>
                </h1>
                <p class="text-lg text-surface-600">
                    Articoli pensati per aiutarti a capire il denaro, risparmiare di più e investire con consapevolezza. In italiano, senza tecnicismi inutili.
                </p>
            </div>
        </div>
    </section>

    <!-- Categorie -->
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('magazine.index') }}"
               class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-medium bg-surface-900 text-white">
                Tutti
            </a>
            @foreach($categories as $cat)
                <a href="{{ route('magazine.category', $cat->slug) }}"
                   class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-medium border border-surface-200 text-surface-700 hover:border-surface-400 transition-colors">
                    <span class="w-2 h-2 rounded-full mr-2 shrink-0" style="background-color: {{ $cat->color }}"></span>
                    {{ $cat->name }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 pb-16">

        @if($featured)
            <!-- Articolo in evidenza -->
            <div class="mb-12">
                <a href="{{ route('magazine.show', $featured->slug) }}" class="group block">
                    <div class="grid md:grid-cols-2 gap-0 rounded-2xl overflow-hidden shadow-sm border border-surface-200 hover:shadow-md transition-shadow">
                        @if($featured->cover_image_url)
                            <div class="aspect-video md:aspect-auto overflow-hidden">
                                <img src="{{ $featured->cover_image_url }}"
                                     alt="{{ $featured->title }}"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                     loading="lazy">
                            </div>
                        @else
                            <div class="aspect-video md:aspect-auto bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center">
                                <svg class="w-16 h-16 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                                </svg>
                            </div>
                        @endif
                        <div class="p-6 sm:p-8 bg-white flex flex-col justify-center">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="w-2.5 h-2.5 rounded-full" style="background-color: {{ $featured->category->color }}"></span>
                                <span class="text-xs font-semibold uppercase tracking-wider text-surface-500">{{ $featured->category->name }}</span>
                                <span class="text-xs text-surface-400">·</span>
                                <span class="text-xs text-surface-400">{{ $featured->reading_time_minutes }} min</span>
                            </div>
                            <h2 class="text-2xl sm:text-3xl font-bold text-surface-900 mb-3 group-hover:text-primary-700 transition-colors leading-snug">
                                {{ $featured->title }}
                            </h2>
                            <p class="text-surface-600 mb-4 leading-relaxed">{{ $featured->excerpt }}</p>
                            <div class="flex items-center justify-between text-sm text-surface-500">
                                <span>{{ $featured->author_name }}</span>
                                <span>{{ $featured->published_at->locale('it')->isoFormat('D MMM YYYY') }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endif

        @if($articles->isNotEmpty())
            <!-- Griglia articoli -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                @foreach($articles as $article)
                    @include('magazine._article-card', ['article' => $article])
                @endforeach
            </div>

            <!-- Paginazione -->
            @if($articles->hasPages())
                <div class="flex justify-center">
                    {{ $articles->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-20 text-surface-400">
                <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-lg font-medium text-surface-600">Nessun articolo ancora pubblicato.</p>
                <p class="text-sm mt-1">Torna presto — il primo numero è in arrivo!</p>
            </div>
        @endif
    </div>
@endsection
