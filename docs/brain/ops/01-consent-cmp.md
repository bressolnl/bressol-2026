# Consent & CMP (NL/EU)

Doel: WordPress blijft dataLayer events pushen, maar tags worden pas geactiveerd zodra de CMP toestemming geeft.

## Principes
- WP/Bressol-core: alleen dataLayer pushen, geen analytics scripts.
- CMP + GTM: bepalen of tags mogen afvuren.
- Consent moet per categorie (analytics, marketing) kunnen worden toegepast.

## Aanbevolen CMP-opties (NL/EU)
- Cookiebot (mature, Consent Mode support)
- iubenda (breed EU-compatibel, Consent Mode)
- OneTrust (enterprise)
- CookieYes / CookieHub (budgetvriendelijk)

## Implementatiestrategie
1) CMP laadt als eerste (head).
2) CMP zet consent state (Consent Mode v2).
3) GTM luistert op consent state en vuurt tags pas na toestemming.
4) WP/dataLayer events blijven beschikbaar zodat GTM events kan ophalen zodra consent is gegeven.

## Plaatsing snippets in WordPress
- CMP snippet in `wp_head` via de hook `bressol_cmp_head`.
- Eventuele CMP/consent helpers in `wp_footer` via `bressol_cmp_footer` (alleen indien vereist door de CMP).
- De theme implementeert alleen placeholders, geen scripts.

## Gating van GTM zonder dataLayer te breken
- dataLayer events blijven altijd pushen.
- GTM triggers moeten expliciet consent checken.
- Voorbeeld: "All Pages" trigger + consent requirement "analytics_storage = granted".

## Notities
- Geen analytics scripts rechtstreeks in WordPress.
- Alle pixels/tags via GTM met consent checks.
- Documenteer per tag welke consent categorie nodig is.
