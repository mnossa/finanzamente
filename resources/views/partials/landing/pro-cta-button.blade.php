{{--
    Partial: bottone CTA Pro nelle landing page target.
    Gestisce 3 stati in base alla configurazione pre-lancio:
      1. waitlistEnabled  → link alla/lista d'attesa (homepage #piani)
      2. preLaunchMode    → nessun bottone (pro non ancora disponibile)
      3. default          → link a plan.select con label e stile passati

    Parametri obbligatori:
      $label        - testo del bottone (es. "Abbonati a Pro — traccia il tuo portafoglio")
      $umamiEvent   - nome evento Umami (es. "landing-cta-investitori")
      $umamiPosition - posizione evento Umami ("hero" o "footer")
      $classes      - classi Tailwind dell'elemento <a>
--}}
@php
    $waitlistEnabled = $waitlistEnabled ?? false;
    $preLaunchMode   = $preLaunchMode ?? false;
@endphp

@if ($waitlistEnabled)
    <a href="{{ url('/#piani') }}"
       class="{{ $classes }}"
       data-umami-event="{{ $umamiEvent }}-waitlist"
       data-umami-event-position="{{ $umamiPosition }}">
        Unisciti alla lista d'attesa
        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
        </svg>
    </a>
@elseif (!$preLaunchMode && Route::has('plan.select'))
    <a href="{{ route('plan.select') }}?plan=pro&billing_cycle=monthly"
       class="{{ $classes }}"
       data-umami-event="{{ $umamiEvent }}"
       data-umami-event-position="{{ $umamiPosition }}">
        {{ $label }}
        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
        </svg>
    </a>
@endif
