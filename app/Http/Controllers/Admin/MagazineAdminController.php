<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Gestione articoli del magazine (area riservata al proprietario).
 *
 * Accesso protetto dal middleware 'owner' registrato in bootstrap/app.php.
 */
class MagazineAdminController extends Controller
{
    /** Lista articoli con paginazione. */
    public function index(): View
    {
        $articles = MagazineArticle::with('category')
            ->latest()
            ->paginate(20);

        return view('admin.magazine.index', compact('articles'));
    }

    /** Form creazione nuovo articolo. */
    public function create(): View
    {
        $categories = MagazineCategory::orderBy('sort_order')->get();

        return view('admin.magazine.create', compact('categories'));
    }

    /** Salva nuovo articolo. */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateArticle($request);

        [$coverPath, $credit, $creditUrl] = $this->handleCoverImage($request);
        $data['cover_image_path']       = $coverPath;
        $data['cover_image_credit']     = $credit;
        $data['cover_image_credit_url'] = $creditUrl;
        $data['reading_time_minutes']   = MagazineArticle::estimateReadingTime($data['content']);
        $data['slug']                   = $this->uniqueSlug($data['title']);

        MagazineArticle::create($data);

        cache()->forget('magazine_has_published');

        return redirect()->route('admin.magazine.index')
            ->with('success', 'Articolo creato con successo.');
    }

    /** Form modifica articolo. */
    public function edit(MagazineArticle $article): View
    {
        $categories = MagazineCategory::orderBy('sort_order')->get();

        return view('admin.magazine.edit', compact('article', 'categories'));
    }

    /**
     * Anteprima articolo in bozza — visibile solo all'admin/owner.
     * Usa la stessa view pubblica ma senza il constraint published().
     */
    public function preview(MagazineArticle $article): View
    {
        $article->load('category');

        $related = MagazineArticle::with('category')
            ->published()
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->limit(3)
            ->get();

        return view('magazine.show', compact('article', 'related'));
    }

    /** Aggiorna articolo. */
    public function update(Request $request, MagazineArticle $article): RedirectResponse
    {
        $data = $this->validateArticle($request, $article->id);

        [$newCoverPath, $credit, $creditUrl] = $this->handleCoverImage($request);
        if ($newCoverPath !== null) {
            if ($article->cover_image_path) {
                Storage::disk('public')->delete($article->cover_image_path);
            }
            $data['cover_image_path']       = $newCoverPath;
            $data['cover_image_credit']     = $credit;
            $data['cover_image_credit_url'] = $creditUrl;
        }

        $data['reading_time_minutes'] = MagazineArticle::estimateReadingTime($data['content']);

        $article->update($data);

        cache()->forget('magazine_has_published');

        return redirect()->route('admin.magazine.index')
            ->with('success', 'Articolo aggiornato con successo.');
    }

    /** Elimina articolo e relativa immagine. */
    public function destroy(MagazineArticle $article): RedirectResponse
    {
        if ($article->cover_image_path) {
            Storage::disk('public')->delete($article->cover_image_path);
        }

        $article->delete();

        cache()->forget('magazine_has_published');

        return redirect()->route('admin.magazine.index')
            ->with('success', 'Articolo eliminato.');
    }

    /**
     * Proxy per la ricerca immagini su Unsplash.
     * La chiave API non viene mai esposta al frontend.
     */
    public function unsplashSearch(Request $request): JsonResponse
    {
        $request->validate([
            'q'    => ['required', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $key = config('services.unsplash.access_key');

        if (! $key) {
            return response()->json(['error' => 'Unsplash non configurato. Vedi tasks/unsplash-setup.md'], 503);
        }

        $response = Http::timeout(10)
            ->get('https://api.unsplash.com/search/photos', [
                'query'       => $request->q,
                'per_page'    => 20,
                'page'        => $request->integer('page', 1),
                'orientation' => 'landscape',
                'client_id'   => $key,
            ]);

        if (! $response->ok()) {
            return response()->json(['error' => 'Errore nella ricerca Unsplash'], 502);
        }

        $data        = $response->json();
        $totalPages  = $data['total_pages'] ?? 1;
        $currentPage = $request->integer('page', 1);

        $results = collect($data['results'] ?? [])->map(fn ($photo) => [
            'id'          => $photo['id'],
            'thumb'       => $photo['urls']['small'],
            'full'        => $photo['urls']['full'],
            'description' => $photo['alt_description'] ?? $photo['description'] ?? '',
            'author_name' => $photo['user']['name'],
            'author_url'  => $photo['user']['links']['html'] . '?utm_source=finanzamente&utm_medium=referral',
            'credit'      => 'Photo by ' . $photo['user']['name'] . ' on Unsplash',
        ]);

        return response()->json([
            'results'      => $results,
            'current_page' => $currentPage,
            'has_more'     => $currentPage < $totalPages,
        ]);
    }

    // ── Helpers privati ───────────────────────────────────────────────────────

    private function validateArticle(Request $request, ?int $articleId = null): array
    {
        return $request->validate([
            'category_id'           => ['required', 'exists:magazine_categories,id'],
            'title'                 => ['required', 'string', 'max:255'],
            'excerpt'               => ['required', 'string', 'max:500'],
            'content'               => ['required', 'string'],
            'cover_image'           => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'unsplash_photo_url'    => ['nullable', 'url'],
            'unsplash_photo_credit' => ['nullable', 'string', 'max:255'],
            'unsplash_author_url'   => ['nullable', 'url'],
            'author_name'           => ['required', 'string', 'max:100'],
            'published_at'          => ['nullable', 'date'],
            'is_featured'           => ['boolean'],
            'is_ai_assisted'        => ['boolean'],
            'meta_title'            => ['nullable', 'string', 'max:70'],
            'meta_description'      => ['nullable', 'string', 'max:160'],
        ]);
    }

    /**
     * Gestisce l'immagine di copertina: da upload diretto o da URL Unsplash.
     * Restituisce [$path, $credit, $creditUrl] — $path è null se nessuna immagine.
     *
     * @return array{0: string|null, 1: string|null, 2: string|null}
     */
    private function handleCoverImage(Request $request): array
    {
        // Priorità 1: file caricato direttamente
        if ($request->hasFile('cover_image')) {
            $file     = $request->file('cover_image');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs('magazine/covers', $filename, 'public');

            return [$path, null, null];
        }

        // Priorità 2: immagine Unsplash da scaricare
        if ($request->filled('unsplash_photo_url')) {
            $path = $this->downloadRemoteImage($request->unsplash_photo_url);

            if ($path) {
                return [
                    $path,
                    $request->input('unsplash_photo_credit'),
                    $request->input('unsplash_author_url'),
                ];
            }
        }

        return [null, null, null];
    }

    /**
     * Scarica un'immagine remota e la salva nel volume storage.
     * Restituisce il path relativo o null in caso di errore.
     */
    private function downloadRemoteImage(string $url): ?string
    {
        try {
            $response = Http::timeout(20)->get($url);

            if (! $response->ok()) {
                return null;
            }

            $contentType = $response->header('Content-Type');
            $extension   = match (true) {
                str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
                str_contains($contentType, 'png')  => 'png',
                str_contains($contentType, 'webp') => 'webp',
                default                            => 'jpg',
            };

            $filename = Str::uuid() . '.' . $extension;
            $path     = 'magazine/covers/' . $filename;

            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    private function uniqueSlug(string $title): string
    {
        $slug     = Str::slug($title);
        $original = $slug;
        $i        = 1;

        while (MagazineArticle::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }

        return $slug;
    }
}

