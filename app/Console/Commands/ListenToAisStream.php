<?php

namespace App\Console\Commands;

use App\Models\Vessel;
use App\Services\AisPositionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;
use WebSocket\Client;
use WebSocket\Configuration;
use WebSocket\Connection;
use WebSocket\Message\Message;
use WebSocket\Middleware\CloseHandler;
use WebSocket\Middleware\CompressionExtension;
use WebSocket\Middleware\CompressionExtension\DeflateCompressor;
use WebSocket\Middleware\PingResponder;

class ListenToAisStream extends Command
{
    protected $signature = 'aisstream:listen
        {--max-messages=0 : Stop after this many recognized vessel position reports; zero keeps listening}';

    protected $description = 'Listen for AISStream position reports and update ongoing vessel voyages';

    private int $recognizedMessages = 0;

    private bool $limitReached = false;

    public function handle(AisPositionService $positions): int
    {
        if (! (bool) config('services.aisstream.enabled')) {
            $this->error('AISStream is disabled. Set AISSTREAM_ENABLED=true and clear the config cache.');

            return self::FAILURE;
        }

        $apiKey = trim((string) config('services.aisstream.api_key'));
        if ($apiKey === '') {
            $this->error('AISSTREAM_API_KEY is missing. Keep it in the server-side .env file.');

            return self::FAILURE;
        }

        $mmsis = Vessel::query()
            ->where('ais_enabled', true)
            ->whereNotNull('mmsi')
            ->orderBy('id')
            ->pluck('mmsi')
            ->map(fn ($mmsi): string => (string) $mmsi)
            ->filter(fn (string $mmsi): bool => preg_match('/^\d{9}$/', $mmsi) === 1)
            ->unique()
            ->values()
            ->all();

        if ($mmsis === []) {
            $this->error('No AIS-enabled vessel with a valid nine-digit MMSI is configured. Run the migrations first.');

            return self::FAILURE;
        }

        $subscription = json_encode([
            'APIKey' => $apiKey,
            'BoundingBoxes' => config('services.aisstream.bounding_boxes'),
            'FiltersShipMMSI' => $mmsis,
            'FilterMessageTypes' => [
                'PositionReport',
                'StandardClassBPositionReport',
                'ExtendedClassBPositionReport',
            ],
        ], JSON_THROW_ON_ERROR);

        $this->info('AISStream listener starting for MMSI: '.implode(', ', $mmsis));
        $this->line('Press Ctrl+C to stop the local coverage test.');

        $maximumBackoff = max(5, (int) config('services.aisstream.reconnect_max_seconds', 60));
        $backoff = 1;

        while (! $this->limitReached) {
            try {
                $client = $this->client($positions);
                $client->connect();
                $client->text($subscription);
                $this->info('Connected and subscribed. Waiting for vessel reports...');
                $backoff = 1;
                $client->start(30);
            } catch (Throwable $exception) {
                Log::warning('AISStream listener disconnected.', [
                    'exception' => $exception,
                    'retry_seconds' => $backoff,
                ]);

                if ($this->limitReached) {
                    break;
                }

                $this->warn("AISStream connection interrupted: {$exception->getMessage()}");
            }

            if (! $this->limitReached) {
                $delay = min($maximumBackoff, $backoff) + random_int(0, min(3, $backoff));
                $this->line("Reconnecting in {$delay} second(s)...");
                sleep($delay);
                $backoff = min($maximumBackoff, $backoff * 2);
            }
        }

        $this->info("Coverage test stopped after {$this->recognizedMessages} recognized position report(s).");

        return self::SUCCESS;
    }

    private function client(AisPositionService $positions): Client
    {
        $client = new Client(
            (string) config('services.aisstream.endpoint'),
            new Configuration(timeout: 30)
        );

        $client
            ->addMiddleware(new CompressionExtension(new DeflateCompressor))
            ->addMiddleware(new CloseHandler)
            ->addMiddleware(new PingResponder);

        $process = function (Client $client, Connection $connection, Message $message) use ($positions): void {
            try {
                $payload = json_decode($message->getContent(), true, flags: JSON_THROW_ON_ERROR);
            } catch (Throwable $exception) {
                Log::notice('AISStream sent an unreadable message.', ['exception' => $exception]);

                return;
            }

            if (($payload['MessageType'] ?? null) === 'SubscriptionConfirmation') {
                $compression = data_get($payload, 'Message.CompressionEnabled') ? 'enabled' : 'not negotiated';
                $this->line("Subscription confirmed; compression {$compression}.");

                return;
            }

            try {
                $result = $positions->ingest($payload);
            } catch (Throwable $exception) {
                Log::error('AISStream position could not be stored.', [
                    'message_type' => $payload['MessageType'] ?? null,
                    'mmsi' => data_get($payload, 'MetaData.MMSI'),
                    'exception' => $exception,
                ]);
                $this->error('A received AIS position could not be stored. Check laravel.log.');

                return;
            }

            if (in_array($result['status'], ['recorded', 'refreshed'], true)) {
                $this->recognizedMessages++;
                $verb = $result['status'] === 'recorded' ? 'Track saved' : 'Live marker refreshed';
                $this->line(sprintf(
                    '[%s] %s: %s (MMSI %s, voyage %s)',
                    now()->format('Y-m-d H:i:s'),
                    $verb,
                    $result['vessel'] ?? 'Unknown vessel',
                    $result['mmsi'] ?? '-',
                    $result['voyage_id'] ?? '-'
                ));
            } elseif (($result['reason'] ?? null) === 'no_ongoing_voyage') {
                $this->line(sprintf(
                    '[%s] AIS received for %s, but it has no ongoing voyage.',
                    now()->format('Y-m-d H:i:s'),
                    $result['vessel'] ?? ($result['mmsi'] ?? 'unknown vessel')
                ));
            }

            $maximumMessages = max(0, (int) $this->option('max-messages'));
            if ($maximumMessages > 0 && $this->recognizedMessages >= $maximumMessages) {
                $this->limitReached = true;
                $client->stop();
                $client->disconnect();
            }
        };

        return $client
            ->onBinary($process)
            ->onText($process)
            ->onError(function (Client $client, ?Connection $connection, Throwable $exception): void {
                Log::warning('AISStream WebSocket error.', ['exception' => $exception]);
            });
    }
}
