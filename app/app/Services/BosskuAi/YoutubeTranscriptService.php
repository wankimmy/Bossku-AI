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

        $scraped = $this->fetchTranscriptFromPlayerPage($videoId);

        return $scraped !== '' ? $scraped : '';
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
            ])->timeout(30)->get($watchUrl);

            if (! $response->successful()) {
                return '';
            }

            $html = $response->body();
            $marker = 'ytInitialPlayerResponse';
            $pos = strpos($html, $marker);
            if ($pos === false) {
                return '';
            }
            $bracePos = strpos($html, '{', $pos);
            if ($bracePos === false) {
                return '';
            }

            $json = $this->extractJsonAt($html, $bracePos);
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            $captionTracks = data_get($data, 'captions.playerCaptionsTracklistRenderer.captionTracks', []);
            if (! is_array($captionTracks) || $captionTracks === []) {
                return '';
            }

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

            $captionResponse = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ])->timeout(30)->get($captionUrl.'&fmt=json3');

            if (! $captionResponse->successful()) {
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
                    if ($txt !== '' && $txt !== '\n') {
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
