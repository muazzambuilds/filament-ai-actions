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
