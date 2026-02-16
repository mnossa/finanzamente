<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detrazioni Fiscali {{ $year }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
            padding: 30px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #059669;
            padding-bottom: 15px;
        }
        .header h1 {
            color: #059669;
            font-size: 24pt;
            margin-bottom: 5px;
        }
        .header p {
            color: #64748b;
            font-size: 10pt;
        }
        .info-box {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-box p {
            margin: 3px 0;
            font-size: 10pt;
        }
        .info-box strong {
            color: #334155;
        }
        h2 {
            color: #334155;
            font-size: 16pt;
            margin: 25px 0 15px 0;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 10pt;
        }
        table thead {
            background: #059669;
            color: white;
        }
        table thead th {
            padding: 10px 8px;
            text-align: left;
            font-weight: 600;
        }
        table tbody tr {
            border-bottom: 1px solid #e2e8f0;
        }
        table tbody tr:nth-child(even) {
            background: #f8fafc;
        }
        table tbody td {
            padding: 8px;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .amount {
            font-weight: 600;
            white-space: nowrap;
        }
        .deductible {
            color: #059669;
            font-weight: 700;
        }
        .summary-box {
            background: #dcfce7;
            border: 2px solid #059669;
            border-radius: 8px;
            padding: 15px;
            margin-top: 30px;
        }
        .summary-box h3 {
            color: #059669;
            font-size: 14pt;
            margin-bottom: 10px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .summary-item {
            padding: 8px;
            background: white;
            border-radius: 4px;
        }
        .summary-item .label {
            font-size: 9pt;
            color: #64748b;
            margin-bottom: 3px;
        }
        .summary-item .value {
            font-size: 14pt;
            font-weight: 700;
            color: #059669;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 9pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Detrazioni Fiscali {{ $year }}</h1>
        <p>Report generato per la dichiarazione dei redditi</p>
    </div>

    <div class="info-box">
        <p><strong>Utente:</strong> {{ $user->name }}</p>
        <p><strong>Email:</strong> {{ $user->email }}</p>
        <p><strong>Generato il:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</p>
    </div>

    @php
        $groupedTransactions = $transactions->groupBy('tax_deduction_type');
        $totalAmount = 0;
        $totalDeductible = 0;
    @endphp

    @foreach($groupedTransactions as $type => $typeTransactions)
        @php
            $typeTotal = $typeTransactions->sum(fn($t) => abs((float) $t->amount));
            $typeDeductible = $typeTransactions->sum(fn($t) => $t->getTaxDeductibleAmount());
            $totalAmount += $typeTotal;
            $totalDeductible += $typeDeductible;
        @endphp
        
        <h2>{{ ucfirst($type ?? 'Altro') }} ({{ $typeTransactions->count() }} spese)</h2>
        
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrizione</th>
                    <th>Categoria</th>
                    <th class="text-right">Importo</th>
                    <th class="text-center">%</th>
                    <th class="text-right">Detraibile</th>
                </tr>
            </thead>
            <tbody>
                @foreach($typeTransactions as $transaction)
                <tr>
                    <td>{{ $transaction->date->format('d/m/Y') }}</td>
                    <td>{{ $transaction->description ?: '-' }}</td>
                    <td>{{ $transaction->category->name ?? '-' }}</td>
                    <td class="text-right amount">€ {{ number_format(abs((float) $transaction->amount), 2, ',', '.') }}</td>
                    <td class="text-center">{{ $transaction->tax_deduction_rate }}%</td>
                    <td class="text-right amount deductible">€ {{ number_format($transaction->getTaxDeductibleAmount(), 2, ',', '.') }}</td>
                </tr>
                @endforeach
                <tr style="font-weight: 600; background: #f1f5f9;">
                    <td colspan="3" class="text-right">Totale {{ ucfirst($type ?? 'Altro') }}:</td>
                    <td class="text-right amount">€ {{ number_format($typeTotal, 2, ',', '.') }}</td>
                    <td></td>
                    <td class="text-right amount deductible">€ {{ number_format($typeDeductible, 2, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach

    <div class="summary-box">
        <h3>Riepilogo Totale</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="label">Numero Spese</div>
                <div class="value">{{ $transactions->count() }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Totale Speso</div>
                <div class="value">€ {{ number_format($totalAmount, 2, ',', '.') }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Totale Detraibile</div>
                <div class="value">€ {{ number_format($totalDeductible, 2, ',', '.') }}</div>
            </div>
            <div class="summary-item">
                <div class="label">Percentuale Media</div>
                <div class="value">{{ $totalAmount > 0 ? number_format(($totalDeductible / $totalAmount) * 100, 1, ',', '.') : 0 }}%</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <p>Documento generato automaticamente da <strong>Finanzamente</strong></p>
        <p>Questo documento è riservato e destinato esclusivamente all'uso del destinatario.</p>
        <p><strong>Disclaimer:</strong> Le informazioni contenute in questo documento sono fornite a titolo informativo e non costituiscono consulenza fiscale o legale. L'autore declina ogni responsabilità per l'utilizzo di tali informazioni e per qualsiasi danno diretto o indiretto derivante dalla loro interpretazione o applicazione. Si consiglia di consultare un professionista qualificato prima di intraprendere azioni basate su questi dati.</p>
    </div>
</body>
</html>
