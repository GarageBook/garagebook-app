# SEO Quality Gate

GarageBook behandelt SEO als release-blocking kwaliteit. Publicaties moeten slagen op:

```bash
vendor/bin/pint
php artisan test
php artisan garagebook:seo-audit
```

## URL Conventies

- Publieke garagepagina's staan op `https://garagebook.nl/garage/{public_slug}`.
- Gebruik geen querystrings in canonicals of sitemaps.
- Gebruik geen trailing slash op app-routes, behalve wanneer een externe marketingroute dit expliciet vereist.
- Gebruik geen `www` host voor app-routes.
- Gebruik altijd `https` voor canonicals.

## Trailing Slash Beleid

- App-routes worden zonder trailing slash geserveerd.
- Requests met trailing slash worden gecanonicaliseerd naar dezelfde URL zonder slash.
- Garagepagina's met querystring redirecten naar de clean canonical URL zonder querystring.

## Canonical Regels

- Elke indexeerbare publieke garagepagina heeft een self-canonical.
- De canonical URL moet exact gelijk zijn aan de sitemap URL.
- Canonicals mogen geen querystring, `http`, `www`, demo-token of oude slug bevatten.
- Afwijkende slugs mogen alleen redirecten als er betrouwbaar een voertuig is gevonden. Er is geen brede slug-guessing fallback.

## Robots Regels

- Indexeerbare pagina's gebruiken `index,follow`.
- Bewust niet-indexeerbare garagepagina's gebruiken `noindex,follow`.
- `noindex` pagina's mogen nooit in sitemap staan.
- Demo/outreach voertuigen zijn nooit indexeerbaar en komen nooit in sitemap.

## Structured Data Regels

- Publieke voertuigpagina's zijn geen verkoopproductpagina's.
- `Product` schema is verboden op `/garage/{slug}` tenzij er echte offers/reviews/aggregateRating zijn en dit expliciet is goedgekeurd.
- Garagepagina's moeten `WebPage` schema bevatten.
- Garagepagina's moeten `Vehicle` schema bevatten wanneer voertuigdata beschikbaar is.
- `BreadcrumbList` mag worden toegevoegd wanneer er zichtbare breadcrumbs zijn.
- `SoftwareApplication` hoort op marketing/app-pagina's, niet per voertuig als Product.

## Sitemap Regels

- Sitemaps moeten bereikbaar zijn en XML met `<loc>` URLs bevatten.
- Alle sitemap URLs moeten direct 200 geven.
- Sitemap URLs mogen niet redirecten.
- Sitemap URLs mogen geen querystring bevatten.
- Sitemap URLs mogen niet duplicate zijn.
- Sitemap URLs mogen geen `noindex` pagina's bevatten.
- Garage sitemap URLs moeten exact overeenkomen met de canonical URL van het voertuig.

## Checklist Voor Nieuwe Pagina's

- Bepaal expliciet of de pagina indexeerbaar is.
- Voeg een unieke title toe.
- Voeg een unieke meta description toe.
- Voeg precies één duidelijke H1 toe.
- Voeg een canonical URL toe die naar zichzelf wijst als de pagina indexeerbaar is.
- Voeg de pagina alleen toe aan sitemap als deze direct 200 geeft en indexeerbaar is.
- Voeg geen Product schema toe tenzij de pagina echt een product met offers/reviews/rating is.
- Draai `php artisan garagebook:seo-audit` voordat je publiceert.

## Commands

```bash
php artisan garagebook:seo-audit
php artisan garagebook:seo-report
```

`garagebook:seo-audit` is de CI/deploy gate. Exitcode `0` is PASS, exitcode `1` is FAIL.

`garagebook:seo-report` schrijft een markdownrapport naar `storage/app/reports/` voor maandelijkse monitoring.
