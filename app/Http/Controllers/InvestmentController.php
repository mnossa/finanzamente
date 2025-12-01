<?php

namespace App\Http\Controllers;

use App\Models\Investment;
use Illuminate\Http\Request;

class InvestmentController extends Controller
{
    public function index()
    {
        return response()->json(Investment::with('asset')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'household_id' => 'required|exists:households,id',
            'asset_id' => 'required|exists:investment_assets,id',
            'quantity' => 'required|numeric',
            'buy_price' => 'required|numeric',
            'buy_date' => 'required|date',
        ]);

        $inv = Investment::create($data);
        return response()->json($inv, 201);
    }

    public function show(Investment $investment)
    {
        return response()->json($investment->load('asset'));
    }

    public function update(Request $request, Investment $investment)
    {
        $data = $request->validate([
            'quantity' => 'sometimes|numeric',
            'sell_price' => 'sometimes|numeric',
            'sell_date' => 'nullable|date',
        ]);

        $investment->update($data);
        return response()->json($investment);
    }

    public function destroy(Investment $investment)
    {
        $investment->delete();
        return response()->json(null, 204);
    }
}
