<x-mail::message>
# Ciao!

{{ $inviterName }} ti ha invitato a unirti alla household **{{ $householdName }}** su Finanzamente.

**Ruolo assegnato:** {{ $role }}

@if($isNewUser)
Per accettare l'invito, dovrai prima creare un account su Finanzamente. Clicca il pulsante qui sotto per registrarti e unirti automaticamente alla household.

<x-mail::button :url="$acceptUrl">
Registrati e accetta l'invito
</x-mail::button>
@else
Clicca il pulsante qui sotto per accettare l'invito e unirti alla household.

<x-mail::button :url="$acceptUrl">
Accetta l'invito
</x-mail::button>
@endif

**Attenzione:** Questo invito scadrà il {{ $expiresAt }}.

Se non hai richiesto questo invito, puoi ignorare questa email.

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
