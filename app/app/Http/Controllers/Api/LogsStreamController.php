<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogsStreamController extends Controller
{
    private const MAX_INITIAL_BYTES = 50_000;
    private const POLL_INTERVAL_US = 500_000; // 0.5 s
    private const STREAM_TIMEOUT_S = 60;

    public function stream(): StreamedResponse
    {
        $logPath = storage_path('logs/laravel.log');

        return response()->stream(function () use ($logPath) {
            if (ob_get_level()) {
                ob_end_clean();
            }
            ignore_user_abort(false);

            // Initial history
            if (file_exists($logPath) && is_readable($logPath)) {
                foreach ($this->readTailEntries($logPath, self::MAX_INITIAL_BYTES) as $entry) {
                    $this->sseWrite($entry);
                }
            }

            $lastSize = file_exists($logPath) ? (int) filesize($logPath) : 0;
            $deadline = time() + self::STREAM_TIMEOUT_S;
            $partialLine = '';

            while (! connection_aborted() && time() < $deadline) {
                clearstatcache(true, $logPath);
                $currentSize = file_exists($logPath) ? (int) filesize($logPath) : 0;

                if ($currentSize > $lastSize) {
                    $fp = fopen($logPath, 'rb');
                    if ($fp !== false) {
                        fseek($fp, $lastSize);
                        $chunk = fread($fp, $currentSize - $lastSize);
                        fclose($fp);

                        if ($chunk !== false) {
                            $lines = explode("\n", $partialLine.$chunk);
                            $partialLine = (string) array_pop($lines);

                            $pending = '';
                            foreach ($lines as $line) {
                                $line = rtrim($line);
                                if ($line === '') {
                                    continue;
                                }
                                if (str_starts_with($line, '[')) {
                                    if ($pending !== '') {
                                        $entry = $this->parseLine($pending);
                                        if ($entry) {
                                            $this->sseWrite($entry);
                                        }
                                    }
                                    $pending = $line;
                                } else {
                                    $pending .= "\n".$line;
                                }
                            }
                            if ($pending !== '') {
                                $entry = $this->parseLine($pending);
                                if ($entry) {
                                    $this->sseWrite($entry);
                                }
                            }
                        }
                    }
                    $lastSize = $currentSize;
                } elseif ($currentSize < $lastSize) {
                    $lastSize = 0;
                    $partialLine = '';
                }

                echo ": ping\n\n";
                flush();
                usleep(self::POLL_INTERVAL_US);
            }
        }, 200, [
            'Content-Type'     => 'text/event-stream',
            'Cache-Control'    => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'       => 'keep-alive',
        ]);
    }

    /** @return list<array{timestamp: string, channel: string, level: string, message: string, context: mixed}> */
    private function readTailEntries(string $path, int $maxBytes): array
    {
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            return [];
        }

        fseek($fp, 0, SEEK_END);
        $size = (int) ftell($fp);

        if ($size === 0) {
            fclose($fp);
            return [];
        }

        $readFrom = max(0, $size - $maxBytes);
        $toRead = $size - $readFrom;
        fseek($fp, $readFrom);
        $raw = fread($fp, $toRead);
        fclose($fp);

        if ($raw === false || $raw === '') {
            return [];
        }

        $lines = explode("\n", $raw);

        // If we started mid-file, the first line may be a fragment — drop it.
        if ($readFrom > 0) {
            array_shift($lines);
        }

        $rawEntries = [];
        $current = '';
        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }
            if (str_starts_with($line, '[')) {
                if ($current !== '') {
                    $rawEntries[] = $current;
                }
                $current = $line;
            } else {
                $current .= "\n".$line;
            }
        }
        if ($current !== '') {
            $rawEntries[] = $current;
        }

        return array_values(array_filter(array_map([$this, 'parseLine'], $rawEntries)));
    }

    /**
     * Parse a Laravel log line into a structured entry.
     * Format: [YYYY-MM-DD HH:MM:SS] channel.LEVEL: message {optional_json}
     *
     * @return array{timestamp: string, channel: string, level: string, message: string, context: mixed}|null
     */
    private function parseLine(string $line): ?array
    {
        if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.(\w+): (.*)/s', $line, $m)) {
            return null;
        }

        [, $datetime, $channel, $level, $rest] = $m;
        $rest = trim($rest);
        $context = null;
        $message = $rest;

        // Extract trailing JSON context if present
        if (preg_match('/^(.*?)(\s*\{.*\})\s*$/s', $rest, $cm)) {
            $decoded = json_decode(trim($cm[2]), true);
            if (is_array($decoded)) {
                $message = trim($cm[1]);
                $context = $decoded;
            }
        }

        $message = trim($message);
        if ($message === '') {
            $message = '(empty)';
        }
        if (strlen($message) > 500) {
            $message = substr($message, 0, 500).'…';
        }

        return [
            'timestamp' => $datetime,
            'channel'   => $channel,
            'level'     => strtolower($level),
            'message'   => $message,
            'context'   => $context,
        ];
    }

    /** @param array{timestamp: string, channel: string, level: string, message: string, context: mixed} $entry */
    private function sseWrite(array $entry): void
    {
        echo 'data: '.json_encode($entry)."\n\n";
        flush();
    }
}
