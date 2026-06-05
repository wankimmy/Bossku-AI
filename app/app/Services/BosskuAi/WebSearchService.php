<?php

namespace App\Services\BosskuAi;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;

/**
 * Key-less web search via DuckDuckGo's HTML endpoint, so the assistant can
 * search-then-learn (discover URLs, then run them through the existing
 * fetch → chunk → embed → memory pipeline).
 */
class WebSearchService
{
    /**
     * @return list<array{title: string, url: string, snippet: string}>
     */
    public function search(string $query, int $limit = 8): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $limit = max(1, min($limit, 25));

        $html = $this->fetch($query);
        if ($html === null) {
            return [];
        }

        return array_slice($this->parseResults($html), 0, $limit);
    }

    protected function fetch(string $query): ?string
    {
        // POST to the HTML endpoint — most reliable for server-side requests.
        foreach (['https://html.duckduckgo.com/html/', 'https://lite.duckduckgo.com/lite/'] as $endpoint) {
            try {
                $response = Http::asForm()->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])->timeout(20)->post($endpoint, ['q' => $query, 'kl' => 'us-en']);

                if ($response->successful() && trim($response->body()) !== '') {
                    return $response->body();
                }
            } catch (\Throwable) {
                // try next endpoint
            }
        }

        return null;
    }

    /**
     * @return list<array{title: string, url: string, snippet: string}>
     */
    protected function parseResults(string $html): array
    {
        $doc = new DOMDocument();
        if (! @$doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return [];
        }
        $xp = new DOMXPath($doc);

        $results = [];
        $seen = [];

        // html.duckduckgo.com markup: a.result__a (title+href), a.result__snippet.
        $anchors = $xp->query("//a[contains(concat(' ', normalize-space(@class), ' '), ' result__a ')]");
        if ($anchors !== false) {
            foreach ($anchors as $a) {
                /** @var \DOMElement $a */
                $url = $this->normalizeUrl((string) $a->getAttribute('href'));
                if ($url === '' || isset($seen[$url])) {
                    continue;
                }

                $title = $this->cleanText($a->textContent);
                if ($title === '') {
                    continue;
                }

                $snippet = '';
                $snippetNode = $xp->query(".//ancestor::div[contains(@class,'result')][1]//a[contains(@class,'result__snippet')]", $a);
                if ($snippetNode !== false && $snippetNode->length > 0) {
                    $snippet = $this->cleanText($snippetNode->item(0)->textContent);
                }

                $seen[$url] = true;
                $results[] = ['title' => $title, 'url' => $url, 'snippet' => $snippet];
            }
        }

        // lite.duckduckgo.com fallback markup: plain result links in a table.
        if ($results === []) {
            $liteLinks = $xp->query("//a[contains(@class,'result-link')]");
            if ($liteLinks !== false) {
                foreach ($liteLinks as $a) {
                    /** @var \DOMElement $a */
                    $url = $this->normalizeUrl((string) $a->getAttribute('href'));
                    $title = $this->cleanText($a->textContent);
                    if ($url === '' || $title === '' || isset($seen[$url])) {
                        continue;
                    }
                    $seen[$url] = true;
                    $results[] = ['title' => $title, 'url' => $url, 'snippet' => ''];
                }
            }
        }

        return $results;
    }

    /** Resolve DuckDuckGo redirect links (//duckduckgo.com/l/?uddg=...) to the real URL. */
    protected function normalizeUrl(string $href): string
    {
        if ($href === '') {
            return '';
        }

        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        }

        $host = strtolower((string) parse_url($href, PHP_URL_HOST));
        if (str_contains($host, 'duckduckgo.com')) {
            parse_str((string) parse_url($href, PHP_URL_QUERY), $q);
            if (isset($q['uddg']) && is_string($q['uddg'])) {
                $href = $q['uddg'];
            }
        }

        return filter_var($href, FILTER_VALIDATE_URL) && str_starts_with($href, 'http') ? $href : '';
    }

    protected function cleanText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
