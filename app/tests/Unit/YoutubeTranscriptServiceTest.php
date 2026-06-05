<?php

namespace Tests\Unit;

use App\Services\BosskuAi\YoutubeTranscriptService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class YoutubeTranscriptServiceTest extends TestCase
{
    #[Test]
    public function it_extracts_video_ids_from_common_url_formats(): void
    {
        $service = app(YoutubeTranscriptService::class);

        $this->assertSame('T7KqH7kYnE4', $service->extractVideoId('https://www.youtube.com/watch?v=T7KqH7kYnE4'));
        $this->assertSame('abc123XYZ09', $service->extractVideoId('https://youtu.be/abc123XYZ09'));
        $this->assertSame('shortVid12ab', $service->extractVideoId('https://www.youtube.com/shorts/shortVid12ab'));
        $this->assertNull($service->extractVideoId('https://example.com/not-youtube'));
    }

    #[Test]
    public function it_parses_timedtext_xml(): void
    {
        $xml = '<transcript><text start="0" dur="2">Hello world</text><text start="2" dur="2">Second line</text></transcript>';
        $text = app(YoutubeTranscriptService::class)->parseTranscriptXml($xml);

        $this->assertStringContainsString('Hello world', $text);
        $this->assertStringContainsString('Second line', $text);
    }
}
