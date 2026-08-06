# UX dashboard audit — esito

> Audit post-consolidamento nav Investimenti e widget B2C (2026-06-22).

## Problemi risolti in questo ciclo

| Area | Intervento |
|------|------------|
| Menu Investimenti | Sidebar ridotta a **Investimenti** + **Asset Allocation**; hub con tab (Posizioni, PAC, Allocazione, Asset, Analisi) |
| Transazioni future | Sezione **Prossimi movimenti** con ricorrenze/PAC virtuali, esclusi dal saldo |
| Notifiche | Budget/trend spostati su job schedulato; cap 3 suggerimenti non letti; severità in campanella |
| Simulazioni | Banner verso PAC reali se `pac_active_count > 0` |
| Widget P.IVA | Rimossi in R1 (default dashboard più corto) |

## Metriche di successo (da monitorare)

| Metrica | Target |
|---------|--------|
| Bounce rate voci Investimenti duplicate | −30% click su voci ridondanti |
| Uso sezione Prossimi movimenti | ≥15% sessioni Transazioni senza filtri |
| Notifiche lette entro 7 gg | ≥60% per promemoria PAC/ricorrenze |
| Widget `pac_projection` installato | baseline da misurare dopo 30 gg |

## Backlog residuo

- Legenda classi allocazione vuote (nice-to-have)
- Mappa ISIN statica (solo con API esterna)
- Righe virtuali multi-mese in lista principale (oggi solo sezione dedicata)
