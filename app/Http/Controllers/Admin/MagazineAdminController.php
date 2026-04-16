<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $data['cover_image_path'] = $this->handleCoverImage($request);
        $data['reading_time_minutes'] = MagazineArticle::estimateReadingTime($data['content']);
        $data['slug'] = $this->uniqueSlug($data['title']);

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

    /** Aggiorna articolo. */
    public function update(Request $request, MagazineArticle $article): RedirectResponse
    {
        $data = $this->validateArticle($request, $article->id);

        $newCoverPath = $this->handleCoverImage($request);
        if ($newCoverPath !== null) {
            // Elimina la vecchia immagine dal volume storage
            if ($article->cover_image_path) {
                Storage::disk('public')->delete($article->cover_image_path);
            }
            $data['cover_image_path'] = $newCoverPath;
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

    // ── Helpers privati ───────────────────────────────────────────────────────

    private function validateArticle(Request $request, ?int $articleId = null): array
    {
        return $request->validate([
            'category_id'      => ['required', 'exists:magazine_categories,id'],
            'title'            => ['required', 'string', 'max:255'],
            'excerpt'          => ['required', 'string', 'max:500'],
            'content'          => ['required', 'string'],
            'cover_image'      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'author_name'      => ['required', 'string', 'max:100'],
            'published_at'     => ['nullable', 'date'],
            'is_featured'      => ['boolean'],
            'is_ai_assisted'   => ['boolean'],
            'meta_title'       => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:160'],
        ]);
    }

    /**
     * Salva l'immagine di copertina nel volume storage (persiste tra i deploy).
     * Restituisce il path relativo a storage/app/public, oppure null se nessuna immagine.
     */
    private function handleCoverImage(Request $request): ?string
    {
        if (! $request->hasFile('cover_image')) {
            return null;
        }

        $file = $request->file('cover_image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        // Salva in storage/app/public/magazine/covers/
        // → accessibile via asset('storage/magazine/covers/<filename>')
        return $file->storeAs('magazine/covers', $filename, 'public');
    }

    private function uniqueSlug(string $title): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $i = 1;

        while (MagazineArticle::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }

        return $slug;
    }
}
