<?php

namespace App\Models;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Blog extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'hero_image',
        'published_at',
    ];

    protected static function booted()
    {
        static::creating(function ($blog) {
            if (!$blog->slug) {
                $blog->slug = static::generateUniqueSlug($blog->title);
            }
        });

        static::updating(function ($blog) {
            if ($blog->isDirty('title')) {
                $blog->slug = static::generateUniqueSlug($blog->title);
            }
        });
    }

    protected static function generateUniqueSlug($title)
    {
        $slug = Str::slug($title);
        $original = $slug;
        $i = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }

        return $slug;
    }

    public function getRenderedContentAttribute(): string
    {
        if (!$this->content) {
            return '';
        }

        $previousState = libxml_use_internal_errors(true);

        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="gb-blog-content-root">' . $this->content . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        $root = $document->getElementById('gb-blog-content-root');

        if (!$root instanceof DOMElement) {
            libxml_clear_errors();
            libxml_use_internal_errors($previousState);

            return $this->content;
        }

        foreach ($root->getElementsByTagName('li') as $listItem) {
            $paragraphs = [];

            foreach ($listItem->childNodes as $childNode) {
                if ($childNode instanceof DOMElement && $childNode->tagName === 'p') {
                    $paragraphs[] = $childNode;
                }
            }

            foreach ($paragraphs as $paragraph) {
                $this->unwrapNode($paragraph);
            }
        }

        $this->removeEmptyParagraphs($root);

        $content = '';

        foreach ($root->childNodes as $childNode) {
            $content .= $document->saveHTML($childNode);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousState);

        return $content;
    }

    protected function unwrapNode(DOMNode $node): void
    {
        $parent = $node->parentNode;

        if (!$parent) {
            return;
        }

        while ($node->firstChild) {
            $parent->insertBefore($node->firstChild, $node);
        }

        $parent->removeChild($node);
    }

    protected function removeEmptyParagraphs(DOMNode $node): void
    {
        $children = [];

        foreach ($node->childNodes as $childNode) {
            $children[] = $childNode;
        }

        foreach ($children as $childNode) {
            if ($childNode instanceof DOMElement) {
                $this->removeEmptyParagraphs($childNode);

                if ($childNode->tagName === 'p' && $this->isParagraphEmpty($childNode)) {
                    $childNode->parentNode?->removeChild($childNode);
                }
            }
        }
    }

    protected function isParagraphEmpty(DOMElement $paragraph): bool
    {
        $text = preg_replace('/\x{00A0}|\s+/u', '', $paragraph->textContent ?? '');

        if ($text !== '') {
            return false;
        }

        foreach ($paragraph->childNodes as $childNode) {
            if ($childNode instanceof DOMElement && $childNode->tagName !== 'br') {
                return false;
            }
        }

        return true;
    }
}
