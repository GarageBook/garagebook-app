<?php

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicUrlCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_www_public_page_redirects_to_apex_without_trailing_slash(): void
    {
        Page::query()->create([
            'title' => 'Motor onderhoud schema',
            'slug' => 'motor-onderhoud-schema',
            'content' => '<p>Body</p>',
        ]);

        $this->get('http://www.garagebook.nl/motor-onderhoud-schema/')
            ->assertRedirect('https://garagebook.nl/motor-onderhoud-schema');
    }

    public function test_apex_public_page_without_trailing_slash_still_works(): void
    {
        Page::query()->create([
            'title' => 'Motor onderhoud schema',
            'slug' => 'motor-onderhoud-schema',
            'content' => '<p>Body</p>',
        ]);

        $this->get('https://garagebook.nl/motor-onderhoud-schema')
            ->assertOk()
            ->assertSee('Motor onderhoud schema');
    }
}
