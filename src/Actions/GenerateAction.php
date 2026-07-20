<?php

namespace MuazzamBuilds\FilamentAiActions\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use MuazzamBuilds\FilamentAiActions\Concerns\InteractsWithOpenAi;
use Throwable;

class GenerateAction extends Action
{
    use InteractsWithOpenAi;

    public static function getDefaultName(): ?string
    {
        return 'aiGenerate';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => __('filament-ai-actions::messages.generate.label'));
        $this->icon(Heroicon::OutlinedSparkles);
        $this->modalHeading(fn (): string => __('filament-ai-actions::messages.generate.modal_heading'));
        $this->modalDescription(fn (): string => __('filament-ai-actions::messages.generate.modal_description'));
        $this->modalSubmitActionLabel(fn (): string => __('filament-ai-actions::messages.generate.submit'));
        $this->visible(fn (): bool => $this->isAiEnabled());

        $this->fillForm(function (?Model $record = null): array {
            return [
                'context' => $this->resolveSourceContent($record),
            ];
        });

        $this->schema([
            Textarea::make('prompt')
                ->label(__('filament-ai-actions::messages.generate.prompt'))
                ->rows(4)
                ->required(),
            Textarea::make('context')
                ->label(__('filament-ai-actions::messages.generate.context'))
                ->rows(8),
        ]);

        $this->action(function (array $data, ?Model $record = null, mixed $livewire = null): void {
            try {
                $prompt = trim((string) ($data['prompt'] ?? ''));
                $context = trim((string) ($data['context'] ?? $this->resolveSourceContent($record)));
                $this->ensureContent($prompt);

                $system = $this->resolveSystemPrompt(
                    'You are a careful writing assistant. Follow the request and return only the requested content, '
                        . 'with no preamble.',
                    [
                        'context' => $context,
                        'prompt' => $prompt,
                        'record' => $record,
                    ],
                );

                $user = $prompt;

                if (filled($context)) {
                    $user .= "\n\nContext:\n" . $context;
                }

                $result = $this->runChat([
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ]);

                $this->applyResultToRecord($record, $result, $livewire);
                $this->notifySuccess(__('filament-ai-actions::messages.generate.success'), $result);
            } catch (Throwable $exception) {
                $this->failure();
                $this->notifyFailure($exception);
            }
        });
    }
}
