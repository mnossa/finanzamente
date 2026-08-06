<x-mail::message>
# Nuove ricorrenze da confermare

Ciao **{{ $user->name }}**,

@if($suggestionsCount === 1)
abbiamo individuato **1 possibile transazione ricorrente** nella household **{{ $household->name }}**.
@else
abbiamo individuato **{{ $suggestionsCount }} possibili transazioni ricorrenti** nella household **{{ $household->name }}**.
@endif

Apri la sezione Rilevamento Ricorrenze per confermare o ignorare i suggerimenti.

<x-mail::button :url="$reviewUrl" color="green">
Vai ai suggerimenti
</x-mail::button>

Grazie,<br>
{{ config('app.name') }}
</x-mail::message>
