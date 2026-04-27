<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Page;
use Illuminate\Support\Collection;

class InternalContentLinks
{
    public const FEATURED_PAGE_SLUG = 'universeel-onderhoudsboekje-kopen-dit-is-het-beste-alternatief-2026';

    public static function featuredPage(): ?Page
    {
        return Page::query()
            ->where('slug', static::FEATURED_PAGE_SLUG)
            ->first();
    }

    public static function relatedBlogsForBlog(Blog $blog, int $limit = 3): Collection
    {
        return Blog::query()
            ->whereNotNull('published_at')
            ->whereKeyNot($blog->getKey())
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    public static function relatedBlogsForFeaturedPage(int $limit = 4): Collection
    {
        return Blog::query()
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }
}
