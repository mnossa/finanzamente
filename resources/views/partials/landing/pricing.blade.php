{{--
    Partial condiviso: sezione prezzi per le landing page target.
    Variabili necessarie: $plans, $proEnabled, $annualDiscountPercent
--}}
<section id="piani" class="py-12 sm:py-20 bg-white" aria-labelledby="pricing-title-{{ $targetId ?? 'default' }}">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto text-center mb-8 sm:mb-12">
            <h2 id="pricing-title-{{ $targetId ?? 'default' }}" class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                Semplice e trasparente
            </h2>
            <p class="text-base sm:text-lg text-surface-600">
                Inizia gratis con il piano Base. Passa a Pro per sbloccare le funzionalità avanzate.
            </p>
        </div>

        @php
            $basePlan = $plans['base'] ?? null;
            $proPlan = $plans['pro'] ?? null;
            $proMonthly = $proPlan ? $proPlan['price_monthly'] : 0;
            $proAnnualMonthly = $proPlan ? $proPlan['price_annual_monthly'] : 0;
            $proAnnualTotal = $proPlan ? $proPlan['price_annual_total'] : 0;
            $discount = $annualDiscountPercent;
            $toggleId = 'billing-toggle-' . ($targetId ?? 'default');
            $thumbId = 'toggle-thumb-' . ($targetId ?? 'default');
            $labelMonthlyId = 'label-monthly-' . ($targetId ?? 'default');
            $labelAnnualId = 'label-annual-' . ($targetId ?? 'default');
            $proPriceId = 'pro-price-' . ($targetId ?? 'default');
            $proAnnualInfoId = 'pro-annual-info-' . ($targetId ?? 'default');
            $proCtaId = 'pro-cta-' . ($targetId ?? 'default');
        @endphp

        {{-- Toggle mensile/annuale (solo se Pro è disponibile) --}}
        @if($proEnabled && $proPlan)
        <div class="flex justify-center mb-10">
            <div class="inline-flex items-center gap-4 bg-surface-50 rounded-full px-6 py-3 border border-surface-200">
                <span id="{{ $labelMonthlyId }}" class="text-sm font-medium text-surface-900">Mensile</span>
                <button
                    type="button"
                    id="{{ $toggleId }}"
                    role="switch"
                    aria-checked="false"
                    aria-labelledby="{{ $labelMonthlyId }} {{ $labelAnnualId }}"
                    class="relative inline-flex h-6 w-11 items-center rounded-full bg-surface-200 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    <span id="{{ $thumbId }}" class="inline-block h-4 w-4 transform rounded-full bg-white shadow translate-x-1 transition-transform duration-200"></span>
                </button>
                <span id="{{ $labelAnnualId }}" class="text-sm font-medium text-surface-400">
                    Annuale
                    <span class="ml-1.5 bg-accent-100 text-accent-700 text-xs font-semibold px-2 py-0.5 rounded-full">-{{ $discount }}%</span>
                </span>
            </div>
        </div>
        @endif

        <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8 items-stretch">
            <!-- Piano Base -->
            @if($basePlan)
            <div class="bg-white rounded-2xl border-2 border-primary-500 p-6 sm:p-8 shadow-soft-md relative flex flex-col">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                    <span class="bg-primary-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">Gratuito</span>
                </div>
                <div class="text-center mb-6">
                    <h3 class="text-xl font-bold text-surface-900 mb-1">FinanzaMente Base</h3>
                    <div class="text-4xl font-extrabold text-primary-600 my-3">€0</div>
                    <p class="text-sm text-surface-500">Per sempre. Nessuna carta richiesta.</p>
                </div>
                <ul class="space-y-3 mb-8 text-sm text-surface-700 flex-1">
                    @foreach($basePlan['features'] as $feature)
                    <li class="flex items-center gap-2"><span class="text-primary-500 font-bold">✓</span> {{ $feature }}</li>
                    @endforeach
                </ul>
                @if (Route::has('plan.select'))
                    <a href="{{ route('plan.select') }}?plan=base" class="block w-full text-center py-3 px-6 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors duration-200">
                        Inizia gratis
                    </a>
                @endif
            </div>
            @endif

            <!-- Piano Pro -->
            @if($proPlan && $proEnabled)
            <div class="bg-gradient-to-b from-accent-600 to-accent-700 rounded-2xl p-6 sm:p-8 shadow-accent relative flex flex-col text-white">
                <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                    <span class="bg-amber-400 text-amber-900 text-xs font-bold px-4 py-1.5 rounded-full uppercase tracking-widest shadow-md">⭐ Consigliato</span>
                </div>
                <div class="text-center mb-6">
                    <h3 class="text-xl font-bold text-white mb-1">FinanzaMente Pro</h3>
                    <div class="my-3">
                        <div class="flex items-baseline justify-center gap-1">
                            <span id="{{ $proPriceId }}" class="text-4xl font-extrabold text-white">
                                {{ number_format($proMonthly, 2, ',', '.') }} €
                            </span>
                            <span class="text-accent-200 text-sm">/mese</span>
                        </div>
                        <div id="{{ $proAnnualInfoId }}" class="hidden mt-1 text-center space-y-0.5">
                            <p class="text-accent-200 text-sm">
                                <span class="line-through">{{ number_format($proMonthly * 12, 2, ',', '.') }} €/anno</span>
                                <span class="text-white font-semibold ml-1">→ {{ number_format($proAnnualTotal, 2, ',', '.') }} €/anno</span>
                            </p>
                            <p class="text-xs text-accent-100">Risparmi {{ number_format($proMonthly * 12 - $proAnnualTotal, 2, ',', '.') }} €/anno</p>
                        </div>
                    </div>
                    <p class="text-sm text-accent-100">{{ $proPlan['label'] }}</p>
                </div>
                <ul class="space-y-3 mb-8 text-sm flex-1">
                    @foreach($proPlan['features'] as $feature)
                    <li class="flex items-center gap-2 text-white"><span class="font-bold">✓</span> {{ $feature }}</li>
                    @endforeach
                </ul>
                @if (Route::has('plan.select'))
                    <a id="{{ $proCtaId }}" href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly" class="block w-full text-center py-3 px-6 bg-white hover:bg-accent-50 text-accent-700 font-semibold rounded-xl transition-colors duration-200">
                        Scegli Pro mensile
                    </a>
                @endif
            </div>
            @elseif($proPlan && !$proEnabled)
            <div class="bg-surface-50 rounded-2xl border-2 border-dashed border-surface-300 p-6 sm:p-8 relative opacity-70 flex flex-col">
                <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                    <span class="bg-surface-400 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">Presto disponibile</span>
                </div>
                <div class="text-center mb-6">
                    <h3 class="text-xl font-bold text-surface-900 mb-1">FinanzaMente Pro</h3>
                    <div class="text-4xl font-extrabold text-surface-400 my-3">Presto</div>
                    <p class="text-sm text-surface-500">Funzionalità avanzate in arrivo.</p>
                </div>
                <ul class="space-y-3 mb-8 text-sm text-surface-500 flex-1">
                    @foreach($proPlan['features'] as $feature)
                    <li class="flex items-center gap-2"><span class="font-bold">✦</span> {{ $feature }}</li>
                    @endforeach
                </ul>
                <button disabled class="block w-full text-center py-3 px-6 bg-surface-200 text-surface-400 font-semibold rounded-xl cursor-not-allowed">
                    Presto disponibile
                </button>
            </div>
            @endif
        </div>
    </div>
