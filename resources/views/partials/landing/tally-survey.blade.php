{{--
    Partial: link + popup Tally.so per il micro-sondaggio di interesse.

    Variabili opzionali:
        $tallyFormId  (string) — ID del form Tally.so (es. "wMeXYZ").
                                 Se vuoto, il partial non renderizza nulla.
        $tallyLabel   (string) — testo del link. Default: "Dimmi cosa ti serve →"
        $tallyClasses (string) — classi CSS aggiuntive per il link.
--}}
@php
    $tallyFormId  = $tallyFormId  ?? config('prelaunch.tally_form_id', '');
    $tallyLabel   = $tallyLabel   ?? 'Dimmi cosa ti serve →';
    $tallyClasses = $tallyClasses ?? '';
    $domClasses = $domClasses ?? '';
@endphp

@if($tallyFormId)
<div class=" mt-3 {{ $domClasses }}">
    <a
        href="javascript:void(0)"
        data-tally-open="{{ $tallyFormId }}"
        data-tally-emoji-text="👋"
        data-tally-emoji-animation="wave"
        data-tally-auto-close="3000"
        class="text-xs text-accent-200 hover:text-white underline underline-offset-2 transition-colors {{ $tallyClasses }}"
    >{{ $tallyLabel }}</a>
</div>

@push('scripts')
<script>
(function() {
    if (typeof Tally !== 'undefined') return;
    var s = document.createElement('script');
    s.src = 'https://tally.so/widgets/embed.js';
    s.async = true;
    document.head.appendChild(s);
})();
</script>
@endpush
@endif
