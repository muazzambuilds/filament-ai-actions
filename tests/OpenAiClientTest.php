<?php

namespace MuazzamBuilds\FilamentAiActions\Tests;

use Illuminate\Support\Facades\Http;
use MuazzamBuilds\FilamentAiActions\OpenAI\OpenAiClient;
use RuntimeException;

class OpenAiClientTest extends TestCase
{
    public function test_it_returns_chat_completion_content(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '  Hello summary  ']],
                ],
            ]),
        ]);

        $result = app(OpenAiClient::class)->chat([
            ['role' => 'user', 'content' => 'Summarize this'],
        ]);

        $this->assertSame('Hello summary', $result);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/chat/completions')
                && $request['model'] === 'gpt-4o-mini'
                && $request->hasHeader('Authorization', 'Bearer sk-test');
        });
    }

    public function test_it_throws_when_api_key_missing(): void
    {
        config(['filament-ai-actions.api_key' => null]);

        $this->expectException(RuntimeException::class);

        app(OpenAiClient::class)->chat([
            ['role' => 'user', 'content' => 'Hi'],
        ]);
    }

    public function test_whitespace_api_key_is_not_configured(): void
    {
        config(['filament-ai-actions.api_key' => '   ']);

        $client = app(OpenAiClient::class);

        $this->assertFalse($client->isConfigured());
        $this->assertNull($client->apiKey());
    }

    public function test_it_sends_custom_generation_options_and_organization(): void
    {
        config([
            'filament-ai-actions.base_url' => 'https://gateway.example.test/v1/',
            'filament-ai-actions.organization' => 'org-test',
        ]);
        Http::fake([
            'gateway.example.test/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Custom response']],
                ],
            ]),
        ]);

        app(OpenAiClient::class)->chat(
            [['role' => 'user', 'content' => 'Hi']],
            model: 'custom-model',
            temperature: 0.0,
            maxTokens: 42,
        );

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://gateway.example.test/v1/chat/completions'
                && $request->hasHeader('OpenAI-Organization', 'org-test')
                && $request['model'] === 'custom-model'
                && $request['temperature'] === 0.0
                && $request['max_tokens'] === 42;
        });
    }

    public function test_it_wraps_api_errors_with_the_provider_message(): void
    {
        Http::fake([
            '*' => Http::response([
                'error' => ['message' => 'Rate limit reached'],
            ], 429),
        ]);

        try {
            app(OpenAiClient::class)->chat([
                ['role' => 'user', 'content' => 'Hi'],
            ]);

            $this->fail('Expected the OpenAI request to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Rate limit reached', $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }

    public function test_it_throws_on_empty_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => '']],
                ],
            ]),
        ]);

        $this->expectException(RuntimeException::class);

        app(OpenAiClient::class)->chat([
            ['role' => 'user', 'content' => 'Hi'],
        ]);
    }
}
