<?php

namespace MuazzamBuilds\FilamentAiActions\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use MuazzamBuilds\FilamentAiActions\Concerns\InteractsWithOpenAi;
use MuazzamBuilds\FilamentAiActions\Support\LanguageOptions;
use RuntimeException;
use Throwable;

class TranslateAction extends Action
{
    use InteractsWithOpenAi;

    /**
     * @var array<string, string>|Closure|null
     */
    protected array | Closure | null $languages = null;

    protected string | Closure | null $defaultLanguage = null;

    public static function getDefaultName(): ?string
    {
        return 'aiTranslate';
    }

    /**
     * @param  array<string, string>|Closure  $languages
     */
    public function languages(array | Closure $languages): static
    {
        $this->languages = $languages;

        return $this;
    }

    public function defaultLanguage(string | Closure | null $language): static
    {
        $this->defaultLanguage = $language;

        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getLanguages(): array
    {
        $languages = $this->evaluate($this->languages);

        if (is_array($languages) && $languages !== []) {
            return $languages;
        }

        return LanguageOptions::all();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(fn (): string => __('filament-ai-actions::messages.translate.label'));
        $this->icon(Heroicon::OutlinedLanguage);
        $this->modalHeading(fn (): string => __('filament-ai-actions::messages.translate.modal_heading'));
        $this->modalDescription(fn (): string => __('filament-ai-actions::messages.translate.modal_description'));
        $this->modalSubmitActionLabel(fn (): string => __('filament-ai-actions::messages.translate.submit'));
        $this->visible(fn (): bool => $this->isAiEnabled());

        $this->fillForm(function (?Model $record = null): array {
            $languages = $this->getLanguages();
            $default = $this->evaluate($this->defaultLanguage);

            return [
                'content' => $this->resolveSourceContent($record),
                'language' => (filled($default) && array_key_exists((string) $default, $languages))
                    ? (string) $default
                    : array_key_first($languages),
            ];
        });

        $this->schema([
            Textarea::make('content')
                ->label(__('filament-ai-actions::messages.translate.content'))
                ->rows(8)
                ->required(),
            Select::make('language')
                ->label(__('filament-ai-actions::messages.translate.language'))
                ->options(fn (): array => $this->getLanguages())
                ->searchable()
                ->preload()
                ->native(false)
                ->required(),
            Textarea::make('instructions')
                ->label(__('filament-ai-actions::messages.translate.instructions'))
                ->placeholder(__('filament-ai-actions::messages.translate.instructions_placeholder'))
                ->rows(3),
        ]);

        $this->action(function (array $data, ?Model $record = null, mixed $livewire = null): void {
            try {
                $payload = $this->modalPayload($data, $record);
                $this->ensureContent($payload['content']);

                $languages = $this->getLanguages();
                $language = (string) ($data['language'] ?? '');

                if (! array_key_exists($language, $languages)) {
                    throw new RuntimeException(__('filament-ai-actions::messages.translate.invalid_language'));
                }

                $languageLabel = $languages[$language];
                $system = $this->resolveSystemPrompt(
                    "Translate the user's content into {$languageLabel}. Preserve meaning, formatting, names, and factual "
                        . 'details. Return only the translation.',
                    [
                        'content' => $payload['content'],
                        'instructions' => $payload['instructions'],
                        'language' => $language,
                        'languageLabel' => $languageLabel,
                        'record' => $record,
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
                $this->notifySuccess(__('filament-ai-actions::messages.translate.success'), $result);
            } catch (Throwable $exception) {
                $this->failure();
                $this->notifyFailure($exception);
            }
        });
    }
}
