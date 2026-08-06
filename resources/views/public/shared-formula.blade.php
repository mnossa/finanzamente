@extends('layouts.guest')

@section('meta-tags')
    <title>{{ $widget->name }} — Anteprima | Finanzamente</title>
    <meta name="description" content="Anteprima interattiva di un widget finanziario personalizzato su Finanzamente.">
@endsection

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <p class="text-sm font-medium text-primary-600">Anteprima demo</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-surface-900">{{ $widget->name }}</h1>
        <p class="mt-3 text-surface-600">
            Valori dimostrativi per mostrarti come funziona un widget a formula su Finanzamente.
            Registrati per collegarlo ai tuoi dati reali.
        </p>

        <div class="mt-8 rounded-2xl border border-surface-200 bg-white p-6 shadow-sm">
            @if(($preview['type'] ?? '') === 'kpi')
                <p class="text-sm text-surface-500">{{ $preview['periodLabel'] ?? '' }}</p>
                <p class="mt-2 text-4xl font-bold text-surface-900">
                    @if(($preview['format'] ?? 'currency') === 'percent')
                        {{ number_format((float) ($preview['value'] ?? 0), 1, ',', '.') }}%
                    @else
                        {{ number_format((float) ($preview['value'] ?? 0), 2, ',', '.') }} €
                    @endif
                </p>
            @elseif(($preview['type'] ?? '') === 'progress')
                <p class="text-sm text-surface-500">{{ $preview['periodLabel'] ?? '' }}</p>
                <p class="mt-2 text-2xl font-semibold text-surface-900">
                    {{ number_format((float) ($preview['value'] ?? 0), 0, ',', '.') }} €
                    <span class="text-base font-normal text-surface-500">
                        / {{ number_format((float) ($preview['threshold'] ?? 0), 0, ',', '.') }} €
                    </span>
                </p>
                <div class="mt-4 h-3 w-full overflow-hidden rounded-full bg-surface-100">
                    <div class="h-full rounded-full bg-primary-500" style="width: {{ min(100, (float) ($preview['percentage'] ?? 0)) }}%"></div>
                </div>
            @else
                <p class="text-sm text-surface-500">Andamento mensile (demo)</p>
                <ul class="mt-4 space-y-2">
                    @foreach(($preview['points'] ?? []) as $point)
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-surface-600">{{ $point['label'] ?? '' }}</span>
                            <span class="font-medium text-surface-900">{{ number_format((float) ($point['value'] ?? 0), 2, ',', '.') }} €</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('register') }}"
               class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-5 py-3 text-sm font-semibold text-white hover:bg-primary-700">
                Registrati gratis
            </a>
            <a href="{{ route('login') }}"
               class="inline-flex items-center justify-center rounded-lg border border-surface-300 px-5 py-3 text-sm font-semibold text-surface-700 hover:bg-surface-50">
                Accedi
            </a>
        </div>
    </section>
@endsection
