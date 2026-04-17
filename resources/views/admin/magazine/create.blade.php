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
            autosave: { enabled: true, uniqueId: 'magazine-create', delay: 3000 },
            placeholder: '## Introduzione\n\nScrivi qui il corpo dell\'articolo...',
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

            <div class="flex items-center gap-4 mb-8">
                <a href="{{ route('admin.magazine.index') }}"
                   class="p-2 rounded-lg text-surface-500 hover:bg-surface-100 transition-colors" title="Indietro">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div>
                    <p class="text-sm text-surface-500 uppercase tracking-wider font-semibold">Admin · Magazine</p>
                    <h1 class="text-2xl font-bold text-surface-900">Nuovo articolo</h1>
                </div>
            </div>

            <form action="{{ route('admin.magazine.store') }}" method="POST" enctype="multipart/form-data" novalidate>
                @csrf
                @include('admin.magazine._form')
            </form>

        </div>
    </div>
@endsection
