@extends('layouts.guest')

@push('styles')
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/easymde@2.20.0/dist/easymde.min.css"
          integrity="sha384-3AvV7152TgYAMYdGZPqG9BpmSH2ZW6ewTDL0QV5PyNkl19KMI+yLMdJz183N8A2d"
          crossorigin="anonymous">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/easymde@2.20.0/dist/easymde.min.js"
            integrity="sha384-YDXeUfPZ4SP6vJpnF+ZMmf4B1bax6yd4Q/aNbkvLidRD843hPG5RE67M0IYT4LOq"
            crossorigin="anonymous"></script>
    <script>
        const easyMDE = new EasyMDE({
            element: document.getElementById('content'),
            spellChecker: false,
            autosave: { enabled: true, uniqueId: 'magazine-edit-{{ $article->id }}', delay: 3000 },
            toolbar: [
                'bold', 'italic', 'heading', '|',
                'quote', 'unordered-list', 'ordered-list', '|',
                'link', 'image', '|',
                'preview', 'side-by-side', 'fullscreen', '|',
                'guide'
            ],
            minHeight: '500px',
        });
    </script>
@endpush

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
                @else
                    <a href="{{ route('admin.magazine.preview', $article) }}" target="_blank"
                       class="inline-flex items-center gap-2 text-sm font-medium px-3 py-1.5 rounded-lg bg-surface-100 text-surface-600 hover:bg-surface-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Anteprima
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
