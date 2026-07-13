<?php

namespace MuazzamBuilds\FilamentAiActions\OpenAI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiClient
{
    public function isConfigured(): bool
    {
        return filled($this->apiKey());
    }

    public function apiKey(): ?string
    {
        $key = config('filament-ai-actions.api_key');

        return filled($key) ? (string) $key : null;
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(
        array $messages,
        ?string $model = null,
        ?float $temperature = null,
        ?int $maxTokens = null,
    ): string {
        if (! $this->isConfigured()) {
            throw new RuntimeException(__('filament-ai-actions::messages.missing_api_key'));
        }

        $payload = [
            'model' => $model ?? (string) config('filament-ai-actions.model', 'gpt-4o-mini'),
            'messages' => $messages,
            'temperature' => $temperature ?? (float) config('filament-ai-actions.temperature', 0.4),
            'max_tokens' => $maxTokens ?? (int) config('filament-ai-actions.max_tokens', 1200),
        ];

        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey(),
            'Content-Type' => 'application/json',
        ];

        $organization = config('filament-ai-actions.organization');

        if (filled($organization)) {
            $headers['OpenAI-Organization'] = (string) $organization;
        }

        $url = rtrim((string) config('filament-ai-actions.base_url'), '/') . '/chat/completions';

        try {
            $response = Http::withHeaders($headers)
                ->connectTimeout((int) config('filament-ai-actions.connect_timeout', 10))
                ->timeout((int) config('filament-ai-actions.timeout', 60))
                ->post($url, $payload)
                ->throw();
        } catch (ConnectionException $exception) {
            throw new RuntimeException(__('filament-ai-actions::messages.connection_failed'), previous: $exception);
        } catch (RequestException $exception) {
            $body = $exception->response?->json('error.message')
                ?? $exception->response?->body()
                ?? $exception->getMessage();

            throw new RuntimeException(__('filament-ai-actions::messages.request_failed', [
                'message' => is_string($body) ? $body : __('filament-ai-actions::messages.unknown_error'),
            ]), previous: $exception);
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || blank($content)) {
            throw new RuntimeException(__('filament-ai-actions::messages.empty_response'));
        }

        return trim($content);
    }
}
