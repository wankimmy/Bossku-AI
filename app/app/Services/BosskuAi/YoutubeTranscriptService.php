<?php

namespace App\Services\BosskuAi;

use DOMDocument;
use Illuminate\Support\Facades\Http;

class YoutubeTranscriptService
{
    public function extractVideoId(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $id = null;
        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif (isset($query['v'])) {
            $id = (string) $query['v'];
        } elseif (preg_match('~/shorts/([A-Za-z0-9_-]{6,})~', $path, $m)) {
            $id = $m[1];
        } elseif (preg_match('/youtu\.be\/([a-zA-Z0-9_-]{11})/', $url, $m)) {
            $id = $m[1];
        } elseif (preg_match('/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $m)) {
            $id = $m[1];
        }

        return $id && preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) ? $id : null;
    }

    public function fetchTitle(string $url, string $videoId): string
    {
        try {
            $oembed = Http::timeout(8)->acceptJson()->get('https://www.youtube.com/oembed', [
                'url' => $url,
                'format' => 'json',
            ]);
            if ($oembed->successful()) {
                $title = trim((string) data_get($oembed->json(), 'title'));
                if ($title !== '') {
                    return $title;
                }
            }
        } catch (\Throwable) {
            //
        }

        return 'YouTube Video '.$videoId;
    }

