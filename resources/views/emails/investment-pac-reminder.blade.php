<x-mail::message>
# Promemoria PAC

Ciao {{ $user->name }},

{{ $details['message'] }}

<x-mail::button :url="route('investment-pacs.show', $pac)">
Apri dettaglio PAC
</x-mail::button>

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
