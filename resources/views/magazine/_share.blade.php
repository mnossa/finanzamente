<div class="flex flex-wrap gap-3 items-center my-8">
    <span class="text-surface-400 text-sm font-medium">Condividi:</span>
    {{-- FACEBOOK --}}
    <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->fullUrl()) }}" target="_blank"
        rel="noopener nofollow"
        class="inline-flex items-center px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-semibold transition-colors"
        aria-label="Condividi su Facebook"
        onclick="window.trackEvent && trackEvent('share', { platform: 'facebook', article: '{{ addslashes($article->slug) }}' })">
        <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path
                d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" />
        </svg>
    </a>
    {{-- X --}}
    <a href="https://x.com/intent/post?url={{ urlencode(request()->fullUrl()) }}&text={{ urlencode($article->title) }}"
        target="_blank" rel="noopener nofollow"
        class="inline-flex items-center px-3 py-2 rounded-lg bg-surface-100 hover:bg-surface-200 text-surface-700 text-sm font-semibold transition-colors"
        aria-label="Condividi su X"
        onclick="window.trackEvent && trackEvent('share', { platform: 'x', article: '{{ addslashes($article->slug) }}' })">
        <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path
                d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L2.25 2.25h6.844l4.258 5.638L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z" />
        </svg>
    </a>
    {{-- LINKEDIN --}}
    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(request()->fullUrl()) }}" target="_blank"
        rel="noopener nofollow"
        class="inline-flex items-center px-3 py-2 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 text-sm font-semibold transition-colors"
        aria-label="Condividi su LinkedIn"
        onclick="window.trackEvent && trackEvent('share', { platform: 'linkedin', article: '{{ addslashes($article->slug) }}' })">
        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 16 16">
  <path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z"></path>
</svg>
    </a>
    {{-- WHATSAPP --}}
    <a href="https://wa.me/?text={{ urlencode($article->title . ' ' . request()->fullUrl()) }}" target="_blank"
        rel="noopener nofollow"
        class="inline-flex items-center px-3 py-2 rounded-lg bg-green-50 hover:bg-green-100 text-green-700 text-sm font-semibold transition-colors"
        aria-label="Condividi su WhatsApp"
        onclick="window.trackEvent && trackEvent('share', { platform: 'whatsapp', article: '{{ addslashes($article->slug) }}' })">
        <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path
                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.148-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.095 3.2 5.076 4.363.711.306 1.263.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zm-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.999-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884zm8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
        </svg>
    </a>
    {{-- EMAIL --}}
    <a href="mailto:?subject={{ urlencode($article->title) }}&body={{ urlencode(request()->fullUrl()) }}"
        class="inline-flex items-center px-3 py-2 rounded-lg bg-surface-100 hover:bg-surface-200 text-surface-700 text-sm font-semibold transition-colors"
        aria-label="Condividi via email"
        onclick="window.trackEvent && trackEvent('share', { platform: 'email', article: '{{ addslashes($article->slug) }}' })">
        <svg class="w-5 h-5 mr-1" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path
                d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 2v.01L12 13 4 6.01V6h16zM4 20V8.99l8 6.99 8-6.99V20H4z" />
        </svg>
    </a>
</div>
