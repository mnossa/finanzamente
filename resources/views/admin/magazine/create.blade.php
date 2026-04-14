@extends('layouts.guest')

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
