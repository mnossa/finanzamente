<x-mail::message>
# Prossime scadenze

Ciao {{ $user->name }},

{!! nl2br(e($summaryMessage)) !!}

<x-mail::button :url="route('transactions.index')">
Vedi prossimi movimenti
</x-mail::button>

Puoi modificare la frequenza delle notifiche dal tuo profilo.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
