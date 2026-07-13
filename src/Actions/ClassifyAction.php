<?php

namespace MuazzamBuilds\FilamentAiActions\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use MuazzamBuilds\FilamentAiActions\Concerns\InteractsWithOpenAi;
use RuntimeException;
use Throwable;

class ClassifyAction extends Action
{
    use InteractsWithOpenAi;

    /**
     * @var array<int|string, string>|Closure|null
     */
    protected array | Closure | null $labels = null;

    public static function getDefaultName(): ?string
    {
        return 'aiClassify';
    }

    /**
     * @param  array<int|string, string>|Closure  $labels
     */
    public function labels(array | Closure $labels): static
    {
        $this->labels = $labels;

        return $this;
    }

    /**
     * @return array<int|string, string>
     */
    public function getLabels(): array
    {
        $labels = $this->evaluate($this->labels);

        return is_array($labels) ? $labels : [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => __('filament-ai-actions::messages.classify.label'));

        $this->icon(Heroicon::OutlinedTag);

        $this->modalHeading(fn (): string => __('filament-ai-actions::messages.classify.modal_heading'));

        $this->modalDescription(fn (): string => __('filament-ai-actions::messages.classify.modal_description'));

        $this->modalSubmitActionLabel(fn (): string => __('filament-ai-actions::messages.classify.submit'));

        $this->visible(fn (): bool => $this->isAiEnabled());

        $this->fillForm(function (?Model $record = null): array {
            return [
                'content' => $this->resolveSourceContent($record),
            ];
        });

        $this->schema([
            Textarea::make('content')
                ->label(__('filament-ai-actions::messages.classify.content'))
                ->rows(8)
                ->required(),
            Textarea::make('instructions')
                ->label(__('filament-ai-actions::messages.classify.instructions'))
                ->placeholder(__('filament-ai-actions::messages.classify.instructions_placeholder'))
                ->rows(3),
        ]);

        $this->action(function (array $data, ?Model $record = null, mixed $livewire = null): void {
            try {
                $payload = $this->modalPayload($data, $record);
                $this->ensureContent($payload['content']);

                $labels = $this->getLabels();

                if (count($labels) < 2) {
                    throw new RuntimeException(__('filament-ai-actions::messages.missing_labels'));
                }

                $labelList = collect($labels)
                    ->map(function (string $label, int | string $key): string {
                        return is_string($key) && ! is_numeric($key)
                            ? "{$key}: {$label}"
                            : $label;
                    })
                    ->values()
                    ->implode(', ');

                $allowed = collect($labels)
                    ->map(fn (string $label, int | string $key): string => is_string($key) && ! is_numeric($key) ? (string) $key : $label)
                    ->values()
                    ->all();

                $system = 'You are a precise classifier. Choose exactly one label from this list: '
                    . $labelList
                    . '. Reply with only the label value'
                    . (array_is_list($labels) ? '' : ' key')
                    . ', with no explanation.';

                if (filled($payload['instructions'])) {
                    $system .= ' Additional instructions: ' . $payload['instructions'];
                }

                $result = $this->runChat([
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $payload['content']],
                ]);

                $normalized = $this->normalizeLabel($result, $allowed);

                $this->applyResultToRecord($record, $normalized, $livewire);

                $this->notifySuccess(
                    __('filament-ai-actions::messages.classify.success'),
                    __('filament-ai-actions::messages.classify.result_label', ['label' => $normalized]),
                );
            } catch (Throwable $exception) {
                $this->notifyFailure($exception);
            }
        });
    }

    /**
     * @param  array<int, string>  $allowed
     */
    protected function normalizeLabel(string $result, array $allowed): string
    {
        $candidate = trim($result, " \t\n\r\0\x0B\"'`");

        foreach ($allowed as $label) {
            if (strcasecmp($candidate, $label) === 0) {
                return $label;
            }
        }

        foreach ($allowed as $label) {
            if (str_contains(mb_strtolower($candidate), mb_strtolower($label))) {
                return $label;
            }
        }

        return $candidate;
    }
}
