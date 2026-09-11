<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SemaphoreSmsService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.semaphore.enabled') && filled(config('services.semaphore.api_key'));
    }

    public function normalizePhilippineNumber(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);
        if (str_starts_with($digits, '09') && strlen($digits) === 11) $digits = '63'.substr($digits, 1);
        elseif (str_starts_with($digits, '9') && strlen($digits) === 10) $digits = '63'.$digits;

        return preg_match('/^639\d{9}$/', $digits) ? $digits : null;
    }

    public function send(string $number, string $message): array
    {
        if (! $this->isConfigured()) throw new RuntimeException('Semaphore SMS is not configured.');
        $normalized = $this->normalizePhilippineNumber($number);
        if (! $normalized) throw new RuntimeException('The recipient has an invalid Philippine mobile number.');

        $payload = ['apikey' => config('services.semaphore.api_key'), 'number' => $normalized, 'message' => $message];
        if (filled(config('services.semaphore.sender_name'))) $payload['sendername'] = config('services.semaphore.sender_name');

        $response = Http::asForm()->timeout(15)->retry(2, 300)->post(config('services.semaphore.endpoint'), $payload);
        $response->throw();
        $result = $response->json();
        $messageResult = is_array($result) && array_is_list($result) ? ($result[0] ?? null) : $result;
        if (! is_array($messageResult) || empty($messageResult['message_id'])) throw new RuntimeException('Semaphore returned an invalid response.');
        if (in_array(strtolower((string) ($messageResult['status'] ?? '')), ['failed', 'refunded'], true)) {
            throw new RuntimeException('Semaphore rejected the SMS with status '.($messageResult['status'] ?? 'failed').'.');
        }

        return $messageResult;
    }
}
