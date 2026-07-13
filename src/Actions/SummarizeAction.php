<?php

namespace MuazzamBuilds\FilamentAiActions\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use MuazzamBuilds\FilamentAiActions\Concerns\InteractsWithOpenAi;
use Throwable;

class SummarizeAction extends Action
{
    use InteractsWithOpenAi;

    public static function getDefaultName(): ?string
    {
        return 'aiSummarize';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => __('filament-ai-actions::messages.summarize.label'));

        $this->icon(Heroicon::OutlinedSparkles);

        $this->modalHeading(fn (): string => __('filament-ai-actions::messages.summarize.modal_heading'));

        $this->modalDescription(fn (): string => __('filament-ai-actions::messages.summarize.modal_description'));

        $this->modalSubmitActionLabel(fn (): string => __('filament-ai-actions::messages.summarize.submit'));

        $this->visible(fn (): bool => $this->isAiEnabled());

        $this->fillForm(function (?Model $record = null): array {
            return [
                'content' => $this->resolveSourceContent($record),
            ];
        });

        $this->schema([
            Textarea::make('content')
                ->label(__('filament-ai-actions::messages.summarize.content'))
                ->rows(8)
                ->required(),
            Textarea::make('instructions')
                ->label(__('filament-ai-actions::messages.summarize.instructions'))
                ->placeholder(__('filament-ai-actions::messages.summarize.instructions_placeholder'))
                ->rows(3),
        ]);

        $this->action(function (array $data, ?Model $record = null, mixed $livewire = null): void {
            try {
                $payload = $this->modalPayload($data, $record);
                $this->ensureContent($payload['content']);

                $system = 'You are a careful editor. Summarize the user content clearly and accurately. '
                    . 'Return only the summary, with no preamble.';

                if (filled($payload['instructions'])) {
                    $system .= ' Additional instructions: ' . $payload['instructions'];
                }

                $result = $this->runChat([
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $payload['content']],
                ]);

                $this->applyResultToRecord($record, $result, $livewire);
                $this->notifySuccess(__('filament-ai-actions::messages.summarize.success'), $result);
            } catch (Throwable $exception) {
                $this->notifyFailure($exception);
            }
        });
    }
}
