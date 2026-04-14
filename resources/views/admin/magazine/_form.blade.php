{{-- Partial condiviso tra create e edit --}}
@push('styles')
<style>
    /* Fullscreen: porta il container sopra la navbar fixed (z-50 = 50) */
    .EasyMDEContainer.fullscreen {
        z-index: 100 !important;
    }

    /* Toolbar sticky sotto la navbar quando si scrolla (non in fullscreen) */
    .EasyMDEContainer:not(.fullscreen) .editor-toolbar {
        position: sticky;
        top: 4rem; /* h-16 = 64px */
        z-index: 40;
        background: #ffffff;
    }
    @media (min-width: 640px) {
        .EasyMDEContainer:not(.fullscreen) .editor-toolbar {
            top: 5rem; /* h-20 = 80px */
        }
    }

    /* In fullscreen la toolbar è già in cima al container fixed */
    .EasyMDEContainer.fullscreen .editor-toolbar {
        position: sticky;
        top: 0;
    }
</style>
@endpush

<div class="grid lg:grid-cols-3 gap-8">

    <!-- Colonna principale -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Titolo -->
        <div>
            <label for="title" class="block text-sm font-medium text-surface-700 mb-1.5">Titolo <span class="text-red-500" aria-hidden="true">*</span></label>
            <input type="text" id="title" name="title"
                   value="{{ old('title', $article->title ?? '') }}"
                   class="w-full rounded-xl border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition"
                   required maxlength="255" placeholder="Es. Come costruire un fondo di emergenza in 6 mesi">
            @error('title')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Excerpt -->
        <div>
            <label for="excerpt" class="block text-sm font-medium text-surface-700 mb-1.5">
                Introduzione / Excerpt <span class="text-red-500" aria-hidden="true">*</span>
                <span class="text-surface-400 font-normal">(max 500 caratteri)</span>
            </label>
            <textarea id="excerpt" name="excerpt" rows="3"
                      class="w-full rounded-xl border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition resize-none"
                      required maxlength="500"
                      placeholder="Un paragrafo breve che riassume l'articolo e invoglia alla lettura.">{{ old('excerpt', $article->excerpt ?? '') }}</textarea>
            @error('excerpt')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Contenuto Markdown -->
        <div>
            <label for="content" class="block text-sm font-medium text-surface-700 mb-1.5">
                Contenuto <span class="text-red-500" aria-hidden="true">*</span>
            </label>
            <textarea id="content" name="content"
                      required
                      placeholder="## Introduzione&#10;&#10;Scrivi qui il corpo dell'articolo...">{{ old('content', $article->content ?? '') }}</textarea>
            @error('content')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

    </div>

    <!-- Colonna laterale -->
    <div class="space-y-6">

        <!-- Pubblica -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-4">
            <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">Pubblicazione</h3>

            <div>
                <label for="published_at" class="block text-sm font-medium text-surface-700 mb-1.5">
                    Data di pubblicazione
                    <span class="text-surface-400 font-normal">(vuoto = bozza)</span>
                </label>
                <input type="datetime-local" id="published_at" name="published_at"
                       value="{{ old('published_at', isset($article) && $article->published_at ? $article->published_at->format('Y-m-d\TH:i') : '') }}"
                       class="w-full rounded-xl border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition">
                @error('published_at')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="is_featured" value="1"
                       {{ old('is_featured', $article->is_featured ?? false) ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-surface-300 text-primary-600 focus:ring-primary-300">
                <span class="text-sm font-medium text-surface-700">In evidenza (hero in homepage)</span>
            </label>

            <button type="submit"
                    class="w-full py-2.5 bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold rounded-xl transition-colors">
                Salva articolo
            </button>
        </div>

        <!-- Categoria -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-3">
            <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">Categoria</h3>
            <div>
                <label for="category_id" class="sr-only">Categoria</label>
                <select id="category_id" name="category_id"
                        class="w-full rounded-xl border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition"
                        required>
                    <option value="">Seleziona una categoria</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}"
                                {{ old('category_id', $article->category_id ?? '') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Immagine di copertina -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-3">
            <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">Immagine di copertina</h3>
            <p class="text-xs text-surface-400">JPG, PNG o WebP — max 2 MB. Salvata nel volume persistente, non persa ai deploy.</p>

            @if(isset($article) && $article->cover_image_url)
                <div class="aspect-video rounded-xl overflow-hidden bg-surface-100">
                    <img src="{{ $article->cover_image_url }}" alt="Copertina attuale" class="w-full h-full object-cover">
                </div>
                <p class="text-xs text-surface-400">Carica una nuova immagine per sostituire quella attuale.</p>
            @endif

            <div>
                <label for="cover_image" class="sr-only">Immagine di copertina</label>
                <input type="file" id="cover_image" name="cover_image"
                       accept="image/jpeg,image/jpg,image/png,image/webp"
                       class="w-full text-sm text-surface-600 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100">
                @error('cover_image')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- Autore -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-3">
            <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">Autore</h3>
            <div>
                <label for="author_name" class="sr-only">Nome autore</label>
                <input type="text" id="author_name" name="author_name"
                       value="{{ old('author_name', $article->author_name ?? 'Redazione Finanzamente') }}"
                       class="w-full rounded-xl border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition"
                       required maxlength="100">
                @error('author_name')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <!-- SEO -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-4">
            <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">SEO</h3>

            <div>
                <label for="meta_title" class="block text-xs font-medium text-surface-600 mb-1">
                    Meta title <span class="text-surface-400">(max 70 car.)</span>
                </label>
                <input type="text" id="meta_title" name="meta_title"
                       value="{{ old('meta_title', $article->meta_title ?? '') }}"
                       class="w-full rounded-xl border border-surface-300 px-3 py-2 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition"
                       maxlength="70" placeholder="Lascia vuoto per usare il titolo">
                @error('meta_title')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="meta_description" class="block text-xs font-medium text-surface-600 mb-1">
                    Meta description <span class="text-surface-400">(max 160 car.)</span>
                </label>
                <textarea id="meta_description" name="meta_description" rows="3"
                          class="w-full rounded-xl border border-surface-300 px-3 py-2 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition resize-none"
                          maxlength="160" placeholder="Lascia vuoto per usare l'excerpt">{{ old('meta_description', $article->meta_description ?? '') }}</textarea>
                @error('meta_description')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

    </div>
</div>
