<?php

namespace App\Services\BosskuAi;

use DOMDocument;
use Illuminate\Support\Facades\Http;

class YoutubeTranscriptService
{
    /**
     * Public InnerTube key for the ANDROID client. The ANDROID player endpoint
     * returns caption tracks whose baseUrls download without a consent cookie or
     * PO token, which is the most reliable way to reach auto-generated (ASR)
     * captions from a server IP. Overridable via env if Google rotates it.
     */
    protected function innertubeKey(): string
    {
        return (string) (env('YOUTUBE_INNERTUBE_KEY') ?: 'AIzaSyA8eiZmM1FaDVjRy-df2KTyQ_vz_yYM39w');
    }

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
        } elseif (preg_match('~/embed/([A-Za-z0-9_-]{6,})~', $path, $m)) {
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

    /**
     * Fetch a transcript for a video using its captions — manual or
     * auto-generated (ASR) — translating to English when the only available
     * captions are in another language. Lightweight: no audio download / STT.
     */
    public function fetchTranscript(string $videoId): string
    {
        $tracks = $this->captionTracks($videoId);

        foreach ($this->orderedCandidates($tracks) as $track) {
            $baseUrl = (string) ($track['baseUrl'] ?? '');
            if ($baseUrl === '') {
                continue;
            }

            $lang = strtolower((string) ($track['languageCode'] ?? ''));
            $isEnglish = str_starts_with($lang, 'en');

            // Non-English track: ask YouTube to translate it to English first.
            if (! $isEnglish) {
                $translated = $this->downloadCaptionText($baseUrl, true);
                if ($translated !== '') {
                    return $translated;
                }
            }

            $asIs = $this->downloadCaptionText($baseUrl, false);
            if ($asIs !== '') {
                return $asIs;
            }

            if ($isEnglish) {
                // Last try: force the translation endpoint even for an en track.
                $forced = $this->downloadCaptionText($baseUrl, true);
                if ($forced !== '') {
                    return $forced;
                }
            }
        }

        // Legacy timedtext API as a final fallback.
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
            foreach ([['lang' => $lang], ['lang' => $lang, 'tlang' => 'en']] as $params) {
                $text = $this->fetchTimedtext($videoId, $params);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * Collect caption tracks, trying the reliable InnerTube ANDROID player API
     * first, then falling back to scraping the watch page.
     *
     * @return list<array<string, mixed>>
     */
    protected function captionTracks(string $videoId): array
    {
        $tracks = $this->captionTracksViaInnerTube($videoId);
        if ($tracks !== []) {
            return $tracks;
        }

        return $this->captionTracksViaPlayerPage($videoId);
    }

    /** @return list<array<string, mixed>> */
    protected function captionTracksViaInnerTube(string $videoId): array
    {
        foreach (['ANDROID', 'WEB'] as $client) {
            try {
                $context = $client === 'ANDROID'
                    ? ['clientName' => 'ANDROID', 'clientVersion' => '19.09.37', 'androidSdkVersion' => 30, 'hl' => 'en', 'gl' => 'US']
                    : ['clientName' => 'WEB', 'clientVersion' => '2.20240726.00.00', 'hl' => 'en', 'gl' => 'US'];

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => $client === 'ANDROID'
                        ? 'com.google.android.youtube/19.09.37 (Linux; U; Android 11) gzip'
                        : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                    'Accept-Language' => 'en-US,en;q=0.9',
                ])->timeout(20)->post(
                    'https://www.youtube.com/youtubei/v1/player?key='.$this->innertubeKey(),
                    [
                        'context' => ['client' => $context],
                        'videoId' => $videoId,
                    ],
                );

                if (! $response->successful()) {
                    continue;
                }

                $tracks = data_get($response->json(), 'captions.playerCaptionsTracklistRenderer.captionTracks');
                if (is_array($tracks) && $tracks !== []) {
                    return array_values($tracks);
                }
            } catch (\Throwable) {
                // try next client
            }
        }

        return [];
    }

    /** @return list<array<string, mixed>> */
    protected function captionTracksViaPlayerPage(string $videoId): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
                'Accept-Language' => 'en-US,en;q=0.9',
                // Bypass YouTube GDPR consent wall on server IPs
                'Cookie' => 'CONSENT=YES+1; SOCS=CAESEwgDEgk0NTU5OTkxNjUaAmVuIAEaBgiAo_CmBg==',
            ])->timeout(30)->get('https://www.youtube.com/watch?v='.$videoId);

            if (! $response->successful()) {
                return [];
            }

            $html = $response->body();
            $tracks = $this->extractCaptionTracksFromHtml($html);
            if ($tracks !== []) {
                return $tracks;
            }

            $pos = strpos($html, 'ytInitialPlayerResponse');
            if ($pos !== false) {
                $bracePos = strpos($html, '{', $pos);
                if ($bracePos !== false) {
                    try {
                        $json = $this->extractJsonAt($html, $bracePos);
                        $data = json_decode($json, true, 1024, JSON_THROW_ON_ERROR);
                        $tracks = data_get($data, 'captions.playerCaptionsTracklistRenderer.captionTracks', []);

                        return is_array($tracks) ? array_values($tracks) : [];
                    } catch (\Throwable) {
                        return [];
                    }
                }
            }

            return [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Order candidate tracks: English manual → English ASR → other manual →
     * other ASR, so we use the cleanest source and only translate when needed.
     *
     * @param  list<array<string, mixed>>  $tracks
     * @return list<array<string, mixed>>
     */
    protected function orderedCandidates(array $tracks): array
    {
        $rank = function (array $t): int {
            $lang = strtolower((string) ($t['languageCode'] ?? ''));
            $isEn = str_starts_with($lang, 'en');
            $isAsr = strtolower((string) ($t['kind'] ?? '')) === 'asr';

            return match (true) {
                $isEn && ! $isAsr => 0,
                $isEn && $isAsr => 1,
                ! $isAsr => 2,
                default => 3,
            };
        };

        usort($tracks, fn (array $a, array $b): int => $rank($a) <=> $rank($b));

        return $tracks;
    }

    protected function downloadCaptionText(string $baseUrl, bool $translateToEnglish): string
    {
        $sep = str_contains($baseUrl, '?') ? '&' : '?';
        $suffix = $translateToEnglish ? '&tlang=en' : '';
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0 Safari/537.36',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];

        // json3 carries the cleanest segment text.
        try {
            $json = Http::withHeaders($headers)->timeout(30)->get($baseUrl.$sep.'fmt=json3'.$suffix);
            if ($json->successful()) {
                $text = $this->parseJson3((array) $json->json());
                if ($text !== '') {
                    return $text;
                }
            }
        } catch (\Throwable) {
            //
        }

        // XML fallback.
        try {
            $xml = Http::withHeaders($headers)->timeout(30)->get($baseUrl.($translateToEnglish ? $sep.'tlang=en' : ''));
            if ($xml->successful()) {
                return $this->parseTranscriptXml($xml->body());
            }
        } catch (\Throwable) {
            //
        }

        return '';
    }

    /** @param array<string, mixed> $data */
    protected function parseJson3(array $data): string
    {
        $events = is_array($data['events'] ?? null) ? $data['events'] : [];
        $segments = [];
        foreach ($events as $event) {
            if (! is_array($event['segs'] ?? null)) {
                continue;
            }
            foreach ($event['segs'] as $seg) {
                $txt = (string) ($seg['utf8'] ?? '');
                if (trim($txt) !== '' && $txt !== "\n") {
                    $segments[] = trim($txt);
                }
            }
        }

        return $this->cleanTranscript(implode(' ', $segments));
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

        return $this->cleanTranscript(implode(' ', $lines));
    }

    protected function cleanTranscript(string $text): string
    {
        $text = preg_replace('/\[(Music|Applause|Laughter|Inaudible|Silence)\]/i', '', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
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

                    return is_array($tracks) ? array_values($tracks) : [];
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
