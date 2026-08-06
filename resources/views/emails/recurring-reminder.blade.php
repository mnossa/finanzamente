@php
    $formatter = app(\App\Services\RecurringReminderFormatter::class);
    $details = $formatter->format($recurringTransaction, $dueDate);
@endphp
<x-mail::message>
# Promemoria ricorrenza

Ciao {{ $user->name }},

domani (**{{ $dueDate->format('d/m/Y') }}**) è prevista un'**{{ $details['direction_label'] }}** di **{{ $details['amount_formatted'] }}**.

**Categoria:** {{ $details['category_name'] }}

**Causale:** {{ $details['description'] }}

<x-mail::button :url="route('recurring-transactions.show', $recurringTransaction)">
Vedi ricorrenza
</x-mail::button>

Puoi modificare le notifiche dal tuo profilo.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
