<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Page;
use Illuminate\Support\Collection;

class InternalContentLinks
{
    public const FEATURED_PAGE_SLUG = 'universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026';

    private const RELATED_BLOG_SLUGS = [
        'digitaal-onderhoudsboekje-voor-je-motor-wat-is-het-en-hoe-werkt-het' => [
            'waarom-een-universeel-onderhoudsboekje-achterhaald-is-en-wat-je-beter-kunt-gebruiken',
            'digitaal-onderhoudsboekje-voor-je-motor-van-papieren-boekje-naar-complete-historie',
            'hoe-een-complete-onderhoudshistorie-de-verkoopwaarde-van-je-motor-verhoogt',
        ],
        'waarom-een-universeel-onderhoudsboekje-achterhaald-is-en-wat-je-beter-kunt-gebruiken' => [
            'digitaal-onderhoudsboekje-voor-je-motor-wat-is-het-en-hoe-werkt-het',
            'digitaal-onderhoudsboekje-voor-je-motor-van-papieren-boekje-naar-complete-historie',
            'hoe-een-complete-onderhoudshistorie-de-verkoopwaarde-van-je-motor-verhoogt',
        ],
        'hoe-een-complete-onderhoudshistorie-de-verkoopwaarde-van-je-motor-verhoogt' => [
            'motor-verkopen-dit-doet-een-goede-onderhoudshistorie-met-je-prijs',
            'onderhoudshistorie-van-je-motor-kwijt-dit-kun-je-doen-en-voorkomen-in-de-toekomst',
            'digitaal-onderhoudsboekje-voor-je-motor-wat-is-het-en-hoe-werkt-het',
        ],
    ];

    private const FEATURED_PAGE_RELATED_BLOG_SLUGS = [
        'waarom-een-universeel-onderhoudsboekje-achterhaald-is-en-wat-je-beter-kunt-gebruiken',
        'digitaal-onderhoudsboekje-voor-je-motor-wat-is-het-en-hoe-werkt-het',
        'digitaal-onderhoudsboekje-voor-je-motor-van-papieren-boekje-naar-complete-historie',
        'hoe-een-complete-onderhoudshistorie-de-verkoopwaarde-van-je-motor-verhoogt',
    ];

    public static function featuredPage(): ?Page
    {
        return Page::query()
            ->where('slug', static::FEATURED_PAGE_SLUG)
            ->first();
    }

    public static function relatedBlogsForBlog(Blog $blog, int $limit = 3): Collection
    {
        $relatedSlugs = static::RELATED_BLOG_SLUGS[$blog->slug] ?? null;

        if ($relatedSlugs !== null) {
            return static::blogsBySlugPriority($relatedSlugs, $limit);
        }

        return Blog::query()
            ->whereNotNull('published_at')
            ->whereKeyNot($blog->getKey())
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public static function relatedBlogsForFeaturedPage(int $limit = 4): Collection
    {
        $relatedBlogs = static::blogsBySlugPriority(static::FEATURED_PAGE_RELATED_BLOG_SLUGS, $limit);

        if ($relatedBlogs->isNotEmpty()) {
            return $relatedBlogs;
        }

        return Blog::query()
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    private static function blogsBySlugPriority(array $slugs, int $limit): Collection
    {
        $slugOrder = array_values(array_unique($slugs));

        $blogs = Blog::query()
            ->whereNotNull('published_at')
            ->whereIn('slug', $slugOrder)
            ->get()
            ->keyBy('slug');

        return collect($slugOrder)
            ->map(fn (string $slug) => $blogs->get($slug))
            ->filter()
            ->take($limit)
            ->values();
    }
}
