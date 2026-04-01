<x-mail::message>
# Il tuo piano Pro sta per scadere

Ciao **{{ $user->name }}**,

@if($daysRemaining === 1)
Il tuo piano **Pro** scade **domani**, il **{{ $expiresAt }}**.
@else
Il tuo piano **Pro** scade tra **{{ $daysRemaining }} giorni**, il **{{ $expiresAt }}**.
@endif

Dopo questa data, il tuo account passerà automaticamente al piano **Base** e perderai l'accesso alle seguenti funzionalità:

@foreach($proFeatures as $feature)
- {{ $feature }}
@endforeach

**I tuoi dati non verranno eliminati**, ma alcune funzionalità risulteranno bloccate fino al rinnovo.

<x-mail::button :url="$renewUrl" color="green">
Rinnova il piano Pro
</x-mail::button>

Se hai già rinnovato o vuoi saperne di più, visita la pagina del tuo abbonamento.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
