@extends('layouts.guest')

@section('content')
    <div class="pt-24 sm:pt-32 pb-16">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">

            <div class="flex items-center justify-between mb-8">
                <div>
                    <p class="text-sm text-surface-500 uppercase tracking-wider font-semibold mb-1">Admin</p>
                    <h1 class="text-2xl font-bold text-surface-900">Articoli Magazine</h1>
                </div>
                <a href="{{ route('admin.magazine.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Nuovo articolo
                </a>
            </div>

            <div class="bg-white rounded-2xl border border-surface-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-surface-50 border-b border-surface-200">
                        <tr>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600">Titolo</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600 hidden sm:table-cell">Categoria</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600 hidden md:table-cell">Stato</th>
                            <th class="text-left px-5 py-3 font-semibold text-surface-600 hidden md:table-cell">Visite</th>
                            <th class="text-right px-5 py-3 font-semibold text-surface-600">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-100">
                        @forelse($articles as $article)
                            <tr class="hover:bg-surface-50 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-surface-900 line-clamp-1">{{ $article->title }}</div>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        @if($article->is_featured)
                                            <span class="text-xs text-accent-600 font-medium">★ In evidenza</span>
                                        @endif
                                        @if($article->is_ai_assisted)
                                            <span class="inline-flex items-center gap-0.5 text-xs text-violet-600 font-medium">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                          d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                                </svg>
                                                AI
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-5 py-4 hidden sm:table-cell">
                                    <span class="inline-flex items-center gap-1.5 text-surface-600">
                                        <span class="w-2 h-2 rounded-full" style="background-color: {{ $article->category->color }}"></span>
                                        {{ $article->category->name }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell">
                                    @if($article->is_draft)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-600">Bozza</span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-medium bg-accent-50 text-accent-700">
                                            {{ $article->published_at->locale('it')->isoFormat('D MMM YYYY') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell text-surface-500">
                                    {{ number_format($article->views_count) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if(!$article->is_draft)
                                            <a href="{{ route('magazine.show', $article->slug) }}"
                                               target="_blank"
                                               class="p-1.5 rounded-lg text-surface-400 hover:text-primary-600 hover:bg-primary-50 transition-colors"
                                               title="Visualizza">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                                </svg>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.magazine.edit', $article) }}"
                                           class="p-1.5 rounded-lg text-surface-400 hover:text-primary-600 hover:bg-primary-50 transition-colors"
                                           title="Modifica">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                        <form action="{{ route('admin.magazine.destroy', $article) }}" method="POST"
                                              onsubmit="return confirm('Eliminare definitivamente \'{{ addslashes($article->title) }}\'?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="p-1.5 rounded-lg text-surface-400 hover:text-red-600 hover:bg-red-50 transition-colors"
                                                    title="Elimina">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-12 text-center text-surface-400">
                                    Nessun articolo. <a href="{{ route('admin.magazine.create') }}" class="text-primary-600 hover:underline">Crea il primo</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                @if($articles->hasPages())
                    <div class="px-5 py-4 border-t border-surface-100">
                        {{ $articles->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
