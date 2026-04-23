{{-- Partial condiviso tra create e edit --}}
@push('styles')
<style>
    /* Fullscreen: porta il container sopra la navbar fixed */
    .EasyMDEContainer.fullscreen { z-index: 100 !important; }

    /* Toolbar sticky sotto la navbar quando si scrolla (non in fullscreen) */
    .EasyMDEContainer:not(.fullscreen) .editor-toolbar {
        position: sticky;
        top: 4rem;
        z-index: 40;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-bottom: none;
        border-radius: 0.75rem 0.75rem 0 0;
        padding: 6px 8px;
    }
    @media (min-width: 640px) {
        .EasyMDEContainer:not(.fullscreen) .editor-toolbar { top: 5rem; }
    }
    .EasyMDEContainer.fullscreen .editor-toolbar { position: sticky; top: 0; }

    /* Editor area */
    .EasyMDEContainer:not(.fullscreen) .CodeMirror {
        border-radius: 0 0 0.75rem 0.75rem;
        border: 1px solid #e2e8f0;
        border-top: none;
        font-size: 0.9375rem;
        line-height: 1.7;
        padding: 1rem;
        min-height: 500px;
    }

    /* Sidebar sticky */
    .admin-sidebar { position: sticky; top: 5.5rem; }
    @media (max-width: 1023px) { .admin-sidebar { position: static; } }

    /* Toggle switch */
    .toggle-input:checked + .toggle-track { background-color: #4F46E5; }
    .toggle-input:checked + .toggle-track .toggle-thumb { transform: translateX(1.25rem); }
    .toggle-input:focus + .toggle-track { box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2); }

    /* Character counter */
    .char-counter.warning { color: #d97706; }
    .char-counter.danger  { color: #dc2626; }
</style>
@endpush

<div class="grid lg:grid-cols-3 gap-8 items-start">

    <!-- ══ Colonna principale ══════════════════════════════════════════════ -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Titolo -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <div class="flex items-center justify-between mb-1.5">
                <label for="title" class="block text-sm font-semibold text-surface-800">
                    Titolo <span class="text-red-500 ml-0.5" aria-hidden="true">*</span>
                </label>
                <span id="title-counter" class="text-xs text-surface-400 char-counter tabular-nums">0 / 255</span>
            </div>
            <input type="text" id="title" name="title"
                   value="{{ old('title', $article->title ?? '') }}"
                   class="w-full rounded-xl border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition"
                   required maxlength="255"
                   placeholder="Es. Come costruire un fondo di emergenza in 6 mesi"
                   oninput="updateCounter('title', 255)">
            @error('title')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Excerpt -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <div class="flex items-center justify-between mb-1.5">
                <label for="excerpt" class="block text-sm font-semibold text-surface-800">
                    Introduzione / Excerpt <span class="text-red-500 ml-0.5" aria-hidden="true">*</span>
                </label>
                <span id="excerpt-counter" class="text-xs text-surface-400 char-counter tabular-nums">0 / 500</span>
            </div>
            <p class="text-xs text-surface-400 mb-2">Breve paragrafo mostrato nelle anteprime e nei risultati di ricerca.</p>
            <textarea id="excerpt" name="excerpt" rows="3"
                      class="w-full rounded-xl border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition resize-none"
                      required maxlength="500"
                      placeholder="Un paragrafo breve che riassume l'articolo e invoglia alla lettura."
                      oninput="updateCounter('excerpt', 500)">{{ old('excerpt', $article->excerpt ?? '') }}</textarea>
            @error('excerpt')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- Contenuto Markdown -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5">
            <label for="content" class="block text-sm font-semibold text-surface-800 mb-3">
                Contenuto <span class="text-red-500 ml-0.5" aria-hidden="true">*</span>
                <span class="text-xs text-surface-400 font-normal ml-1">— Markdown supportato</span>
            </label>
            <textarea id="content" name="content"
                      required
                      placeholder="## Introduzione&#10;&#10;Scrivi qui il corpo dell'articolo...">{{ old('content', $article->content ?? '') }}</textarea>
            @error('content')
                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <!-- SEO -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-4">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
                </svg>
                <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">SEO</h3>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="meta_title" class="block text-xs font-medium text-surface-600">Meta title</label>
                    <span id="meta_title-counter" class="text-xs text-surface-400 char-counter tabular-nums">0 / 70</span>
                </div>
                <input type="text" id="meta_title" name="meta_title"
                       value="{{ old('meta_title', $article->meta_title ?? '') }}"
                       class="w-full rounded-xl border border-surface-300 px-3 py-2 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition"
                       maxlength="70" placeholder="Lascia vuoto per usare il titolo dell'articolo"
                       oninput="updateCounter('meta_title', 70)">
                @error('meta_title')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="meta_description" class="block text-xs font-medium text-surface-600">Meta description</label>
                    <span id="meta_description-counter" class="text-xs text-surface-400 char-counter tabular-nums">0 / 160</span>
                </div>
                <textarea id="meta_description" name="meta_description" rows="2"
                          class="w-full rounded-xl border border-surface-300 px-3 py-2 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition resize-none"
                          maxlength="160" placeholder="Lascia vuoto per usare l'excerpt"
                          oninput="updateCounter('meta_description', 160)">{{ old('meta_description', $article->meta_description ?? '') }}</textarea>
                @error('meta_description')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
        </div>

    </div>

    <!-- ══ Colonna laterale (sticky) ══════════════════════════════════════ -->
    <div class="admin-sidebar space-y-5">

        <!-- Pubblica -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-4">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">Pubblicazione</h3>
            </div>

            <div>
                <label for="published_at" class="block text-xs font-medium text-surface-600 mb-1.5">
                    Data di pubblicazione
                    <span class="text-surface-400 font-normal">— vuoto = bozza</span>
                </label>
                <input type="datetime-local" id="published_at" name="published_at"
                       value="{{ old('published_at', isset($article) && $article->published_at ? $article->published_at->format('Y-m-d\TH:i') : '') }}"
                       class="w-full rounded-xl border border-surface-300 px-3 py-2 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition">
                @error('published_at')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Toggle: In evidenza -->
            <label class="flex items-center justify-between gap-3 cursor-pointer">
                <div>
                    <span class="text-sm font-medium text-surface-700">In evidenza</span>
                    <p class="text-xs text-surface-400 mt-0.5">Mostrato come hero in homepage</p>
                </div>
                <div class="relative flex-shrink-0">
                    <input type="checkbox" name="is_featured" value="1"
                           id="toggle-is_featured"
                           {{ old('is_featured', $article->is_featured ?? false) ? 'checked' : '' }}
                           class="toggle-input sr-only">
                    <div class="toggle-track w-10 h-6 bg-surface-200 rounded-full transition-colors duration-200 ease-in-out cursor-pointer flex items-center px-0.5"
                         onclick="document.getElementById('toggle-is_featured').click()">
                        <div class="toggle-thumb w-5 h-5 bg-white rounded-full shadow-sm transition-transform duration-200 ease-in-out"></div>
                    </div>
                </div>
            </label>

            <hr class="border-surface-100">

            <button type="submit"
                    class="w-full flex items-center justify-center gap-2 py-2.5 bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white text-sm font-semibold rounded-xl transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Salva articolo
            </button>
        </div>

        <!-- Categoria -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-3">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M7 7h.01M7 3h5l7 7-7 7-5-5V3z"/>
                </svg>
                <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">Categoria</h3>
            </div>
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
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">Copertina</h3>
            </div>

            {{-- Anteprima immagine corrente --}}
            <div id="cover-preview-wrap" class="{{ (isset($article) && $article->cover_image_url) ? '' : 'hidden' }}">
                <div class="aspect-video rounded-xl overflow-hidden bg-surface-100 border border-surface-200">
                    <img id="cover-preview-img"
                         src="{{ isset($article) ? ($article->cover_image_url ?? '') : '' }}"
                         alt="Anteprima copertina"
                         class="w-full h-full object-cover">
                </div>
                <p id="cover-preview-credit" class="mt-1 text-xs text-surface-400 italic">
                    {{ isset($article) ? $article->cover_image_credit : '' }}
                </p>
            </div>

            {{-- Campi nascosti per immagine Unsplash selezionata --}}
            <input type="hidden" name="unsplash_photo_url"    id="unsplash_photo_url"    value="">
            <input type="hidden" name="unsplash_photo_credit" id="unsplash_photo_credit" value="">
            <input type="hidden" name="unsplash_author_url"   id="unsplash_author_url"   value="">

            {{-- Tab switcher --}}
            <div class="flex rounded-lg border border-surface-200 overflow-hidden text-xs font-medium" role="tablist">
                <button type="button" id="tab-upload" role="tab" aria-selected="true" aria-controls="panel-upload"
                        class="flex-1 py-1.5 bg-primary-50 text-primary-700 transition-colors"
                        onclick="switchCoverTab('upload')">
                    Carica file
                </button>
                <button type="button" id="tab-unsplash" role="tab" aria-selected="false" aria-controls="panel-unsplash"
                        class="flex-1 py-1.5 text-surface-500 hover:bg-surface-50 transition-colors"
                        onclick="switchCoverTab('unsplash')">
                    Cerca su Unsplash
                </button>
            </div>

            {{-- Panel: Upload --}}
            <div id="panel-upload" role="tabpanel">
                <label for="cover_image" class="sr-only">Immagine di copertina</label>
                <input type="file" id="cover_image" name="cover_image"
                       accept="image/jpeg,image/jpg,image/png,image/webp"
                       class="w-full text-sm text-surface-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 transition"
                       onchange="onFileSelected(this)">
                <p class="mt-1.5 text-xs text-surface-400">JPG, PNG o WebP — max 2 MB</p>
                @error('cover_image')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Panel: Unsplash search --}}
            <div id="panel-unsplash" role="tabpanel" class="hidden space-y-3">
                <div class="flex gap-2">
                    <input type="text" id="unsplash-query" placeholder="Es. risparmio, investimenti, budget…"
                           class="flex-1 rounded-xl border border-surface-300 px-3 py-2 text-sm focus:border-primary-400 focus:ring-2 focus:ring-primary-100 outline-none transition"
                           onkeydown="if(event.key==='Enter'){event.preventDefault();searchUnsplash();}">
                    <button type="button" onclick="searchUnsplash()"
                            class="px-3 py-2 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded-xl transition-colors whitespace-nowrap">
                        Cerca
                    </button>
                </div>

                <div id="unsplash-status" class="text-xs text-surface-400 hidden"></div>

                <div id="unsplash-grid" class="grid grid-cols-3 gap-1.5 hidden"></div>

                <p class="text-[10px] text-surface-300 leading-relaxed">
                    Immagini fornite da <a href="https://unsplash.com/?utm_source=finanzamente&utm_medium=referral" target="_blank" rel="noopener" class="underline hover:text-surface-400">Unsplash</a>.
                    Selezionando un'immagine accetti i <a href="https://unsplash.com/license" target="_blank" rel="noopener" class="underline hover:text-surface-400">termini di licenza</a>.
                </p>
            </div>
        </div>

        <!-- Autore -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-3">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">Autore</h3>
            </div>
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

        <!-- Opzioni contenuto -->
        <div class="bg-white rounded-2xl border border-surface-200 p-5 space-y-4">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-surface-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <h3 class="font-semibold text-surface-800 text-sm uppercase tracking-wide">Opzioni contenuto</h3>
            </div>

            <!-- Toggle: Redatto con AI -->
            <label class="flex items-center justify-between gap-3 cursor-pointer">
                <div>
                    <span class="text-sm font-medium text-surface-700">Redatto con AI</span>
                    <p class="text-xs text-surface-400 mt-0.5">Aggiunge la nota "redatto con AI" in fondo all'articolo</p>
                </div>
                <div class="relative flex-shrink-0">
                    <input type="hidden" name="is_ai_assisted" value="0">
                    <input type="checkbox" name="is_ai_assisted" value="1"
                           id="toggle-is_ai_assisted"
                           {{ old('is_ai_assisted', $article->is_ai_assisted ?? false) ? 'checked' : '' }}
                           class="toggle-input sr-only">
                    <div class="toggle-track w-10 h-6 bg-surface-200 rounded-full transition-colors duration-200 ease-in-out cursor-pointer flex items-center px-0.5"
                         onclick="document.getElementById('toggle-is_ai_assisted').click()">
                        <div class="toggle-thumb w-5 h-5 bg-white rounded-full shadow-sm transition-transform duration-200 ease-in-out"></div>
                    </div>
                </div>
            </label>

            <!-- Nota: articoli correlati automatici -->
            <div class="pt-2 border-t border-surface-100 text-xs text-surface-400 leading-relaxed">
                <span class="font-medium text-surface-500">Articoli correlati:</span>
                vengono mostrati automaticamente in fondo all'articolo selezionando fino a 3 articoli pubblicati nella stessa categoria. Pubblica altri articoli nella stessa categoria per vederli apparire.
            </div>
        </div>

    </div>
