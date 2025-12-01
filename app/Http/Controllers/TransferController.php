<?php

namespace App\Http\Controllers;

use App\Services\TransferService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Transfer;

class TransferController extends Controller
{
    protected TransferService $service;

    public function __construct(TransferService $service)
    {
        $this->service = $service;
    }

    /**
     * Create a transfer (atomic): creates a Transfer row and two linked Transaction rows.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'source_account_id' => 'required|exists:accounts,id',
            'destination_account_id' => 'required|exists:accounts,id|different:source_account_id',
            'source_amount' => 'required|numeric|min:0.00000001',
            'source_currency' => 'required|string|exists:currencies,code',
            // dest_amount is calculated server-side from source_amount and exchange_rate;
            // frontend may show an estimated value but should not be authoritative.
            'dest_currency' => 'required|string|exists:currencies,code',
            'exchange_rate' => 'nullable|numeric',
            'fee' => 'nullable|numeric',
            'fee_category_id' => 'nullable|exists:categories,id',
            'source_category_id' => 'required|exists:categories,id',
            'dest_category_id' => 'required|exists:categories,id',
            'date' => 'nullable|date',
            'description' => 'nullable|string',
            'is_private' => 'nullable|boolean',
        ]);

        $user = $request->user();
        if ($user) {
            $data['user_id'] = $user->id;
        }

        // Authorization: user must be member of source account household
        $sourceAccount = \App\Models\Account::find($data['source_account_id']);
        if (! app(\App\Services\HouseholdPermissionService::class)->isMember($user, $sourceAccount->household_id)) {
            abort(403);
        }

        $transfer = $this->service->createTransfer($data);

        return response()->json($transfer, 201);
    }

    public function show(Transfer $transfer)
    {
        $this->authorize('view', $transfer);
        return response()->json($transfer->load(['transactions', 'sourceAccount', 'destinationAccount']));
    }
}
