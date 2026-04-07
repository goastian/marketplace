<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

final class SitemapController extends Controller
{
    public function index(): Response
    {
        $locales = ['en', 'es'];
        $assets = Asset::query()
            ->where('status', 'published')
            ->where('approval_status', 'approved')
            ->orderByDesc('updated_at')
            ->get(['slug', 'type', 'updated_at']);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';

        // Home pages.
        foreach ($locales as $locale) {
            $xml .= $this->urlEntry(url("/{$locale}"), $locales, $locale);
        }

        // Extension type listing pages.
        $types = ['theme', 'wallpaper', 'widget', 'animation'];
        foreach ($types as $type) {
            foreach ($locales as $locale) {
                $xml .= $this->urlEntry(url("/{$locale}/extensions?type={$type}"), $locales, $locale);
            }
        }

        // Individual extension pages.
        foreach ($assets as $asset) {
            foreach ($locales as $locale) {
                $xml .= $this->urlEntry(
                    url("/{$locale}/extensions/{$asset->slug}"),
                    $locales,
                    $locale,
                    $asset->updated_at?->toDateString(),
                );
            }
        }

        // Contact page.
        foreach ($locales as $locale) {
            $xml .= $this->urlEntry(url("/{$locale}/contact"), $locales, $locale);
        }

        $xml .= '</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    private function urlEntry(string $loc, array $locales, string $currentLocale, ?string $lastmod = null): string
    {
        $entry = '<url>';
        $entry .= '<loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>';

        if ($lastmod) {
            $entry .= '<lastmod>' . $lastmod . '</lastmod>';
        }

        // Add hreflang alternates.
        foreach ($locales as $alt) {
            $altUrl = str_replace("/{$currentLocale}/", "/{$alt}/", $loc);
            $entry .= '<xhtml:link rel="alternate" hreflang="' . $alt . '" href="' . htmlspecialchars($altUrl, ENT_XML1) . '" />';
        }

        $entry .= '</url>';

        return $entry;
    }
}
