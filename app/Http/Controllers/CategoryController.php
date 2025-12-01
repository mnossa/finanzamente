<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Tipi di categoria disponibili.
     */
    public const TYPES = [
        'income' => 'Entrata',
        'expense' => 'Uscita',
    ];

    /**
     * Mostra l'elenco delle categorie della household attiva.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $categories = Category::where('household_id', $householdId)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'type' => $category->type,
                    'type_label' => self::TYPES[$category->type] ?? $category->type,
                    'color' => $category->color,
                    'icon' => $category->icon,
                    'created_at' => $category->created_at->format('Y-m-d'),
                ];
            });

        // Raggruppa per tipo
        $byType = [
            'income' => $categories->where('type', 'income')->values(),
            'expense' => $categories->where('type', 'expense')->values(),
        ];

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'byType' => $byType,
            'categoryTypes' => self::TYPES,
        ]);
    }

    /**
     * Mostra il form per creare una nuova categoria.
     */
    public function create(): Response
    {
        return Inertia::render('Categories/Create', [
            'categoryTypes' => self::TYPES,
        ]);
    }

    /**
     * Salva una nuova categoria.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $category = new Category($validated);
        $category->household_id = $user->active_household_id;
        $category->save();

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoria creata con successo.');
    }

    /**
     * Mostra il form per modificare una categoria.
     */
    public function edit(Category $category): Response
    {
        $this->authorizeCategory($category);

        return Inertia::render('Categories/Edit', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type,
                'color' => $category->color,
                'icon' => $category->icon,
            ],
            'categoryTypes' => self::TYPES,
        ]);
    }

    /**
     * Aggiorna una categoria esistente.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);

        $category->update($request->validated());

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoria aggiornata con successo.');
    }

    /**
     * Elimina una categoria (soft delete).
     */
    public function destroy(Category $category): RedirectResponse
    {
        $this->authorizeCategory($category);

        // Verifica che non ci siano transazioni associate
        if ($category->transactions()->exists()) {
            return back()->with('error', 'Non puoi eliminare una categoria con transazioni associate.');
        }

        $category->delete();

        return redirect()
            ->route('categories.index')
            ->with('success', 'Categoria eliminata con successo.');
    }

    /**
     * Verifica che l'utente possa accedere alla categoria.
     */
    private function authorizeCategory(Category $category): void
    {
        $user = Auth::user();

        // Deve appartenere alla household attiva
        if ($category->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questa categoria.');
        }
    }
}
