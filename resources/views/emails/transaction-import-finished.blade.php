<x-mail::message>
@if($successful)
# Importazione completata

Ciao **{{ $user->name }}**,

L'importazione delle transazioni è terminata con esito positivo.

**{{ $notificationTitle }}**

{{ $notificationMessage }}

<x-mail::button :url="$transactionsUrl" color="green">
Vai alle transazioni
</x-mail::button>
@else
# Importazione non riuscita

Ciao **{{ $user->name }}**,

L'importazione delle transazioni non è stata completata.

**{{ $notificationTitle }}**

{{ $notificationMessage }}

@if($errorDetail)
<details style="margin-top: 12px;">
<summary style="cursor: pointer; font-weight: 600;">Dettaglio tecnico</summary>
<p style="margin-top: 8px; font-family: ui-monospace, monospace; font-size: 12px; word-break: break-word;">{{ $errorDetail }}</p>
</details>
@endif

<x-mail::button :url="$transactionsUrl" color="primary">
Apri le transazioni
</x-mail::button>
@endif

Se non hai richiesto tu questa operazione, ignora questa email o contatta il supporto.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
