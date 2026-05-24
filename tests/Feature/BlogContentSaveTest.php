<?php

namespace Tests\Feature;

use App\Filament\Resources\Blogs\Pages\EditBlog;
use App\Models\Blog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogContentSaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_rich_blog_content_via_active_edit_blog_page(): void
    {
        $admin = User::factory()->admin()->create();

        $blog = Blog::query()->create([
            'title' => 'Test blog',
            'slug' => 'test-blog',
            'excerpt' => 'Korte samenvatting',
            'content' => '<p>Oude inhoud.</p>',
            'published_at' => now(),
        ]);

        $richHtml = <<<'HTML'
<h2>Onderhoud slim plannen</h2>
<p>GarageBook helpt je om <strong>onderhoud</strong> en <em>historie</em> overzichtelijk vast te leggen.</p>
<p>Lees ook <a href="https://garagebook.nl/blogs">onze blogs</a> voor meer context en voorbeelden uit de praktijk.</p>
<h3>Wat je wilt bewaren</h3>
<ul>
    <li>Onderhoudsmomenten met datum en kilometerstand</li>
    <li>Facturen, onderdelen en terugkerende patronen</li>
    <li>Notities voor toekomstige upgrades</li>
</ul>
<p>Zo blijft de motorhistorie duidelijk voor jezelf en voor een volgende eigenaar.</p>
HTML;

        $this->actingAs($admin);

        Livewire::test(EditBlog::class, ['record' => $blog->getRouteKey()])
            ->fillForm([
                'content' => $richHtml,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $blog->refresh();

        $this->assertStringContainsString('<h2>Onderhoud slim plannen</h2>', $blog->content);
        $this->assertStringContainsString('<p>GarageBook helpt je om <strong>onderhoud</strong> en <em>historie</em> overzichtelijk vast te leggen.</p>', $blog->content);
        $this->assertStringContainsString('>onze blogs</a>', $blog->content);
        $this->assertStringContainsString('href="https://garagebook.nl/blogs"', $blog->content);
        $this->assertStringContainsString('Onderhoudsmomenten met datum en kilometerstand', $blog->content);
        $this->assertStringContainsString('Facturen, onderdelen en terugkerende patronen', $blog->content);
        $this->assertStringContainsString('Notities voor toekomstige upgrades', $blog->content);
        $this->assertStringContainsString('<strong>onderhoud</strong>', $blog->content);
        $this->assertStringContainsString('<em>historie</em>', $blog->content);
    }
}
