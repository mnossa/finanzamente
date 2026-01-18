<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\InterHouseholdTransfer;
use App\Models\Transaction;

echo "=== Ultimi 3 trasferimenti inter-household ===\n\n";

$transfers = InterHouseholdTransfer::orderBy('created_at', 'desc')->take(3)->get();

foreach ($transfers as $transfer) {
    echo "ID: {$transfer->id}\n";
    echo "Status: {$transfer->status}\n";
    echo "Source Amount: {$transfer->source_amount}\n";
    echo "Source Transaction ID: {$transfer->source_transaction_id}\n";
    echo "Dest Transaction ID: {$transfer->dest_transaction_id}\n";
    echo "Created: {$transfer->created_at}\n";
    
    if ($transfer->source_transaction_id) {
        $sourceTx = Transaction::find($transfer->source_transaction_id);
        echo "  Source TX exists: " . ($sourceTx ? "YES (amount: {$sourceTx->amount})" : "NO") . "\n";
    }
    
    if ($transfer->dest_transaction_id) {
        $destTx = Transaction::find($transfer->dest_transaction_id);
        echo "  Dest TX exists: " . ($destTx ? "YES (amount: {$destTx->amount})" : "NO") . "\n";
    }
    
    echo "\n";
}

echo "\n=== Ultime 5 transazioni create ===\n\n";
$transactions = Transaction::orderBy('created_at', 'desc')->take(5)->get(['id', 'amount', 'description', 'account_id', 'created_at']);
foreach ($transactions as $tx) {
    echo "ID: {$tx->id}, Amount: {$tx->amount}, Account: {$tx->account_id}, Desc: {$tx->description}, Created: {$tx->created_at}\n";
}