    public function fetchTranscript(string $videoId): string
    {
        // Player-page scraping is the most reliable: it uses the same caption URLs
        // that the YouTube web player uses, bypasses the deprecated timedtext API,
        // and works for manually-uploaded as well as auto-generated captions.
        $scraped = $this->fetchTranscriptFromPlayerPage($videoId);
        if ($scraped !== '') {
            return $scraped;
        }

        // Fall back to the older timedtext API for videos where scraping fails.
        $attempts = [
            ['lang' => 'en'],
            ['lang' => 'en', 'kind' => 'asr'],
        ];

        foreach ($attempts as $params) {
            $text = $this->fetchTimedtext($videoId, $params);
            if ($text !== '') {
                return $text;
            }
        }

        foreach ($this->listTimedtextLanguages($videoId) as $lang) {
            $text = $this->fetchTimedtext($videoId, ['lang' => $lang]);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    public function parseTranscriptXml(string $xml): string
    {
        $doc = new DOMDocument();
        if (! @$doc->loadXML($xml)) {
            return '';
        }

        $lines = [];
        foreach ($doc->getElementsByTagName('text') as $node) {
            $text = html_entity_decode(trim($node->textContent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return trim(implode(' ', $lines));
    }

    /**
     * @param  array<string, string>  $params
     */
    protected function fetchTimedtext(string $videoId, array $params): string
    {
        try {
            $response = Http::timeout(12)->get('https://video.google.com/timedtext', array_merge([
                'v' => $videoId,
            ], $params));

            if (! $response->successful() || trim($response->body()) === '') {
                return '';
            }

            return $this->parseTranscriptXml($response->body());
        } catch (\Throwable) {
            return '';
        }
    }

    /** @return list<string> */
    protected function listTimedtextLanguages(string $videoId): array
    {
        try {
            $response = Http::timeout(8)->get('https://video.google.com/timedtext', [
                'type' => 'list',
                'v' => $videoId,
            ]);

            if (! $response->successful() || trim($response->body()) === '') {
                return [];
            }

            $doc = new DOMDocument();
            if (! @$doc->loadXML($response->body())) {
                return [];
            }

            $langs = [];
            foreach ($doc->getElementsByTagName('track') as $track) {
                $lang = trim((string) $track->getAttribute('lang_code'));
                if ($lang !== '' && ! in_array($lang, $langs, true)) {
                    $langs[] = $lang;
                }
            }

            return $langs;
        } catch (\Throwable) {
            return [];
        }
    }

    protected function fetchTranscriptFromPlayerPage(string $videoId): string
    {
        try {
            $watchUrl = 'https://www.youtube.com/watch?v='.$videoId;
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
                // Bypass YouTube GDPR consent wall on server IPs
                'Cookie' => 'CONSENT=YES+1; SOCS=CAESEwgDEgk0NTU5OTkxNjUaAmVuIAEaBgiAo_CmBg==',
            ])->timeout(30)->get($watchUrl);

            if (! $response->successful()) {
                return '';
            }

            $html = $response->body();

            // Extract captionTracks directly from the HTML — faster and avoids
            // parsing the full multi-MB ytInitialPlayerResponse object.
            $captionTracks = $this->extractCaptionTracksFromHtml($html);

            // If direct extraction failed, fall back to full JSON parsing.
            if ($captionTracks === []) {
                $marker = 'ytInitialPlayerResponse';
                $pos = strpos($html, $marker);
                if ($pos !== false) {
                    $bracePos = strpos($html, '{', $pos);
                    if ($bracePos !== false) {
                        try {
                            $json = $this->extractJsonAt($html, $bracePos);
                            $data = json_decode($json, true, 1024, JSON_THROW_ON_ERROR);
                            $captionTracks = data_get($data, 'captions.playerCaptionsTracklistRenderer.captionTracks', []);
                        } catch (\Throwable) {
                            $captionTracks = [];
                        }
                    }
                }
            }

            if (! is_array($captionTracks) || $captionTracks === []) {
                return '';
            }

            // Prefer manually-created English captions, then any English, then first track.
            $track = null;
            foreach ($captionTracks as $t) {
                $lang = strtolower((string) ($t['languageCode'] ?? ''));
                $kind = strtolower((string) ($t['kind'] ?? ''));
                if ($lang === 'en' && $kind !== 'asr') {
                    $track = $t;
                    break;
                }
            }
            if ($track === null) {
                foreach ($captionTracks as $t) {
                    if (strtolower((string) ($t['languageCode'] ?? '')) === 'en') {
                        $track = $t;
                        break;
                    }
                }
            }
            $track ??= $captionTracks[0];

            $captionUrl = (string) ($track['baseUrl'] ?? '');
            if ($captionUrl === '') {
                return '';
            }

            $sep = str_contains($captionUrl, '?') ? '&' : '?';
            $captionResponse = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->timeout(30)->get($captionUrl.$sep.'fmt=json3');

            if (! $captionResponse->successful()) {
                // Fall back to XML format
                $captionResponse = Http::withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ])->timeout(30)->get($captionUrl);
                if ($captionResponse->successful()) {
                    return $this->parseTranscriptXml($captionResponse->body());
                }

                return '';
            }

            $captionData = $captionResponse->json();
            $events = is_array($captionData['events'] ?? null) ? $captionData['events'] : [];
            $segments = [];
            foreach ($events as $event) {
                if (! is_array($event['segs'] ?? null)) {
                    continue;
                }
                foreach ($event['segs'] as $seg) {
                    $txt = trim((string) ($seg['utf8'] ?? ''));
                    if ($txt !== '' && $txt !== "\n") {
                        $segments[] = $txt;
                    }
                }
            }

            $transcript = preg_replace('/\[(Music|Applause|Laughter|Inaudible)\]/i', '', implode(' ', $segments));
            $transcript = preg_replace('/\s+/', ' ', $transcript ?? '');

            return trim($transcript ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Extract the captionTracks array directly from the YouTube watch page HTML,
     * avoiding the need to parse the full multi-MB ytInitialPlayerResponse JSON.
     *
     * @return list<array<string, mixed>>
     */
    protected function extractCaptionTracksFromHtml(string $html): array
    {
        $marker = '"captionTracks":';
        $pos = strpos($html, $marker);
        if ($pos === false) {
            return [];
        }

        $bracketPos = strpos($html, '[', $pos + strlen($marker));
        if ($bracketPos === false) {
            return [];
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        // 500 KB is more than enough for the captionTracks array
        $limit = min(strlen($html), $bracketPos + 500_000);

        for ($i = $bracketPos; $i < $limit; $i++) {
            $ch = $html[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\' && $inString) {
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = ! $inString;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $json = substr($html, $bracketPos, $i - $bracketPos + 1);
                    $tracks = json_decode($json, true);

                    return is_array($tracks) ? $tracks : [];
                }
            }
        }

        return [];
    }

    protected function extractJsonAt(string $source, int $startIndex): string
    {
        $depth = 0;
        $inString = false;
        $escape = false;
        $len = min(strlen($source), $startIndex + 3_000_000);

        for ($i = $startIndex; $i < $len; $i++) {
            $ch = $source[$i];
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($ch === '\\' && $inString) {
                $escape = true;
                continue;
            }
            if ($ch === '"') {
                $inString = ! $inString;
                continue;
            }
            if ($inString) {
                continue;
            }
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $startIndex, $i - $startIndex + 1);
                }
            }
        }

        throw new \RuntimeException('Could not find closing brace for JSON extraction.');
    }
}
