<?php

namespace MuazzamBuilds\FilamentAiActions\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use MuazzamBuilds\FilamentAiActions\Concerns\InteractsWithOpenAi;
use Throwable;

class RewriteAction extends Action
{
    use InteractsWithOpenAi;

    /**
     * @var array<string, string>|Closure|null
     */
    protected array | Closure | null $tones = null;

    public static function getDefaultName(): ?string
    {
        return 'aiRewrite';
    }

    /**
     * @param  array<string, string>|Closure  $tones
     */
    public function tones(array | Closure $tones): static
    {
        $this->tones = $tones;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getTones(): array
    {
        $tones = $this->evaluate($this->tones);

        if (is_array($tones) && $tones !== []) {
            return $tones;
        }

        return [
            'professional' => __('filament-ai-actions::messages.rewrite.tones.professional'),
            'casual' => __('filament-ai-actions::messages.rewrite.tones.casual'),
            'concise' => __('filament-ai-actions::messages.rewrite.tones.concise'),
            'friendly' => __('filament-ai-actions::messages.rewrite.tones.friendly'),
            'persuasive' => __('filament-ai-actions::messages.rewrite.tones.persuasive'),
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => __('filament-ai-actions::messages.rewrite.label'));

        $this->icon(Heroicon::OutlinedPencilSquare);

        $this->modalHeading(fn (): string => __('filament-ai-actions::messages.rewrite.modal_heading'));

        $this->modalDescription(fn (): string => __('filament-ai-actions::messages.rewrite.modal_description'));

        $this->modalSubmitActionLabel(fn (): string => __('filament-ai-actions::messages.rewrite.submit'));

        $this->visible(fn (): bool => $this->isAiEnabled());

        $this->fillForm(function (?Model $record = null): array {
            return [
                'content' => $this->resolveSourceContent($record),
                'tone' => array_key_first($this->getTones()) ?: 'professional',
            ];
        });

        $this->schema([
            Textarea::make('content')
                ->label(__('filament-ai-actions::messages.rewrite.content'))
                ->rows(8)
                ->required(),
            Select::make('tone')
                ->label(__('filament-ai-actions::messages.rewrite.tone'))
                ->options(fn (): array => $this->getTones())
                ->required(),
            Textarea::make('instructions')
                ->label(__('filament-ai-actions::messages.rewrite.instructions'))
                ->placeholder(__('filament-ai-actions::messages.rewrite.instructions_placeholder'))
                ->rows(3),
        ]);

        $this->action(function (array $data, ?Model $record = null, mixed $livewire = null): void {
            try {
                $payload = $this->modalPayload($data, $record);
                $this->ensureContent($payload['content']);

                $tone = (string) ($data['tone'] ?? 'professional');
                $toneLabel = $this->getTones()[$tone] ?? $tone;

                $system = $this->resolveSystemPrompt(
                    "You are an expert writing assistant. Rewrite the user's content in a {$toneLabel} tone. "
                        . 'Preserve meaning and factual details. Return only the rewritten text.',
                    [
                        'content' => $payload['content'],
                        'instructions' => $payload['instructions'],
                        'record' => $record,
                        'tone' => $tone,
                        'toneLabel' => $toneLabel,
                    ],
                );

                if (filled($payload['instructions'])) {
                    $system .= ' Additional instructions: ' . $payload['instructions'];
                }

                $result = $this->runChat([
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $payload['content']],
                ]);

                $this->applyResultToRecord($record, $result, $livewire);
                $this->notifySuccess(__('filament-ai-actions::messages.rewrite.success'), $result);
            } catch (Throwable $exception) {
                $this->failure();
                $this->notifyFailure($exception);
            }
        });
    }
}
