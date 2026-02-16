@props([
    'title' => 'FinanzaMente - Gestisci le tue finanze con intelligenza',
    'description' => 'FinanzaMente è l\'app di gestione finanziaria personale pensata per te. Controlla le tue spese, pianifica il futuro e raggiungi i tuoi obiettivi finanziari con semplicità.',
    'keywords' => 'gestione finanze, budget personale, risparmio, spese, finanza personale, Italia',
    'author' => 'FinanzaMente',
    'ogImage' => null,
])

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="keywords" content="{{ $keywords }}">
<meta name="author" content="{{ $author }}">

<!-- Open Graph / Facebook -->
<meta property="og:type" content="website">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
@if($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
@if($ogImage)
<meta name="twitter:image" content="{{ $ogImage }}">
@endif
