<?php

namespace MuazzamBuilds\FilamentAiActions\Concerns;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use MuazzamBuilds\FilamentAiActions\AiActionsPlugin;
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

    protected string | Closure | null $aiModel = null;

    protected bool | Closure $enabled = true;

    protected string | Closure | null $systemPrompt = null;

    protected float | Closure | null $temperature = null;

    protected int | Closure | null $maxTokens = null;

    protected bool | Closure $shouldSaveResult = true;

    protected ?Closure $applyResultUsing = null;

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
        $this->aiModel = $model;

        return $this;
    }

    public function systemPrompt(string | Closure | null $prompt): static
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    public function temperature(float | Closure | null $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function maxTokens(int | Closure | null $maxTokens): static
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function saveResult(bool | Closure $condition = true): static
    {
        $this->shouldSaveResult = $condition;

        return $this;
    }

    public function applyResultUsing(?Closure $callback): static
    {
        $this->applyResultUsing = $callback;

        return $this;
    }

    public function enabled(bool | Closure $condition = true): static
    {
        $this->enabled = $condition;

        return $this;
    }

    public function isAiEnabled(): bool
    {
        $plugin = $this->resolvePlugin();

        return (bool) $this->evaluate($this->enabled)
            && ($plugin?->isEnabled() ?? true)
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
        $model = $this->evaluate($this->aiModel)
            ?? $this->resolvePlugin()?->getModel();
        $temperature = $this->evaluate($this->temperature);
        $maxTokens = $this->evaluate($this->maxTokens);

        return app(OpenAiClient::class)->chat(
            $messages,
            filled($model) ? (string) $model : null,
            is_numeric($temperature) ? (float) $temperature : null,
            is_numeric($maxTokens) ? (int) $maxTokens : null,
        );
    }

    protected function applyResultToRecord(?Model $record, string $result, mixed $livewire = null): void
    {
        $attribute = $this->evaluate($this->applyToAttribute);

        if ($this->applyResultUsing instanceof Closure) {
            $this->evaluate($this->applyResultUsing, [
                'result' => $result,
                'record' => $record,
                'livewire' => $livewire,
            ]);

            return;
        }

        if (! filled($attribute) || ! $record instanceof Model) {
            return;
        }

        $record->setAttribute((string) $attribute, $result);

        if ((bool) $this->evaluate($this->shouldSaveResult)) {
            $record->save();
        }

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
     * @param  array<string, mixed>  $injections
     */
    protected function resolveSystemPrompt(string $default, array $injections = []): string
    {
        if ($this->systemPrompt === null) {
            return $default;
        }

        $prompt = $this->evaluate($this->systemPrompt, $injections);

        if (! is_string($prompt) || blank($prompt)) {
            throw new RuntimeException(__('filament-ai-actions::messages.missing_system_prompt'));
        }

        return trim($prompt);
    }

    protected function resolvePlugin(): ?AiActionsPlugin
    {
        if (! app()->bound('filament')) {
            return null;
        }

        $panel = filament()->getCurrentPanel();

        if (! $panel?->hasPlugin('filament-ai-actions')) {
            return null;
        }

        $plugin = $panel->getPlugin('filament-ai-actions');

        return $plugin instanceof AiActionsPlugin ? $plugin : null;
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
