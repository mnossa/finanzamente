<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransaction;
use Illuminate\Http\Request;

class RecurringTransactionController extends Controller
{
    public function index()
    {
        return response()->json(RecurringTransaction::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'account_id' => 'required|exists:accounts,id',
            'amount' => 'required|numeric',
            'currency_code' => 'required|string|exists:currencies,code',
            'frequency' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
        ]);

        $rec = RecurringTransaction::create($data);
        return response()->json($rec, 201);
    }

    public function show(RecurringTransaction $recurringTransaction)
    {
        return response()->json($recurringTransaction);
    }

    public function update(Request $request, RecurringTransaction $recurringTransaction)
    {
        $data = $request->validate([
            'amount' => 'sometimes|numeric',
            'frequency' => 'sometimes|string',
        ]);

        $recurringTransaction->update($data);
        return response()->json($recurringTransaction);
    }

    public function destroy(RecurringTransaction $recurringTransaction)
    {
        $recurringTransaction->delete();
        return response()->json(null, 204);
    }
}
