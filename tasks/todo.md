# Remove Magazine + keep Pulse only

## Goal
- Remove Magazine + Product Analytics dashboard; keep Pulse
- APP_ADMIN_EMAIL for Pulse/Telescope/isAdmin
- Legacy URLs → 404; drop DB tables; cleanup deps; PR unico

## Plan
- [x] Delete Magazine + Product Analytics code
- [x] Rename admin email; Pulse-only menu
- [x] Catch-all 404 + drop migration
- [x] Privacy bump 2026-07-30-v1
- [x] Remove intervention/image
- [x] make test + pint-check + playwright
- [x] Residual scan
- [ ] PR

## Review
### Cosa
- Magazine + link-suggestions + Unsplash + ImageProcessing + markdownWithNofollow: rimossi
- Product Analytics (dashboard + ingest + table): rimossi; Umami resta
- Admin menu: solo Pulse
- `APP_ADMIN_EMAIL` (fallback legacy `MAGAZINE_ADMIN_EMAIL`)
- Migration drop tables + covers; sitemap senza magazine
- Privacy `2026-07-30-v1`

### Verifica
- PHPUnit: 1029 passed
- Pint: PASS
- Playwright: 281 passed
- Locale: impostare `APP_ADMIN_EMAIL` in `.env` (fallback MAGAZINE_ADMIN_EMAIL ancora ok)
- WFI-119 WIP resta in `git stash` (`WFI-119 WIP before magazine removal`)
