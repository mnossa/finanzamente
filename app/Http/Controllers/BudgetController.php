<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    public function index()
    {
        return response()->json(Budget::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'household_id' => 'required|exists:households,id',
            'category_id' => 'nullable|exists:categories,id',
            'amount' => 'required|numeric',
            'currency_code' => 'required|string|exists:currencies,code',
            'period_start' => 'required|date',
            'period_end' => 'required|date',
        ]);

        $budget = Budget::create($data);
        return response()->json($budget, 201);
    }

    public function show(Budget $budget)
    {
        return response()->json($budget);
    }

    public function update(Request $request, Budget $budget)
    {
        $data = $request->validate([
            'amount' => 'sometimes|numeric',
            'period_start' => 'sometimes|date',
            'period_end' => 'sometimes|date',
        ]);

        $budget->update($data);
        return response()->json($budget);
    }

    public function destroy(Budget $budget)
    {
        $budget->delete();
        return response()->json(null, 204);
    }
}
