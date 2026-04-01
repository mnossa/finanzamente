<x-mail::message>
# Il tuo piano Pro è scaduto

Ciao **{{ $user->name }}**,

Il tuo piano **Pro** è scaduto e il tuo account è stato portato al piano **Base**.

Le funzionalità Pro non sono più accessibili, ma **tutti i tuoi dati sono al sicuro** e verranno ripristinati non appena rinnoverai il piano.

**Cosa è incluso nel piano Base:**

@foreach($baseFeatures as $feature)
- {{ $feature }}
@endforeach

**Funzionalità Pro che hai perso:**

@foreach($proFeatures as $feature)
- {{ $feature }}
@endforeach

<x-mail::button :url="$upgradeUrl" color="green">
Rinnova il piano Pro
</x-mail::button>

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
