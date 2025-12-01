<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Currency
 *
 * Rappresenta una valuta supportata dall'app (es. EUR, USD). La tabella usa
 * `code` come chiave primaria stringa. Viene mantenuta separata per consentire
 * referenze consistenti nelle altre tabelle (accounts, transactions, budgets).
 */
class Currency extends Model
{
    use HasFactory, DispatchesModelEvents;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code', 'name', 'symbol',
    ];
}
