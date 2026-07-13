<?php

namespace MuazzamBuilds\FilamentAiActions\Concerns;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use MuazzamBuilds\FilamentAiActions\OpenAI\OpenAiClient;
use RuntimeException;
use Throwable;

trait InteractsWithOpenAi
{
    /**
     * @var array<int, string>|Closure|null
     */
    protected array | Closure | null $contentAttributes = null;

    protected string | Closure | null $content = null;

    protected string | Closure | null $applyToAttribute = null;

    protected string | Closure | null $model = null;

    protected bool | Closure $enabled = true;

    /**
     * @param  array<int, string>|Closure  $attributes
     */
    public function attributes(array | Closure $attributes): static
    {
        $this->contentAttributes = $attributes;

        return $this;
    }

    public function content(string | Closure | null $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function applyTo(string | Closure | null $attribute): static
    {
        $this->applyToAttribute = $attribute;

        return $this;
    }

    public function model(string | Closure | null $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function enabled(bool | Closure $condition = true): static
    {
        $this->enabled = $condition;

        return $this;
    }

    public function isAiEnabled(): bool
    {
        return (bool) $this->evaluate($this->enabled)
            && app(OpenAiClient::class)->isConfigured();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function resolveSourceContent(mixed $record = null, array $arguments = []): string
    {
        if ($this->content !== null) {
            $resolved = $this->evaluate($this->content, [
                'record' => $record,
                ...$arguments,
            ]);

            return is_string($resolved) ? trim($resolved) : '';
        }

        $attributes = $this->evaluate($this->contentAttributes) ?? [];

        if (! is_array($attributes) || $attributes === []) {
            return '';
        }

        if (! $record instanceof Model) {
            return '';
        }

        $parts = [];

        foreach ($attributes as $attribute) {
            $value = data_get($record, $attribute);

            if (blank($value)) {
                continue;
            }

            $parts[] = is_scalar($value) ? (string) $value : json_encode($value);
        }

        return trim(implode("\n\n", $parts));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    protected function runChat(array $messages): string
    {
        $model = $this->evaluate($this->model);

        return app(OpenAiClient::class)->chat(
            $messages,
            filled($model) ? (string) $model : null,
        );
    }

    protected function applyResultToRecord(?Model $record, string $result, mixed $livewire = null): void
    {
        $attribute = $this->evaluate($this->applyToAttribute);

        if (! filled($attribute) || ! $record instanceof Model) {
            return;
        }

        $record->setAttribute((string) $attribute, $result);
        $record->save();

        if (is_object($livewire) && method_exists($livewire, 'refreshFormData')) {
            $livewire->refreshFormData([(string) $attribute]);
        }
    }

    protected function notifySuccess(string $title, string $body): void
    {
        Notification::make()
            ->title($title)
            ->body(str($body)->limit(4000)->toString())
            ->success()
            ->persistent()
            ->send();
    }

    protected function notifyFailure(Throwable $exception): void
    {
        Notification::make()
            ->title(__('filament-ai-actions::messages.failed'))
            ->body($exception->getMessage())
            ->danger()
            ->send();
    }

    protected function ensureContent(string $content): void
    {
        if (blank($content)) {
            throw new RuntimeException(__('filament-ai-actions::messages.missing_content'));
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{content: string, instructions: ?string}
     */
    protected function modalPayload(array $data, mixed $record = null): array
    {
        $content = (string) ($data['content'] ?? $this->resolveSourceContent($record));
        $instructions = isset($data['instructions']) ? (string) $data['instructions'] : null;

        return [
            'content' => trim($content),
            'instructions' => filled($instructions) ? trim($instructions) : null,
        ];
    }
}
