@props([
    'title',
    'subtitle' => null,
    'updatedAt' => now()->format('d/m/Y'),
])

<section class="pt-12 sm:pt-16 lg:pt-20 bg-surface-50">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 max-w-5xl my-8">
        <div class="rounded-3xl border border-surface-200 bg-white shadow-soft-md overflow-hidden">
            <div class="px-6 py-8 sm:px-10 sm:py-10 border-b border-surface-100 bg-gradient-to-br from-white via-primary-50/40 to-accent-50/40">
                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary-700 mb-3">Documentazione legale</p>
                <h1 class="text-3xl sm:text-4xl font-bold text-surface-900">{{ $title }}</h1>
                @if ($subtitle)
                    <p class="mt-4 max-w-3xl text-sm sm:text-base text-surface-600 leading-relaxed">{{ $subtitle }}</p>
                @endif
                <p class="mt-4 text-xs sm:text-sm text-surface-500">Ultimo aggiornamento: {{ $updatedAt }}</p>
            </div>

            <div class="px-6 py-6 sm:px-10">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900 leading-relaxed">
                    <strong>Nota operativa:</strong> completa prima della pubblicazione i dati del titolare del trattamento, i tempi di conservazione definitivi, la disciplina su rimborsi e il fornitore di hosting/mail realmente usato in produzione.
                </div>
            </div>

            <article class="px-6 pb-10 sm:px-10 sm:pb-12 space-y-10 text-sm sm:text-base text-surface-700 leading-7">
                {{ $slot }}
            </article>
        </div>
    </div>
</section>