User-agent: *
Allow: /

@if(request()->getHost() === \App\Support\PublicSeoUrl::APP_HOST)
Sitemap: {{ \App\Support\PublicSeoUrl::appBase() }}/sitemap-garages.xml
@else
Sitemap: {{ \App\Support\PublicSeoUrl::path('/sitemap.xml') }}
Sitemap: {{ \App\Support\PublicSeoUrl::appBase() }}/sitemap-garages.xml
Sitemap: {{ \App\Support\PublicSeoUrl::path('/sitemap-vehicle-authority.xml') }}
@endif
