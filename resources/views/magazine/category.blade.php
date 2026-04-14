@extends('layouts.guest')

@section('content')
    <!-- Hero categoria -->
    <section class="pt-24 sm:pt-32 pb-10 bg-gradient-to-br from-surface-50 via-white to-surface-100">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <a href="{{ route('magazine.index') }}" class="inline-flex items-center text-sm text-surface-500 hover:text-primary-600 mb-4 transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Tutti gli articoli
            </a>
            <div class="flex items-center gap-3 mb-3">
                <span class="w-4 h-4 rounded-full" style="background-color: {{ $category->color }}"></span>
                <h1 class="text-3xl sm:text-4xl font-bold text-surface-900">{{ $category->name }}</h1>
            </div>
            @if($category->description)
                <p class="text-lg text-surface-600 max-w-2xl">{{ $category->description }}</p>
            @endif
        </div>
    </section>

    <!-- Filtro categorie -->
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('magazine.index') }}"
               class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-medium border border-surface-200 text-surface-700 hover:border-surface-400 transition-colors">
                Tutti
            </a>
            @foreach($categories as $cat)
                <a href="{{ route('magazine.category', $cat->slug) }}"
                   class="inline-flex items-center px-4 py-1.5 rounded-full text-sm font-medium transition-colors
                          {{ $cat->id === $category->id
                              ? 'bg-surface-900 text-white'
                              : 'border border-surface-200 text-surface-700 hover:border-surface-400' }}">
                    <span class="w-2 h-2 rounded-full mr-2 shrink-0" style="background-color: {{ $cat->color }}"></span>
                    {{ $cat->name }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="container mx-auto px-4 sm:px-6 lg:px-8 pb-16">
        @if($articles->isNotEmpty())
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                @foreach($articles as $article)
                    @include('magazine._article-card', ['article' => $article])
                @endforeach
            </div>
            @if($articles->hasPages())
                <div class="flex justify-center">
                    {{ $articles->links() }}
                </div>
            @endif
        @else
            <div class="text-center py-20 text-surface-400">
                <p class="text-lg font-medium text-surface-600">Nessun articolo in questa categoria.</p>
                <a href="{{ route('magazine.index') }}" class="mt-3 inline-block text-sm text-primary-600 hover:underline">
                    Vai a tutti gli articoli
                </a>
            </div>
        @endif
    </div>
@endsection