</section>

@if($proEnabled && $proPlan)
@push('scripts')
<script>
(function() {
    var toggle = document.getElementById(@json($toggleId));
    var thumb = document.getElementById(@json($thumbId));
    var labelMonthly = document.getElementById(@json($labelMonthlyId));
    var labelAnnual = document.getElementById(@json($labelAnnualId));
    var proPrice = document.getElementById(@json($proPriceId));
    var proAnnualInfo = document.getElementById(@json($proAnnualInfoId));
    var proCta = document.getElementById(@json($proCtaId));

    if (!toggle) return;

    var isAnnual = false;
    @php
        $priceMFmt = number_format($proMonthly, 2, ',', '.') . ' €';
        $priceAMFmt = number_format($proAnnualMonthly, 2, ',', '.') . ' €';
    @endphp
    var priceMonthly = @json($priceMFmt);
    var priceAnnualMonthly = @json($priceAMFmt);
    var baseSelectUrl = @json(route('plan.select'));

    toggle.addEventListener('click', function() {
        isAnnual = !isAnnual;
        toggle.setAttribute('aria-checked', isAnnual ? 'true' : 'false');
        thumb.style.transform = isAnnual ? 'translateX(1.5rem)' : 'translateX(0.25rem)';
        toggle.style.backgroundColor = isAnnual ? 'var(--color-primary-500, #4f4ce5)' : '';
        labelMonthly.style.color = isAnnual ? '' : 'var(--color-surface-900, #0f172a)';
        labelAnnual.style.color = isAnnual ? 'var(--color-surface-900, #0f172a)' : '';
        if (proPrice) proPrice.textContent = isAnnual ? priceAnnualMonthly : priceMonthly;
        if (proAnnualInfo) proAnnualInfo.classList.toggle('hidden', !isAnnual);
        if (proCta) {
            proCta.href = baseSelectUrl + '?plan=pro&billing_cycle=' + (isAnnual ? 'annual' : 'monthly');
            proCta.textContent = isAnnual ? 'Scegli Pro annuale' : 'Scegli Pro mensile';
        }
    });
})();
</script>
@endpush
@endif
