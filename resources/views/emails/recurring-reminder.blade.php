<x-mail::message>
# Promemoria ricorrenza

Ciao {{ $user->name }},

domani (**{{ $dueDate->format('d/m/Y') }}**) è prevista la transazione ricorrente:

**{{ $recurringTransaction->description ?: 'Senza descrizione' }}** — €{{ number_format((float) $recurringTransaction->amount, 2, ',', '.') }}

<x-mail::button :url="route('recurring-transactions.show', $recurringTransaction)">
Vedi ricorrenza
</x-mail::button>

Puoi modificare le notifiche dal tuo profilo.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
