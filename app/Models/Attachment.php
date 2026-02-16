<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Attachment
 *
 * Rappresenta un file caricato e associato ad entità (transazioni, investimenti,
 * ecc.). I file sono referenziati tramite `file_path` e tracciati con
 * `uploaded_by`/`uploaded_at`.
 */
class Attachment extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'attachable_type', 'attachable_id', 'file_path', 'filename', 'mime_type', 'file_size', 'uploaded_at', 'uploaded_by',
    ];

    protected $dates = ['uploaded_at'];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
