<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FinanzaMente - Gestisci le tue finanze con intelligenza</title>
    <meta name="description" content="FinanzaMente è l'app di gestione finanziaria personale pensata per te. Controlla le tue spese, pianifica il futuro e raggiungi i tuoi obiettivi finanziari con semplicità.">
    
    <!-- Preconnect for performance -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
    
    <!-- Vite for CSS -->
    @vite(['resources/css/app.css'])
</head>
<body class="antialiased bg-surface-50 text-surface-900">
    <!-- Header / Navigation -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-surface-200 transition-all duration-300">
        <nav class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 sm:h-20">
                <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 bg-gradient-to-br from-primary-600 to-primary-800 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="text-xl sm:text-2xl font-bold bg-gradient-to-r from-primary-700 to-primary-900 bg-clip-text text-transparent">FinanzaMente</span>
                </div>
                
                <!-- Navigation Links -->
                <div class="flex items-center space-x-2 sm:space-x-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-3 sm:px-4 py-2 text-sm font-medium text-primary-700 hover:text-primary-900 transition-colors">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="inline-flex items-center px-3 sm:px-4 py-2 text-sm font-medium text-surface-700 hover:text-primary-700 transition-colors">
                                Accedi
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="inline-flex items-center px-4 sm:px-6 py-2 sm:py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-primary-600 to-primary-700 hover:from-primary-700 hover:to-primary-800 rounded-lg shadow-md hover:shadow-lg transition-all duration-200">
                                    Registrati gratis
                                </a>
                            @endif
                        @endauth
                    @endif
                </div>
            </div>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="relative pt-24 sm:pt-32 pb-12 sm:pb-20 overflow-hidden">
        <!-- Background decoration -->
        <div class="absolute inset-0 bg-gradient-to-br from-primary-50 via-white to-accent-50 opacity-60" aria-hidden="true"></div>
        <div class="absolute top-0 right-0 -translate-y-12 translate-x-12 w-72 h-72 bg-primary-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        <div class="absolute bottom-0 left-0 translate-y-12 -translate-x-12 w-72 h-72 bg-accent-200 rounded-full blur-3xl opacity-20" aria-hidden="true"></div>
        
        <div class="relative container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <!-- Main headline -->
                <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-surface-900 leading-tight mb-4 sm:mb-6">
                    Gestisci le tue finanze con
                    <span class="bg-gradient-to-r from-primary-600 to-primary-800 bg-clip-text text-transparent">intelligenza</span>
                </h1>
                
                <!-- Subheadline -->
                <p class="text-base sm:text-lg md:text-xl text-surface-600 mb-6 sm:mb-8 max-w-2xl mx-auto leading-relaxed">
                    FinanzaMente ti aiuta a prendere il controllo delle tue spese, risparmiare di più e raggiungere i tuoi obiettivi finanziari. Semplice, intuitivo, pensato per te.
                </p>
                
                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center mb-8 sm:mb-12">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-white bg-gradient-to-r from-accent-600 to-accent-700 hover:from-accent-700 hover:to-accent-800 rounded-xl shadow-accent hover:shadow-accent-lg transition-all duration-200 transform hover:-translate-y-0.5">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            Inizia gratis ora
                        </a>
                    @endif
                    <a href="#come-funziona" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-medium text-primary-700 bg-white hover:bg-surface-50 rounded-xl border-2 border-primary-200 hover:border-primary-300 transition-all duration-200">
                        Scopri come funziona
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </a>
                </div>
                
                <!-- Trust indicators -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 sm:gap-8 text-sm text-surface-600">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-accent-600 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Gratis e senza pubblicità
                    </div>
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-accent-600 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        Dati sicuri e privati
                    </div>
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-accent-600 mr-2" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z" />
                        </svg>
                        Facile da usare
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-12 sm:py-20 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Tutto quello che ti serve per gestire il tuo denaro
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Funzionalità pensate per semplificarti la vita finanziaria
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 max-w-6xl mx-auto">
                <!-- Feature 1 -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Traccia ogni spesa</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Registra entrate e uscite in pochi secondi. Organizza per categorie e tieni tutto sotto controllo.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-accent-500 to-accent-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Budget intelligenti</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Imposta budget mensili e ricevi notifiche quando stai per superarli. Risparmia senza sforzo.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Gestione conti multipli</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Collega tutti i tuoi conti correnti, carte e portafogli. Visualizza il saldo totale in un colpo d'occhio.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-accent-500 to-accent-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Obiettivi finanziari</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Definisci obiettivi di risparmio e monitora i progressi. Realizza i tuoi progetti più importanti.
                    </p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-500 to-primary-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Gestione familiare</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Condividi le finanze con partner o famiglia. Collabora e mantieni tutto organizzato insieme.
                    </p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-surface-50 rounded-2xl p-6 sm:p-8 border border-surface-200 hover:border-primary-300 hover:shadow-soft-lg transition-all duration-300">
                    <div class="w-12 h-12 bg-gradient-to-br from-accent-500 to-accent-700 rounded-xl flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Report dettagliati</h3>
                    <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                        Analizza le tue abitudini di spesa con grafici e statistiche. Prendi decisioni informate.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works Section -->
    <section id="come-funziona" class="py-12 sm:py-20 bg-gradient-to-br from-primary-50 to-accent-50">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-10 sm:mb-16">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-3 sm:mb-4">
                    Come funziona
                </h2>
                <p class="text-base sm:text-lg text-surface-600">
                    Inizia in 3 semplici passi
                </p>
            </div>

            <div class="max-w-4xl mx-auto">
                <div class="space-y-6 sm:space-y-8">
                    <!-- Step 1 -->
                    <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6 bg-white rounded-2xl p-6 sm:p-8 shadow-soft-md">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-primary-600 to-primary-800 rounded-full flex items-center justify-center text-white text-xl sm:text-2xl font-bold shadow-lg">
                                1
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Crea il tuo account</h3>
                            <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                                Registrati gratuitamente in pochi secondi. Non serve carta di credito, non ci sono costi nascosti.
                            </p>
                        </div>
                    </div>

                    <!-- Step 2 -->
                    <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6 bg-white rounded-2xl p-6 sm:p-8 shadow-soft-md">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-accent-600 to-accent-800 rounded-full flex items-center justify-center text-white text-xl sm:text-2xl font-bold shadow-lg">
                                2
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Configura i tuoi conti</h3>
                            <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                                Aggiungi i tuoi conti correnti, carte e portafogli. Inserisci il saldo iniziale e sei pronto a partire.
                            </p>
                        </div>
                    </div>

                    <!-- Step 3 -->
                    <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6 bg-white rounded-2xl p-6 sm:p-8 shadow-soft-md">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 bg-gradient-to-br from-primary-600 to-primary-800 rounded-full flex items-center justify-center text-white text-xl sm:text-2xl font-bold shadow-lg">
                                3
                            </div>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg sm:text-xl font-semibold text-surface-900 mb-2">Inizia a tracciare</h3>
                            <p class="text-sm sm:text-base text-surface-600 leading-relaxed">
                                Registra le tue spese quotidiane, imposta budget e obiettivi. FinanzaMente fa il resto, offrendoti insights preziosi.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="py-12 sm:py-20 bg-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 sm:gap-12 items-center">
                    <!-- Text content -->
                    <div class="order-2 lg:order-1">
                        <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-surface-900 mb-4 sm:mb-6">
                            Perché scegliere FinanzaMente?
                        </h2>
                        <div class="space-y-4 sm:space-y-6">
                            <div class="flex items-start gap-3 sm:gap-4">
                                <div class="flex-shrink-0 w-6 h-6 sm:w-8 sm:h-8 bg-accent-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-accent-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg font-semibold text-surface-900 mb-1">100% Gratuito</h3>
                                    <p class="text-sm sm:text-base text-surface-600">Tutte le funzionalità sono gratis, sempre. Nessun piano premium, nessun costo nascosto.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 sm:gap-4">
                                <div class="flex-shrink-0 w-6 h-6 sm:w-8 sm:h-8 bg-accent-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-accent-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg font-semibold text-surface-900 mb-1">Privato e sicuro</h3>
                                    <p class="text-sm sm:text-base text-surface-600">I tuoi dati sono criptati e protetti. Non vendiamo informazioni a terzi, mai.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 sm:gap-4">
                                <div class="flex-shrink-0 w-6 h-6 sm:w-8 sm:h-8 bg-accent-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-accent-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg font-semibold text-surface-900 mb-1">Intuitivo e veloce</h3>
                                    <p class="text-sm sm:text-base text-surface-600">Interfaccia pensata per l'Italia, mobile-first, facile da usare ovunque ti trovi.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3 sm:gap-4">
                                <div class="flex-shrink-0 w-6 h-6 sm:w-8 sm:h-8 bg-accent-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-accent-700" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base sm:text-lg font-semibold text-surface-900 mb-1">Fatto per l'Italia</h3>
                                    <p class="text-sm sm:text-base text-surface-600">Euro, formato italiano, totalmente in lingua italiana. Progettato per utenti italiani.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Visual/Mockup placeholder -->
                    <div class="order-1 lg:order-2">
                        <div class="relative rounded-2xl bg-gradient-to-br from-primary-100 to-accent-100 p-8 sm:p-12 aspect-square flex items-center justify-center shadow-soft-lg">
                            <div class="text-center">
                                <svg class="w-24 h-24 sm:w-32 sm:h-32 mx-auto mb-4 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                <p class="text-lg sm:text-xl font-semibold text-primary-800">Sicurezza & Privacy</p>
                                <p class="text-sm sm:text-base text-primary-600 mt-2">I tuoi dati sono protetti</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-12 sm:py-20 bg-gradient-to-br from-primary-600 to-primary-800 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-3 sm:mb-4">
                    Pronto a prendere il controllo delle tue finanze?
                </h2>
                <p class="text-base sm:text-lg text-primary-100 mb-6 sm:mb-8 max-w-2xl mx-auto">
                    Unisciti a migliaia di italiani che stanno già gestendo meglio il loro denaro con FinanzaMente.
                </p>
                <div class="flex flex-col sm:flex-row gap-3 sm:gap-4 justify-center items-center">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-semibold text-primary-700 bg-white hover:bg-surface-50 rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 transform hover:-translate-y-0.5">
                            Registrati gratis
                            <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </a>
                    @endif
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 sm:py-4 text-base sm:text-lg font-medium text-white hover:text-primary-100 transition-colors">
                            Hai già un account? Accedi
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-surface-900 text-surface-300 py-8 sm:py-12">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
                    <!-- Brand -->
                    <div>
                        <div class="flex items-center space-x-2 mb-4">
                            <div class="w-8 h-8 bg-gradient-to-br from-primary-600 to-primary-800 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                            <span class="text-lg font-bold text-white">FinanzaMente</span>
                        </div>
                        <p class="text-sm text-surface-400">
                            Gestisci le tue finanze con intelligenza. Gratuito, sicuro, italiano.
                        </p>
                    </div>

                    <!-- Links - Prodotto -->
                    <div>
                        <h3 class="text-white font-semibold mb-3 sm:mb-4">Prodotto</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#" class="hover:text-white transition-colors">Funzionalità</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Come funziona</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Sicurezza</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">FAQ</a></li>
                        </ul>
                    </div>

                    <!-- Links - Supporto -->
                    <div>
                        <h3 class="text-white font-semibold mb-3 sm:mb-4">Supporto</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#" class="hover:text-white transition-colors">Centro assistenza</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Contattaci</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Guide</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Blog</a></li>
                        </ul>
                    </div>

                    <!-- Links - Legale -->
                    <div>
                        <h3 class="text-white font-semibold mb-3 sm:mb-4">Legale</h3>
                        <ul class="space-y-2 text-sm">
                            <li><a href="#" class="hover:text-white transition-colors">Privacy Policy</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Termini di servizio</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">Cookie Policy</a></li>
                            <li><a href="#" class="hover:text-white transition-colors">GDPR</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Bottom footer -->
                <div class="border-t border-surface-800 pt-6 sm:pt-8 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <p class="text-sm text-surface-400 text-center sm:text-left">
                        &copy; {{ date('Y') }} FinanzaMente. Tutti i diritti riservati.
                    </p>
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-surface-400">Fatto con ❤️ in Italia</span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Smooth scroll script -->
    <script>
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    const headerOffset = 80;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>
