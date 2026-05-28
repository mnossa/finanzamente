<?php

namespace App\Http\Controllers;

use App\Models\DuplicateTransactionCandidate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DuplicateTransactionCandidateController extends Controller
{
    public function index(): Response
    {
        $items = DuplicateTransactionCandidate::with(['primaryTransaction.account', 'candidateTransaction.account'])
            ->where('user_id', Auth::id())
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DuplicateTransactionCandidate $c) => [
                'id' => $c->id,
                'distance_days' => $c->distance_days,
                'primary' => [
                    'id' => $c->primaryTransaction->id,
                    'date' => $c->primaryTransaction->date->format('Y-m-d'),
                    'amount' => (float) $c->primaryTransaction->amount,
                    'description' => $c->primaryTransaction->description,
                ],
                'candidate' => [
                    'id' => $c->candidateTransaction->id,
                    'date' => $c->candidateTransaction->date->format('Y-m-d'),
                    'amount' => (float) $c->candidateTransaction->amount,
                    'description' => $c->candidateTransaction->description,
                ],
            ]);

        return Inertia::render('Transactions/Duplicates', ['items' => $items]);
    }

    public function markIgnored(DuplicateTransactionCandidate $candidate): RedirectResponse
    {
        abort_unless($candidate->user_id === Auth::id(), 403);
        $candidate->update(['status' => 'ignored']);

        return back()->with('success', 'Candidato duplicato ignorato.');
    }

    public function markValid(DuplicateTransactionCandidate $candidate): RedirectResponse
    {
        abort_unless($candidate->user_id === Auth::id(), 403);
        $candidate->update(['status' => 'validated']);

        return back()->with('success', 'Candidato marcato come valido.');
    }
}
