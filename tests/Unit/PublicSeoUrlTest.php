<?php

namespace Tests\Unit;

use App\Support\PublicSeoUrl;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PublicSeoUrlTest extends TestCase
{
    public function test_base_uses_public_site_url_config_without_trailing_slash(): void
    {
        Config::set('app.public_site_url', 'https://garagebook.nl/');

        $this->assertSame('https://garagebook.nl', PublicSeoUrl::base());
    }

    public function test_garage_url_uses_apex_host_without_querystring_or_trailing_slash(): void
    {
        Config::set('app.public_site_url', 'https://garagebook.nl/');
        Config::set('app.url', 'https://app.garagebook.nl');

        $url = PublicSeoUrl::garage('/mijn publieke slug?utm_source=test/');

        $this->assertSame('https://garagebook.nl/garage/mijn%20publieke%20slug', $url);
        $this->assertStringNotContainsString('app.garagebook.nl/garage', $url);
        $this->assertStringNotContainsString('//', parse_url($url, PHP_URL_PATH));
        $this->assertFalse(str_ends_with($url, '/'));
    }
}
