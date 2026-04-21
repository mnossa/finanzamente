@extends('layouts.guest')

@section('content')
    @if($article->is_draft)
        <div class="sticky top-16 sm:top-20 z-40 bg-amber-50 border-b border-amber-200 shadow-sm">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between gap-4">
                <p class="text-sm text-amber-800 font-medium flex items-center gap-2">
                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    Anteprima bozza — questo articolo non è ancora pubblicato e non è visibile al pubblico.
                </p>
                <a href="{{ route('admin.magazine.edit', $article) }}"
                   class="text-sm text-amber-700 hover:text-amber-900 underline decoration-dotted flex-shrink-0">
                    ← Torna alla modifica
                </a>
            </div>
        </div>
    @endif

    <!-- Cover -->
    @if($article->cover_image_url)
        <div class="w-full aspect-video sm:aspect-[21/7] overflow-hidden bg-surface-200 relative">
            <img src="{{ $article->cover_image_url }}"
                 alt="{{ $article->title }}"
                 class="w-full h-full object-cover"
                 loading="eager">
            @if($article->cover_image_credit)
                <p class="absolute bottom-1.5 right-2 text-white/60 hover:text-white/90 transition-colors"
                   style="font-size: 0.625rem; text-shadow: 0 1px 2px rgba(0,0,0,.6);">
                    @if($article->cover_image_credit_url)
                        <a href="{{ $article->cover_image_credit_url }}" target="_blank" rel="noopener nofollow"
                           class="underline decoration-dotted">{{ $article->cover_image_credit }}</a>
                    @else
                        {{ $article->cover_image_credit }}
                    @endif
                </p>
            @endif
        </div>
    @else
        <div class="w-full h-32 sm:h-48 bg-gradient-to-br from-primary-100 to-primary-200"></div>
    @endif

    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">

            <!-- Breadcrumb + meta -->
            <div class="pt-8 pb-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-surface-500">
                <a href="{{ route('magazine.index') }}" class="hover:text-primary-600 transition-colors">Magazine</a>
                <span aria-hidden="true">/</span>
                <a href="{{ route('magazine.category', $article->category->slug) }}"
                   class="inline-flex items-center gap-1.5 hover:text-primary-600 transition-colors">
                    <span class="w-2 h-2 rounded-full" style="background-color: {{ $article->category->color }}"></span>
                    {{ $article->category->name }}
                </a>
            </div>

            <!-- Titolo -->
            <h1 class="text-3xl sm:text-4xl font-bold text-surface-900 leading-tight mb-4">
                {{ $article->title }}
            </h1>


            <!-- Autore e data -->
            <div class="flex flex-wrap items-center gap-4 pb-4 border-b border-surface-200 text-sm text-surface-500">
                <span class="font-medium text-surface-700">{{ $article->author_name }}</span>
                @if($article->published_at)
                    <time datetime="{{ $article->published_at->toDateString() }}">
                        {{ $article->published_at->locale('it')->isoFormat('D MMMM YYYY') }}
                    </time>
                @else
                    <span class="italic text-amber-600">non ancora pubblicato</span>
                @endif
                <span>{{ $article->reading_time_minutes }} min di lettura</span>
                <span>{{ number_format($article->views_count) }} letture</span>
            </div>

            @include('magazine._share', ['article' => $article])

            <!-- Disclaimer finanziario (sempre presente) -->
            <div class="mt-4 mb-6 px-3 py-2.5 rounded-lg bg-surface-50 border border-surface-200 text-xs text-surface-400 leading-relaxed">
                <span class="font-semibold text-surface-500">Disclaimer:</span>
                Il presente articolo ha finalità puramente informative ed educative. Non costituisce consulenza finanziaria, sollecitazione al pubblico risparmio o consulenza fiscale. Le informazioni descritte devono essere adattate alla propria situazione personale, possibilmente con il supporto di professionisti abilitati.
            </div>


            <!-- Excerpt -->
            <p class="text-lg text-surface-600 leading-relaxed font-medium">
                {{ $article->excerpt }}
            </p>

            <!-- Separatore visivo -->
            <div class="my-6 border-t border-surface-200"></div>

            <!-- Contenuto Markdown → HTML -->
            <div class="prose prose-surface prose-lg max-w-none
                        prose-headings:font-bold prose-headings:text-surface-900
                        prose-strong:text-surface-900
                        prose-code:text-primary-700 prose-code:bg-primary-50 prose-code:px-1 prose-code:rounded
                        prose-blockquote:border-l-primary-400 prose-blockquote:text-surface-600">
                {!! $article->content_html !!}
            </div>

            <!-- Disclaimer finanziario — rimosso dal fondo, ora posizionato sopra il contenuto -->

            @if($article->is_ai_assisted)
            <!-- Nota AI -->
            <div class="mt-8 flex items-start gap-2 text-surface-400 leading-relaxed" style="font-size: 0.6875rem;">
                <svg class="flex-shrink-0 text-surface-300" style="width: 12px; height: 12px; margin-top: 1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <span>Testo redatto con il supporto di tecnologie di intelligenza artificiale sotto la supervisione e revisione editoriale dell'autore.</span>
            </div>
            @endif

            <!-- CTA -->
            <div class="mt-12 p-6 sm:p-8 rounded-2xl bg-gradient-to-br from-primary-50 to-accent-50 border border-primary-100 text-center">
                <h2 class="text-xl font-bold text-surface-900 mb-2">Gestisci il tuo denaro con Finanzamente</h2>
                <p class="text-surface-600 mb-4 text-sm">Dashboard personale, conti, budget e molto altro — gratis, senza compromessi sulla privacy.</p>
                <a href="{{ route('home') }}?utm_source=magazine&utm_medium=article_cta&utm_campaign={{ $article->slug }}"
                   class="inline-flex items-center px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors">
                    Scopri Finanzamente
                </a>
            </div>

            @include('magazine._share', ['article' => $article])

            <!-- Articoli correlati -->
            @if($related->isNotEmpty())
                <div class="mt-14">
                    <h3 class="text-xl font-bold text-surface-900 mb-6">Leggi anche</h3>
                    <div class="grid sm:grid-cols-3 gap-4">
                        @foreach($related as $rel)
                            @include('magazine._article-card', ['article' => $rel])
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mt-12 mb-8">
                <a href="{{ route('magazine.index') }}"
                   class="inline-flex items-center text-sm text-surface-500 hover:text-primary-600 transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Tutti gli articoli
                </a>
            </div>
        </div>
    </div>
@endsection
