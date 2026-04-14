@extends('layouts.guest')

@section('content')
    <div class="pt-24 sm:pt-32 pb-16">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-4">
                    <a href="{{ route('admin.magazine.index') }}"
                       class="p-2 rounded-lg text-surface-500 hover:bg-surface-100 transition-colors" title="Indietro">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <div>
                        <p class="text-sm text-surface-500 uppercase tracking-wider font-semibold">Admin · Magazine</p>
                        <h1 class="text-2xl font-bold text-surface-900">Modifica articolo</h1>
                    </div>
                </div>
                @if(!$article->is_draft)
                    <a href="{{ route('magazine.show', $article->slug) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-sm text-primary-600 hover:underline">
                        Visualizza sul sito
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                @endif
            </div>

            <form action="{{ route('admin.magazine.update', $article) }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf
                @method('PUT')
                @include('admin.magazine._form')
            </form>

        </div>
    </div>
@endsection
