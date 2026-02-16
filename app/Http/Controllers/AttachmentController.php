<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    /**
     * Upload di un allegato per una transazione.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'attachable_type' => 'required|string|in:Transaction,Investment',
            'attachable_id' => 'required|integer',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120', // Max 5MB
        ]);

        $user = Auth::user();
        $attachableType = 'App\\Models\\' . $request->attachable_type;
        $attachable = $attachableType::findOrFail($request->attachable_id);

        // Autorizzazione: verifica che l'utente abbia accesso all'entità
        if ($attachableType === 'App\\Models\\Transaction') {
            $this->authorizeTransaction($attachable);
        }

        // Upload del file
        $file = $request->file('file');
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $mimeType = $file->getClientMimeType();
        $fileSize = $file->getSize();

        // Genera un nome file unico
        $uniqueFilename = now()->format('Y-m-d_His') . '_' . Str::slug(pathinfo($filename, PATHINFO_FILENAME)) . '.' . $extension;
        $path = $file->storeAs('attachments', $uniqueFilename, 'private');

        // Crea il record dell'attachment
        $attachment = Attachment::create([
            'attachable_type' => $attachableType,
            'attachable_id' => $request->attachable_id,
            'file_path' => $path,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'uploaded_at' => now(),
            'uploaded_by' => $user->id,
        ]);

        return response()->json([
            'success' => true,
            'attachment' => [
                'id' => $attachment->id,
                'filename' => $attachment->filename,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
                'uploaded_at' => $attachment->uploaded_at->format('d/m/Y H:i'),
            ],
        ], 201);
    }

    /**
     * Download di un allegato.
     */
    public function download(Attachment $attachment): BinaryFileResponse
    {
        $attachable = $attachment->attachable;

        // Autorizzazione
        if ($attachable instanceof Transaction) {
            $this->authorizeTransaction($attachable);
        }

        if (!Storage::disk('private')->exists($attachment->file_path)) {
            abort(404, 'File non trovato.');
        }

        return response()->download(
            Storage::disk('private')->path($attachment->file_path),
            $attachment->filename
        );
    }

    /**
     * Elimina un allegato.
     */
    public function destroy(Attachment $attachment): JsonResponse
    {
        $attachable = $attachment->attachable;

        // Autorizzazione
        if ($attachable instanceof Transaction) {
            $this->authorizeTransaction($attachable);
        }

        // Elimina il file dal disco
        if (Storage::disk('private')->exists($attachment->file_path)) {
            Storage::disk('private')->delete($attachment->file_path);
        }

        // Elimina il record
        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Allegato eliminato con successo.',
        ]);
    }

    /**
     * Verifica che l'utente possa accedere alla transazione.
     */
    private function authorizeTransaction(Transaction $transaction): void
    {
        $user = Auth::user();
        $account = $transaction->account;

        // Deve appartenere alla household attiva
        if ($account->household_id !== $user->active_household_id) {
            abort(403, 'Non hai accesso a questa transazione.');
        }

        // Se è privata, deve essere il creatore
        if ($transaction->is_private && $transaction->user_id !== $user->id) {
            abort(403, 'Questa transazione è privata.');
        }
    }
}
