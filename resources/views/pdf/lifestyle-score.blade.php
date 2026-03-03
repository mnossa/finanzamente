<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifestyle Inflation Score — {{ $periodLabel }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
        .header h1 { color: #059669; font-size: 22pt; margin-bottom: 5px; }
        .header p { color: #64748b; font-size: 10pt; }
        .score-section {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
        }
        .score-value {
            font-size: 42pt;
            font-weight: bold;
        }
        .score-label { font-size: 14pt; color: #64748b; }
        .score-good   { color: #059669; }
        .score-warn   { color: #d97706; }
        .score-bad    { color: #dc2626; }
        .score-none   { color: #94a3b8; }
        h2 { color: #334155; font-size: 14pt; margin: 20px 0 8px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        .metric-box {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 12px;
        }
        .metric-label { font-size: 9pt; color: #64748b; margin-bottom: 4px; }
        .metric-value { font-size: 14pt; font-weight: bold; color: #1e293b; }
        .metric-value.green { color: #059669; }
        .metric-value.red   { color: #dc2626; }
        .metric-value.orange{ color: #d97706; }
        .metric-value.blue  { color: #3b82f6; }
        table { width: 100%; border-collapse: collapse; font-size: 10pt; }
        th { background: #f1f5f9; color: #475569; font-weight: 600; padding: 8px 10px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; }
        tr:last-child td { border-bottom: none; }
        .excluded-tag {
            display: inline-block;
            background: #e0e7ff;
            color: #4338ca;
            border-radius: 4px;
            padding: 1px 6px;
            font-size: 8pt;
        }
        .included-tag {
            display: inline-block;
            background: #d1fae5;
            color: #065f46;
            border-radius: 4px;
            padding: 1px 6px;
            font-size: 8pt;
        }
        .footer { margin-top: 30px; text-align: center; color: #94a3b8; font-size: 9pt; }
        .formula-box {
            background: #f8fafc;
            border-left: 3px solid #059669;
            padding: 10px 14px;
            font-family: monospace;
            font-size: 9.5pt;
            color: #475569;
            margin: 10px 0 20px;
            border-radius: 0 6px 6px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📈 Lifestyle Inflation Score</h1>
        <p>Periodo: {{ $periodLabel }} · Generato il {{ $generatedAt->format('d/m/Y \a\l\l\e H:i') }}</p>
        <p>Utente: {{ $user->name }}</p>
    </div>

    {{-- Score principale --}}
    @php
        $score = $metrics['lifestyle_score'];
        $scoreClass = $score === null ? 'score-none' : ($score >= 30 ? 'score-good' : ($score >= 10 ? 'score-warn' : 'score-bad'));
        $scoreLabel = $score === null ? 'Dati insufficienti' : ($score >= 30 ? 'Ottimo' : ($score >= 10 ? 'Attenzione' : 'Critico'));
        $fmtEur = fn(float $v) => number_format($v, 2, ',', '.') . ' €';
    @endphp

    <div class="score-section">
        <div class="score-value {{ $scoreClass }}">
            {{ $score !== null ? number_format($score, 1, ',', '.') . '%' : '—' }}
        </div>
        <div class="score-label {{ $scoreClass }}">{{ $scoreLabel }}</div>
    </div>

    {{-- Formula --}}
    <div class="formula-box">
        Score = (Reddito Netto − Spese Effettive) ÷ Reddito Netto × 100
    </div>

    {{-- Metriche --}}
    <h2>Riepilogo</h2>
    <div class="metrics-grid">
        <div class="metric-box">
            <div class="metric-label">Reddito Lordo</div>
            <div class="metric-value">{{ $fmtEur($metrics['gross_income']) }}</div>
        </div>
        @if($metrics['is_partita_iva'])
        <div class="metric-box">
            <div class="metric-label">Tasse Stimate ({{ $metrics['tax_rate'] }}% + {{ $metrics['inps_rate'] }}% INPS)</div>
            <div class="metric-value orange">{{ $fmtEur($metrics['estimated_taxes']) }}</div>
        </div>
        @endif
        <div class="metric-box">
            <div class="metric-label">Reddito Netto</div>
            <div class="metric-value green">{{ $fmtEur($metrics['net_income']) }}</div>
        </div>
        <div class="metric-box">
            <div class="metric-label">Spese Totali</div>
            <div class="metric-value red">{{ $fmtEur($metrics['total_expenses']) }}</div>
        </div>
        <div class="metric-box">
            <div class="metric-label">Investimenti / Esclusi</div>
            <div class="metric-value blue">{{ $fmtEur($metrics['excluded_expenses']) }}</div>
        </div>
        <div class="metric-box">
            <div class="metric-label">Spese Effettive</div>
            <div class="metric-value orange">{{ $fmtEur($metrics['effective_expenses']) }}</div>
        </div>
    </div>

    {{-- Breakdown categorie --}}
    <h2>Dettaglio per Categoria</h2>
    @if(count($metrics['category_breakdown']) > 0)
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th style="text-align:right">Importo</th>
                    <th style="text-align:right">% sul totale</th>
                    <th style="text-align:center">Nel Score</th>
                </tr>
            </thead>
            <tbody>
                @foreach($metrics['category_breakdown'] as $row)
                <tr>
                    <td>{{ $row['icon'] ? $row['icon'] . ' ' : '' }}{{ $row['name'] }}</td>
                    <td style="text-align:right">{{ $fmtEur($row['amount']) }}</td>
                    <td style="text-align:right">{{ number_format($row['percentage'], 1, ',', '.') }}%</td>
                    <td style="text-align:center">
                        @if($row['excluded'])
                            <span class="excluded-tag">Escluso</span>
                        @else
                            <span class="included-tag">Incluso</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="color:#94a3b8;padding:10px 0">Nessuna transazione nel periodo selezionato.</p>
    @endif

    <div class="footer">
        Finanzamente · Report generato automaticamente
    </div>
</body>
</html>
