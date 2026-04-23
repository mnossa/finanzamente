<div class="flex flex-wrap gap-3 items-center my-8">
    <span class="text-surface-400 text-sm font-medium">Condividi:</span>

    {{-- FACEBOOK --}}
    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->fullUrl()) }}"
       target="_blank" rel="noopener nofollow"
       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-semibold transition-colors"
       aria-label="Condividi su Facebook"
       onclick="window.trackEvent && trackEvent('share', { platform: 'facebook', article: '{{ addslashes($article->slug) }}' })">
        <x-simpleicon-facebook class="w-4 h-4 flex-shrink-0" />
        <span class="sr-only">Facebook</span>
    </a>

    {{-- X (ex Twitter) --}}
    <a href="https://x.com/intent/post?url={{ urlencode(request()->fullUrl()) }}&text={{ urlencode($article->title) }}"
       target="_blank" rel="noopener nofollow"
       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-surface-100 hover:bg-surface-200 text-surface-800 text-sm font-semibold transition-colors"
       aria-label="Condividi su X"
       onclick="window.trackEvent && trackEvent('share', { platform: 'x', article: '{{ addslashes($article->slug) }}' })">
        <x-simpleicon-x class="w-4 h-4 flex-shrink-0" />
        <span class="sr-only">X</span>
    </a>

    {{-- LINKEDIN --}}
    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(request()->fullUrl()) }}"
       target="_blank" rel="noopener nofollow"
       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-semibold transition-colors"
       aria-label="Condividi su LinkedIn"
       onclick="window.trackEvent && trackEvent('share', { platform: 'linkedin', article: '{{ addslashes($article->slug) }}' })">
        <x-simpleicon-linkedin class="w-4 h-4 flex-shrink-0" />
        <span class="sr-only">LinkedIn</span>
    </a>

    {{-- WHATSAPP --}}
    <a href="https://wa.me/?text={{ urlencode($article->title . ' ' . request()->fullUrl()) }}"
       target="_blank" rel="noopener nofollow"
       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-green-50 hover:bg-green-100 text-green-700 text-sm font-semibold transition-colors"
       aria-label="Condividi su WhatsApp"
       onclick="window.trackEvent && trackEvent('share', { platform: 'whatsapp', article: '{{ addslashes($article->slug) }}' })">
        <x-simpleicon-whatsapp class="w-4 h-4 flex-shrink-0" />
        <span class="sr-only">WhatsApp</span>
    </a>

    {{-- EMAIL --}}
    <a href="mailto:?subject={{ urlencode($article->title) }}&body={{ urlencode(request()->fullUrl()) }}"
       class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-surface-100 hover:bg-surface-200 text-surface-700 text-sm font-semibold transition-colors"
       aria-label="Condividi via email"
       onclick="window.trackEvent && trackEvent('share', { platform: 'email', article: '{{ addslashes($article->slug) }}' })">
        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
        </svg>
        <span class="sr-only">Email</span>
    </a>
</div>
