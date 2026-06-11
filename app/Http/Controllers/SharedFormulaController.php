<?php

namespace App\Http\Controllers;

use App\Models\FormulaWidget;
use App\Services\FormulaWidgetPayloadBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SharedFormulaController extends Controller
{
    public function show(string $shareToken, FormulaWidgetPayloadBuilder $payloadBuilder): View|RedirectResponse
    {
        $widget = FormulaWidget::query()
            ->where('share_token', $shareToken)
            ->where('is_public', true)
            ->with('financialVariable')
            ->first();

        if ($widget === null) {
            $widget = FormulaWidget::query()
                ->whereHas('financialVariable', fn ($q) => $q->where('share_token', $shareToken)->where('is_public', true))
                ->with('financialVariable')
                ->firstOrFail();
        }

        if (Auth::check()) {
            return redirect()
                ->route('dashboard')
                ->with('importShareToken', $shareToken);
        }

        $preview = $payloadBuilder->buildForGuest($widget);

        return view('public.shared-formula', [
            'widget' => $widget,
            'preview' => $preview,
            'shareToken' => $shareToken,
        ]);
    }
}
