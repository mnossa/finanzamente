<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnsureFinancialVariableRequest;
use App\Http\Requests\StoreFinancialVariableRequest;
use App\Http\Requests\UpdateFinancialVariableRequest;
use App\Models\FinancialVariable;
use App\Services\FinancialVariableLibraryService;
use App\Services\FormulaResolverService;
use App\Support\FormulaTokenParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FinancialVariableController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FinancialVariable::class);

        $user = $request->user();
        $available = app(FormulaResolverService::class)->listAvailableVariables($user);

        $variables = FinancialVariable::query()
            ->where('user_id', $user->id)
            ->where('is_official_template', false)
            ->orderBy('name')
            ->get()
            ->map(fn (FinancialVariable $variable) => $this->formatVariable($variable));

        return Inertia::render('FormulaWidgets/Variables/Index', [
            'variables' => $variables,
            'systemVariables' => $available['system'],
        ]);
    }

    public function store(StoreFinancialVariableRequest $request): RedirectResponse|JsonResponse
    {
        $this->authorize('create', FinancialVariable::class);

        $user = Auth::user();
        $data = $request->validated();

        $variable = FinancialVariable::create([
            'user_id' => $user->id,
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'static_value' => $data['type'] === FinancialVariable::TYPE_STATIC ? ($data['static_value'] ?? 0) : null,
            'formula_string' => $data['type'] === FinancialVariable::TYPE_FORMULA ? ($data['formula_string'] ?? null) : null,
            'is_public' => (bool) ($data['is_public'] ?? false),
            'share_token' => ($data['is_public'] ?? false) ? $this->generateShareToken() : null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'variable' => self::formatVariable($variable),
                'message' => 'Variabile creata con successo.',
            ]);
        }

        return redirect()
            ->route('formula-variables.index')
            ->with('success', 'Variabile creata con successo.');
    }

    /**
     * Excel-like: scegli una metrica → riusa se stessa formula, altrimenti crea e restituisci subito.
     */
    public function ensure(EnsureFinancialVariableRequest $request): JsonResponse
    {
        $this->authorize('create', FinancialVariable::class);

        $user = Auth::user();
        $data = $request->validated();
        $parser = app(FormulaTokenParser::class);
        $library = app(FinancialVariableLibraryService::class);

        if (($data['type'] ?? null) === FinancialVariable::TYPE_FORMULA) {
            $rawFormula = (string) ($data['formula_string'] ?? '');
            $formula = $library->applyFormulaAlias($rawFormula);
            $normalized = $parser->normalizeFormula($formula);

            if ($normalized !== '') {
                $existing = $library->findReusableByFormula($user, $formula);

                if ($existing !== null) {
                    return response()->json([
                        'variable' => self::formatVariable($existing),
                        'created' => false,
                        'message' => 'Metrica già in libreria: selezionata.',
                    ]);
                }

                $repaired = $library->repairByScenarioName($user, (string) $data['name'], $formula);
                if ($repaired !== null) {
                    return response()->json([
                        'variable' => self::formatVariable($repaired),
                        'created' => false,
                        'message' => 'Metrica riparata e selezionata.',
                    ]);
                }

                $data['formula_string'] = $formula;
            }
        }

        $preferredCode = (string) ($data['code'] ?? '');
        if ($preferredCode === '' || ! $parser->isValidCode($preferredCode)) {
            try {
                $preferredCode = $parser->sanitizeCode($data['name']);
            } catch (ValidationException) {
                $preferredCode = 'metrica';
            }
        }

        $variable = FinancialVariable::create([
            'user_id' => $user->id,
            'code' => $parser->uniqueCodeForUser((int) $user->id, $preferredCode),
            'name' => $data['name'],
            'type' => $data['type'],
            'static_value' => $data['type'] === FinancialVariable::TYPE_STATIC ? ($data['static_value'] ?? 0) : null,
            'formula_string' => $data['type'] === FinancialVariable::TYPE_FORMULA ? ($data['formula_string'] ?? null) : null,
            'is_public' => false,
            'share_token' => null,
        ]);

        return response()->json([
            'variable' => self::formatVariable($variable),
            'created' => true,
            'message' => 'Metrica creata e selezionata.',
        ], 201);
    }

    public function update(UpdateFinancialVariableRequest $request, FinancialVariable $financialVariable): RedirectResponse
    {
        $this->authorize('update', $financialVariable);

        $data = $request->validated();
        $type = $data['type'] ?? $financialVariable->type;

        $financialVariable->fill([
            'name' => $data['name'] ?? $financialVariable->name,
            'code' => $data['code'] ?? $financialVariable->code,
            'type' => $type,
            'static_value' => $type === FinancialVariable::TYPE_STATIC
                ? ($data['static_value'] ?? $financialVariable->static_value)
                : null,
            'formula_string' => $type === FinancialVariable::TYPE_FORMULA
                ? ($data['formula_string'] ?? $financialVariable->formula_string)
                : null,
            'is_public' => array_key_exists('is_public', $data)
                ? (bool) $data['is_public']
                : $financialVariable->is_public,
        ]);

        if ($financialVariable->is_public && $financialVariable->share_token === null) {
            $financialVariable->share_token = $this->generateShareToken();
        }

        if (! $financialVariable->is_public) {
            $financialVariable->share_token = null;
        }

        $financialVariable->save();

        return redirect()
            ->route('formula-variables.index')
            ->with('success', 'Variabile aggiornata.');
    }

    public function destroy(FinancialVariable $financialVariable): RedirectResponse
    {
        $this->authorize('delete', $financialVariable);

        $financialVariable->delete();

        return redirect()
            ->route('formula-variables.index')
            ->with('success', 'Variabile eliminata.');
    }

    /**
     * @return array<string, mixed>
     */
    public static function formatVariable(FinancialVariable $variable): array
    {
        return [
            'id' => $variable->id,
            'code' => $variable->code,
            'name' => $variable->name,
            'type' => $variable->type,
            'static_value' => $variable->static_value !== null ? (float) $variable->static_value : null,
            'formula_string' => $variable->formula_string,
            'is_public' => $variable->is_public,
            'share_token' => $variable->share_token,
            'downloads_count' => $variable->downloads_count,
            'source_id' => $variable->source_id,
            'is_official_origin' => self::isOfficialOriginVariable($variable),
        ];
    }

    private static function isOfficialOriginVariable(FinancialVariable $variable): bool
    {
        if ($variable->is_official_template) {
            return true;
        }

        if ($variable->source_id === null) {
            return false;
        }

        if ($variable->relationLoaded('source')) {
            return (bool) $variable->source?->is_official_template;
        }

        return FinancialVariable::query()
            ->where('id', $variable->source_id)
            ->where('is_official_template', true)
            ->exists();
    }

    private function generateShareToken(): string
    {
        return 'v_'.Str::lower(Str::random(10));
    }
}