</div>

@push('scripts')
<script>
    // ── Character counters ──────────────────────────────────────────────────
    function updateCounter(fieldId, max) {
        const field = document.getElementById(fieldId);
        const counter = document.getElementById(fieldId + '-counter');
        if (!field || !counter) return;
        const len = field.value.length;
        counter.textContent = len + ' / ' + max;
        counter.classList.remove('warning', 'danger');
        if (len > max * 0.9)       counter.classList.add('danger');
        else if (len > max * 0.75) counter.classList.add('warning');
    }

    document.addEventListener('DOMContentLoaded', function () {
        ['title', 'excerpt', 'meta_title', 'meta_description'].forEach(function (id) {
            const field = document.getElementById(id);
            if (field) updateCounter(id, parseInt(field.getAttribute('maxlength')));
        });
    });

    // ── Cover image tabs ────────────────────────────────────────────────────
    function switchCoverTab(tab) {
        const isUpload = tab === 'upload';

        document.getElementById('panel-upload').classList.toggle('hidden', !isUpload);
        document.getElementById('panel-unsplash').classList.toggle('hidden', isUpload);

        const btnUpload   = document.getElementById('tab-upload');
        const btnUnsplash = document.getElementById('tab-unsplash');

        btnUpload.classList.toggle('bg-primary-50',   isUpload);
        btnUpload.classList.toggle('text-primary-700', isUpload);
        btnUpload.classList.toggle('text-surface-500', !isUpload);
        btnUpload.setAttribute('aria-selected', isUpload ? 'true' : 'false');

        btnUnsplash.classList.toggle('bg-primary-50',   !isUpload);
        btnUnsplash.classList.toggle('text-primary-700', !isUpload);
        btnUnsplash.classList.toggle('text-surface-500', isUpload);
        btnUnsplash.setAttribute('aria-selected', isUpload ? 'false' : 'true');

        // Se torno all'upload, pulisco la selezione Unsplash
        if (isUpload) clearUnsplashSelection();
    }

    function onFileSelected(input) {
        if (input.files && input.files[0]) {
            const url = URL.createObjectURL(input.files[0]);
            showCoverPreview(url, '');
        }
        clearUnsplashSelection();
    }

    // ── Unsplash search ─────────────────────────────────────────────────────
    let unsplashCurrentPage = 1;
    let unsplashCurrentQuery = '';

    function searchUnsplash(page) {
        const q = document.getElementById('unsplash-query').value.trim();
        if (!q) return;

        const isNewSearch = !page || q !== unsplashCurrentQuery;
        const targetPage  = isNewSearch ? 1 : page;

        const status  = document.getElementById('unsplash-status');
        const grid    = document.getElementById('unsplash-grid');
        const loadBtn = document.getElementById('unsplash-load-more');

        status.textContent = 'Ricerca in corso…';
        status.classList.remove('hidden');

        if (isNewSearch) {
            grid.innerHTML = '';
            grid.classList.add('hidden');
            if (loadBtn) loadBtn.remove();
        }

        fetch('{{ route('admin.magazine.unsplash-search') }}?q=' + encodeURIComponent(q) + '&page=' + targetPage, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                status.textContent = data.error;
                return;
            }
            if (!data.results || data.results.length === 0) {
                status.textContent = 'Nessun risultato. Prova con un termine diverso.';
                return;
            }

            status.classList.add('hidden');
            grid.classList.remove('hidden');

            unsplashCurrentQuery = q;
            unsplashCurrentPage  = data.current_page;

            data.results.forEach(photo => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'relative aspect-video rounded-lg overflow-hidden border-2 border-transparent hover:border-primary-400 transition focus:outline-none focus:border-primary-500';
                btn.setAttribute('aria-label', photo.description || photo.credit);
                btn.innerHTML = `<img src="${photo.thumb}" alt="${photo.description || ''}" class="w-full h-full object-cover" loading="lazy">`;
                btn.onclick = () => selectUnsplashPhoto(photo, btn);
                grid.appendChild(btn);
            });

            // Rimuovi pulsante precedente e ricrea se ci sono altre pagine
            const existingBtn = document.getElementById('unsplash-load-more');
            if (existingBtn) existingBtn.remove();

            if (data.has_more) {
                const more = document.createElement('button');
                more.type = 'button';
                more.id = 'unsplash-load-more';
                more.className = 'col-span-3 mt-1 py-2 text-xs font-semibold text-primary-600 hover:text-primary-700 border border-primary-200 hover:border-primary-400 rounded-xl transition-colors';
                more.textContent = 'Carica altre immagini…';
                more.onclick = () => searchUnsplash(unsplashCurrentPage + 1);
                grid.parentElement.insertBefore(more, grid.nextSibling);
            }
        })
        .catch(() => {
            status.classList.remove('hidden');
            status.textContent = 'Errore di rete. Riprova.';
        });
    }

    function selectUnsplashPhoto(photo, btn) {
        // Deseleziona eventuali precedenti
        document.querySelectorAll('#unsplash-grid button').forEach(b => {
            b.classList.remove('border-primary-500', 'ring-2', 'ring-primary-300');
            b.classList.add('border-transparent');
        });
        btn.classList.remove('border-transparent');
        btn.classList.add('border-primary-500', 'ring-2', 'ring-primary-300');

        document.getElementById('unsplash_photo_url').value    = photo.full;
        document.getElementById('unsplash_photo_credit').value = photo.credit;
        document.getElementById('unsplash_author_url').value   = photo.author_url;

        // Svuota il file input per evitare conflitti
        const fileInput = document.getElementById('cover_image');
        if (fileInput) fileInput.value = '';

        showCoverPreview(photo.thumb, photo.credit);
    }

    function clearUnsplashSelection() {
        document.getElementById('unsplash_photo_url').value    = '';
        document.getElementById('unsplash_photo_credit').value = '';
        document.getElementById('unsplash_author_url').value   = '';
        document.querySelectorAll('#unsplash-grid button').forEach(b => {
            b.classList.remove('border-primary-500', 'ring-2', 'ring-primary-300');
            b.classList.add('border-transparent');
        });
    }

    function showCoverPreview(src, credit) {
        const wrap = document.getElementById('cover-preview-wrap');
        const img  = document.getElementById('cover-preview-img');
        const cred = document.getElementById('cover-preview-credit');
        img.src = src;
        cred.textContent = credit;
        wrap.classList.remove('hidden');
    }
</script>
@endpush
</div>
