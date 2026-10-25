<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingRedirectRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_marketing_redirects_are_direct_for_get_head_slashes_and_raw_queries(): void
    {
        $targets = [
            'blogs' => 'blog/',
            'ons-verhaal' => 'over-garagebook/',
            'privacy-statement' => 'privacy/',
            'universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026' => 'blog/universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026/',
        ];
        foreach ($targets as $source => $target) {
            foreach (['GET', 'HEAD'] as $method) {
                foreach (['', '/'] as $slash) {
                    foreach (['', '?utm_source=a%20b&tag=one&tag=two&x=%2F'] as $query) {
                        $this->call($method, 'https://app.garagebook.nl/'.$source.$slash.$query)
                            ->assertStatus(301)
                            ->assertHeader('Location', 'https://garagebook.nl/'.$target.$query);
                    }
                }
            }
        }
    }

    public function test_terms_redirect_and_footer_remain_unchanged_pending_verified_destination(): void
    {
        Page::query()->create([
            'title' => 'Voorwaarden', 'slug' => 'algemene-voorwaarden',
            'content' => '<p>Legal fixture unchanged.</p>', 'indexable' => false,
        ]);
        foreach (['GET', 'HEAD'] as $method) {
            foreach (['', '/'] as $slash) {
                $this->call($method, 'https://app.garagebook.nl/algemene-voorwaarden'.$slash.'?x=1')
                    ->assertStatus(301)
                    ->assertHeader('Location', 'https://garagebook.nl/algemene-voorwaarden'.$slash.'?x=1');
            }
        }
        $this->get('https://garagebook.nl/algemene-voorwaarden')
            ->assertOk()
            ->assertSee('Legal fixture unchanged.')
            ->assertSee('noindex,nofollow', false)
            ->assertSee('https://garagebook.nl/algemene-voorwaarden', false);
        $this->assertSame('<p>Legal fixture unchanged.</p>', Page::where('slug', 'algemene-voorwaarden')->firstOrFail()->content);
    }

    public function test_existing_privacy_noindex_and_canonical_are_preserved(): void
    {
        Page::query()->create([
            'title' => 'Privacy', 'slug' => 'privacy-statement',
            'content' => '<p>Privacy fixture.</p>', 'indexable' => false,
        ]);
        $this->get('https://garagebook.nl/privacy-statement')
            ->assertOk()->assertSee('noindex,nofollow', false)
            ->assertSee('https://garagebook.nl/privacy-statement', false);
    }

    public function test_both_footers_link_directly_to_verified_destinations(): void
    {
        $public = view('partials.footer')->render();
        $login = $this->get('https://app.garagebook.nl/admin/login')->assertOk()->getContent();
        foreach ([$public, $login] as $html) {
            foreach (['https://garagebook.nl/', 'https://garagebook.nl/blog/', 'https://garagebook.nl/over-garagebook/', 'https://garagebook.nl/privacy/', '/algemene-voorwaarden'] as $url) {
                $this->assertStringContainsString('href="'.$url.'"', $html);
            }
            foreach (['/blogs', '/ons-verhaal', '/privacy-statement'] as $old) {
                $this->assertStringNotContainsString('href="'.$old.'"', $html);
            }
        }
    }

    public function test_new_rules_do_not_redirect_post_requests_or_nested_protected_paths(): void
    {
        foreach (['blogs', 'ons-verhaal', 'privacy-statement'] as $slug) {
            $this->post('https://app.garagebook.nl/'.$slug)->assertStatus(405)->assertHeaderMissing('Location');
            $this->get('https://app.garagebook.nl/api/'.$slug)->assertNotFound()->assertHeaderMissing('Location');
        }
    }
}
