{{--
    Partial condiviso: sezione prezzi.
    Variabili necessarie: $plans, $proEnabled, $annualDiscountPercent
    Variabili opzionali: $targetId (per unicità ID su pagine multiple), $waitlistEnabled
--}}
@php $waitlistEnabled = $waitlistEnabled ?? false; @endphp
<section id="piani" class="py-12 sm:py-20 bg-white" aria-labelledby="pricing-title-{{ $targetId ?? 'default' }}">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto text-center mb-8 sm:mb-12">
            <h2 id="pricing-title-{{ $targetId ?? 'default' }}"
                class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                Semplice e trasparente
            </h2>
            <p class="text-base sm:text-lg text-surface-600">
                @if ($waitlistEnabled)
                    Inizia gratis. Iscriviti alla waitlist per accedere a Pro al lancio.
                @else
                    Inizia gratis. Passa a Pro quando sei pronto.
                @endif
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

        {{-- Toggle mensile/annuale (solo se Pro disponibile e non in modalità waitlist) --}}
        @if ($proEnabled && $proPlan && !$waitlistEnabled)
            <div class="flex justify-center mb-10">
                <div
                    class="inline-flex items-center gap-4 bg-surface-50 rounded-full px-6 py-3 border border-surface-200">
                    <span id="{{ $labelMonthlyId }}" class="text-sm font-medium text-surface-900">Mensile</span>
                    <button type="button" id="{{ $toggleId }}" role="switch" aria-checked="false"
                        aria-labelledby="{{ $labelMonthlyId }} {{ $labelAnnualId }}"
                        class="relative inline-flex h-6 w-11 items-center rounded-full bg-surface-200 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                        <span id="{{ $thumbId }}"
                            class="inline-block h-4 w-4 transform rounded-full bg-white shadow translate-x-1 transition-transform duration-200"></span>
                    </button>
                    <span id="{{ $labelAnnualId }}" class="text-sm font-medium text-surface-400">
                        Annuale
                        <span
                            class="ml-1.5 bg-accent-100 text-accent-700 text-xs font-semibold px-2 py-0.5 rounded-full">-{{ $discount }}%</span>
                    </span>
                </div>
            </div>
        @endif

        <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6 lg:gap-8 items-stretch">
            <!-- Piano Base -->
            @if ($basePlan)
                <div
                    class="bg-white rounded-2xl border-2 border-primary-500 p-6 sm:p-8 shadow-soft-md relative flex flex-col">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span
                            class="bg-primary-500 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">Gratuito</span>
                    </div>
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-bold text-surface-900 mb-1">Finanzamente Base</h3>
                        <div class="text-4xl font-extrabold text-primary-600 my-3">€0</div>
                        <p class="text-sm text-surface-500">Per sempre. Nessuna carta richiesta.</p>
                    </div>
                    @php $baseScope = 'base-' . ($targetId ?? 'default'); @endphp
                    <ul class="space-y-3 mb-8 text-sm text-surface-700 flex-1">
                        @foreach ($basePlan['features'] as $i => $feature)
                            <li
                                class="{{ $i >= 6 ? 'hidden' : '' }} flex items-center gap-2"@if($i >= 6) data-extra="{{ $baseScope }}"@endif>
                                <span class="text-primary-500 font-bold">✓</span> {{ $feature }}
                            </li>
                        @endforeach
                        @if (count($basePlan['features']) > 6)
                            <li>
                                <button type="button" onclick="toggleFeatures(this, '{{ $baseScope }}')"
                                    class="text-primary-600 hover:text-primary-800 text-xs font-medium mt-1 flex items-center gap-1 transition-colors">
                                    <span class="label-more">+ {{ count($basePlan['features']) - 6 }} altre
                                        funzionalità ▾</span>
                                    <span class="label-less hidden">Mostra meno ▲</span>
                                </button>
                            </li>
                        @endif
                    </ul>
                    @if ($waitlistEnabled || config('prelaunch.enabled'))
                        <a href="{{ route('prelaunch.coming-soon') }}"
                            class="block w-full text-center py-3 px-6 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors duration-200">
                            Scopri come accedere
                        </a>
                    @elseif (Route::has('register'))
                        <a href="{{ route('register') }}?plan=base"
                            class="block w-full text-center py-3 px-6 bg-primary-600 hover:bg-primary-700 text-white font-semibold rounded-xl transition-colors duration-200">
                            Inizia gratis
                        </a>
                    @endif
                </div>
            @endif

            <!-- Piano Pro — Modalità waitlist pre-lancio -->
            @if ($proPlan && $waitlistEnabled)
                <div
                    class="bg-gradient-to-b from-accent-600 to-accent-700 rounded-2xl p-6 sm:p-8 shadow-accent relative flex flex-col text-white">
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                        <span
                            class="bg-amber-400 text-amber-900 text-xs font-bold px-4 py-1.5 rounded-full uppercase tracking-widest shadow-md">🚀
                            In arrivo</span>
                    </div>
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-bold text-white mb-1">Finanzamente Pro</h3>
                        <div class="my-3">
                            <div class="flex items-baseline justify-center gap-1">
                                <span class="text-4xl font-extrabold text-white">
                                    {{ number_format($proMonthly, 2, ',', '.') }} €
                                </span>
                                <span class="text-accent-200 text-sm">/mese</span>
                            </div>
                        </div>
                        <p class="text-sm text-accent-100">{{ $proPlan['label'] }}</p>
                    </div>
                    @php $proScope = 'pro-' . ($targetId ?? 'default'); @endphp
                    <ul class="space-y-3 mb-6 text-sm flex-1">
                        @foreach ($proPlan['features'] as $i => $feature)
                            <li
                                class="{{ $i >= 6 ? 'hidden' : '' }} flex items-center gap-2 text-white"@if($i >= 6) data-extra="{{ $proScope }}"@endif>
                                <span class="font-bold">✓</span> {{ $feature }}
                            </li>
                        @endforeach
                        @if (count($proPlan['features']) > 6)
                            <li>
                                <button type="button" onclick="toggleFeatures(this, '{{ $proScope }}')"
                                    class="text-xs text-accent-200 hover:text-white font-medium mt-1 mb-2 flex items-center gap-1 transition-colors">
                                    <span class="label-more">+ {{ count($proPlan['features']) - 6 }} altre funzionalità
                                        ▾</span>
                                    <span class="label-less hidden">Mostra meno ▲</span>
                                </button>
                            </li>
                        @endif
                    </ul>
                    {{-- Form iscrizione waitlist --}}
                    @if (session('waitlist_success'))
                        <div
                            class="rounded-xl bg-white/20 border border-white/30 px-4 py-3 text-center text-sm text-white font-medium">
                            ✅ Iscrizione confermata! Controlla la tua email per confermare.
                        </div>
                    @else
                        <form action="{{ route('waitlist.store') }}" method="POST" class="space-y-3"
                            aria-label="Iscriviti alla waitlist Pro">
                            @csrf
                            <label for="waitlist-email-{{ $targetId ?? 'default' }}" class="sr-only">La tua
                                email</label>
                            <input id="waitlist-email-{{ $targetId ?? 'default' }}" type="email" name="email"
                                required placeholder="La tua email"
                                class="block w-full px-4 py-2.5 rounded-xl bg-white/10 border border-white/30 text-white placeholder-accent-200 focus:outline-none focus:ring-2 focus:ring-white/60 text-sm"
                                value="{{ old('email') }}" autocomplete="email">
                            @error('email')
                                <p class="text-xs text-amber-300">{{ $message }}</p>
                            @enderror
                            <button type="submit"
                                class="block w-full text-center py-3 px-6 bg-white hover:bg-accent-50 text-accent-700 font-semibold rounded-xl transition-colors duration-200">
                                Voglio l'accesso anticipato 🚀
                            </button>
                            <p class="text-xs text-accent-200 text-center">I primi iscritti riceveranno un'offerta riservata al lancio.</p>
                        </form>
                    @endif
                    {{-- Micro-sondaggio Tally (opzionale, attivato da TALLY_SURVEY_FORM_ID) --}}
                    @include('partials.landing.tally-survey', [
                        'tallyLabel' => 'Hai 30 secondi? Dimmi cosa ti serve →',
                        'domClasses' => 'text-center',
                    ])
                </div>

                <!-- Piano Pro — Disponibile -->
            @elseif($proPlan && $proEnabled)
                <div
                    class="bg-gradient-to-b from-accent-600 to-accent-700 rounded-2xl p-6 sm:p-8 shadow-accent relative flex flex-col text-white">
                    <div class="absolute -top-3.5 left-1/2 -translate-x-1/2">
                        <span
                            class="bg-amber-400 text-amber-900 text-xs font-bold px-4 py-1.5 rounded-full uppercase tracking-widest shadow-md">⭐
                            Consigliato</span>
                    </div>
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-bold text-white mb-1">Finanzamente Pro</h3>
                        <div class="my-3">
                            <div class="flex items-baseline justify-center gap-1">
                                <span id="{{ $proPriceId }}" class="text-4xl font-extrabold text-white">
                                    {{ number_format($proMonthly, 2, ',', '.') }} €
                                </span>
                                <span class="text-accent-200 text-sm">/mese</span>
                            </div>
                            <div id="{{ $proAnnualInfoId }}" class="hidden mt-1 text-center space-y-0.5">
                                <p class="text-accent-200 text-sm">
                                    <span class="line-through">{{ number_format($proMonthly * 12, 2, ',', '.') }}
                                        €/anno</span>
                                    <span class="text-white font-semibold ml-1">→
                                        {{ number_format($proAnnualTotal, 2, ',', '.') }} €/anno</span>
                                </p>
                                <p class="text-xs text-accent-100">Risparmi
                                    {{ number_format($proMonthly * 12 - $proAnnualTotal, 2, ',', '.') }} €/anno</p>
                            </div>
                        </div>
                        <p class="text-sm text-accent-100">{{ $proPlan['label'] }}</p>
                    </div>
                    @php $proScope2 = 'pro2-' . ($targetId ?? 'default'); @endphp
                    <ul class="space-y-3 mb-8 text-sm flex-1">
                        @foreach ($proPlan['features'] as $i => $feature)
                            <li
                                class="{{ $i >= 6 ? 'hidden' : '' }} flex items-center gap-2 text-white"@if($i >= 6) data-extra="{{ $proScope2 }}"@endif>
                                <span class="font-bold">✓</span> {{ $feature }}
                            </li>
                        @endforeach
                        @if (count($proPlan['features']) > 6)
                            <li>
                                <button type="button" onclick="toggleFeatures(this, '{{ $proScope2 }}')"
                                    class="text-xs text-accent-200 hover:text-white font-medium mt-1 mb-2 flex items-center gap-1 transition-colors">
                                    <span class="label-more">+ {{ count($proPlan['features']) - 6 }} altre
                                        funzionalità ▾</span>
                                    <span class="label-less hidden">Mostra meno ▲</span>
                                </button>
                            </li>
                        @endif
                    </ul>
                    <a id="{{ $proCtaId }}" href="{{ route('register') }}?plan=pro&billing_cycle=monthly"
                        class="block w-full text-center py-3 px-6 bg-white hover:bg-accent-50 text-accent-700 font-semibold rounded-xl transition-colors duration-200">
                        Scegli Pro mensile
                    </a>
                </div>

                <!-- Piano Pro — Coming soon -->
            @elseif($proPlan && !$proEnabled)
                <div
                    class="bg-surface-50 rounded-2xl border-2 border-dashed border-surface-300 p-6 sm:p-8 relative opacity-70 flex flex-col">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                        <span
                            class="bg-surface-400 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wide">Presto
                            disponibile</span>
                    </div>
                    <div class="text-center mb-6">
                        <h3 class="text-xl font-bold text-surface-900 mb-1">Finanzamente Pro</h3>
                        <div class="text-4xl font-extrabold text-surface-400 my-3">Presto</div>
                        <p class="text-sm text-surface-500">Funzionalità avanzate in arrivo.</p>
                    </div>
                    @php $proScope3 = 'pro3-' . ($targetId ?? 'default'); @endphp
                    <ul class="space-y-3 mb-8 text-sm text-surface-500 flex-1">
                        @foreach ($proPlan['features'] as $i => $feature)
                            <li
                                class="{{ $i >= 6 ? 'hidden' : '' }} flex items-center gap-2"@if($i >= 6) data-extra="{{ $proScope3 }}"@endif>
                                <span class="font-bold">✦</span> {{ $feature }}
                            </li>
                        @endforeach
                        @if (count($proPlan['features']) > 6)
                            <li>
                                <button type="button" onclick="toggleFeatures(this, '{{ $proScope3 }}')"
                                    class="text-xs text-surface-400 hover:text-surface-600 font-medium mt-1 mb-2 flex items-center gap-1 transition-colors">
                                    <span class="label-more">+ {{ count($proPlan['features']) - 6 }} altre
                                        funzionalità ▾</span>
                                    <span class="label-less hidden">Mostra meno ▲</span>
                                </button>
                            </li>
                        @endif
                    </ul>
                    <button disabled
                        class="block w-full text-center py-3 px-6 bg-surface-200 text-surface-400 font-semibold rounded-xl cursor-not-allowed">
                        Presto disponibile
                    </button>
                </div>
            @endif
        </div>
    </div>
</section>
<script>
    if (typeof toggleFeatures === 'undefined') {
        function toggleFeatures(btn, scope) {
            var extras = document.querySelectorAll('[data-extra="' + scope + '"]');
            if (!extras.length) return;
            var isExpanded = !extras[0].classList.contains('hidden');
            extras.forEach(function(el) {
                el.classList.toggle('hidden', isExpanded);
            });
            var labelMore = btn.querySelector('.label-more');
            var labelLess = btn.querySelector('.label-less');
            if (labelMore) labelMore.classList.toggle('hidden', !isExpanded);
            if (labelLess) labelLess.classList.toggle('hidden', isExpanded);
        }
    }
</script>

@if ($proEnabled && $proPlan && !$waitlistEnabled)
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
                var baseRegisterUrl = @json(route('register'));

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
                        proCta.href = baseRegisterUrl + '?plan=pro&billing_cycle=' + (isAnnual ? 'annual' :
                            'monthly');
                        proCta.textContent = isAnnual ? 'Scegli Pro annuale' : 'Scegli Pro mensile';
                    }
                });
            })();
        </script>
    @endpush
@endif
