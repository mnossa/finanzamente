# Setup Unsplash API — Ricerca immagini Magazine

## Passi da seguire

1. Vai su https://unsplash.com/developers e accedi (o registrati)
2. Clicca **"Your apps" → "New Application"**
3. Accetta i termini d'uso
4. Nome app: `Finanzamente Magazine`, descrizione: `Image search for article covers`
5. Copia l'**Access Key** (non la Secret Key)
6. Aggiungi al `.env`:
   ```
   UNSPLASH_ACCESS_KEY=tua_chiave_qui
   ```

## Note
- Rate limit: 50 req/ora (Demo), 5000/ora (Production)
- Attribution gestita automaticamente dal campo `cover_image_credit` nel DB
- Rotta ricerca: `GET /admin/magazine/unsplash-search?q=...` (solo owner)
- L'immagine viene scaricata e salvata localmente al salvataggio dell'articolo
