<?php

namespace App\Http\Controllers;

use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use Artesaos\SEOTools\Facades\OpenGraph;
use Artesaos\SEOTools\Facades\SEOMeta;
use Artesaos\SEOTools\Facades\TwitterCard;
use Illuminate\View\View;

class MagazineController extends Controller
{
    private const ARTICLES_PER_PAGE = 12;

    /**
     * Lista articoli — homepage del magazine.
     */
    public function index(): View
    {
        $categories = MagazineCategory::orderBy('sort_order')->get();

        $featured = MagazineArticle::with('category')
            ->published()
            ->where('is_featured', true)
            ->latest('published_at')
            ->first();

        $articles = MagazineArticle::with('category')
            ->published()
            ->when($featured, fn ($q) => $q->where('id', '!=', $featured->id))
            ->latest('published_at')
            ->paginate(self::ARTICLES_PER_PAGE);

        SEOMeta::setTitle('Magazine — Finanzamente');
        SEOMeta::setDescription('Articoli, guide e consigli pratici sulla gestione del denaro personale in Italia.');
        OpenGraph::setTitle('Magazine — Finanzamente');
        OpenGraph::setDescription('Articoli, guide e consigli pratici sulla gestione del denaro personale in Italia.');
        OpenGraph::setType('website');

        return view('magazine.index', compact('categories', 'featured', 'articles'));
    }

    /**
     * Lista articoli per categoria.
     */
    public function category(string $categorySlug): View
    {
        $category = MagazineCategory::where('slug', $categorySlug)->firstOrFail();

        $categories = MagazineCategory::orderBy('sort_order')->get();

        $articles = MagazineArticle::with('category')
            ->published()
            ->where('category_id', $category->id)
            ->latest('published_at')
            ->paginate(self::ARTICLES_PER_PAGE);

        SEOMeta::setTitle($category->name . ' — Magazine Finanzamente');
        SEOMeta::setDescription($category->description ?? 'Articoli su ' . $category->name);
        OpenGraph::setTitle($category->name . ' — Magazine Finanzamente');
        OpenGraph::setType('website');

        return view('magazine.category', compact('category', 'categories', 'articles'));
    }

    /**
     * Singolo articolo.
     */
    public function show(string $slug): View
    {
        $article = MagazineArticle::with('category')
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $ip   = request()->ip() ?? '';
        $salt = config('app.key', '');
        $article->incrementViews(hash('sha256', $ip . $salt));

        $related = MagazineArticle::with('category')
            ->published()
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->limit(3)
            ->get();

        $metaTitle = $article->meta_title ?: $article->title . ' — Finanzamente';
        $metaDescription = $article->meta_description ?: $article->excerpt;

        SEOMeta::setTitle($metaTitle);
        SEOMeta::setDescription($metaDescription);
        SEOMeta::addMeta('article:published_time', $article->published_at->toIso8601String(), 'property');
        SEOMeta::addMeta('article:section', $article->category->name, 'property');

        OpenGraph::setTitle($metaTitle);
        OpenGraph::setDescription($metaDescription);
        OpenGraph::setType('article');
        if ($article->cover_image_path) {
            OpenGraph::addImage(asset('storage/' . $article->cover_image_path));
        }

        TwitterCard::setTitle($metaTitle);
        TwitterCard::setDescription($metaDescription);
        TwitterCard::setType('summary_large_image');

        return view('magazine.show', compact('article', 'related'));
    }
}
