# Filament AI Actions

OpenAI-powered actions for **Filament v5**. Drop complete **Summarize**, **Rewrite**, **Classify**, **Generate**, and **Translate** actions onto tables and forms — no DIY prompt wiring.

## Requirements

| Dependency | Version |
|---|---|
| PHP | `^8.2` |
| Laravel | `^11` / `^12` |
| Filament | `^5.0` |
| OpenAI | API key |

## Installation

```bash
composer require muazzambuilds/filament-ai-actions
```

Publish config (optional):

```bash
php artisan vendor:publish --tag=filament-ai-actions-config
```

```env
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
```

Optional panel registration:

```php
use MuazzamBuilds\FilamentAiActions\AiActionsPlugin;

$panel->plugin(
    AiActionsPlugin::make()
        ->model('gpt-4o-mini')
        ->enabled(fn (): bool => app()->environment('production'))
);
```

Panel settings are inherited by every action in that panel. Per-action `model()` and `enabled()` settings take precedence or further restrict availability. Registering the plugin is optional; actions fall back to package configuration when no plugin is registered.

## Usage

### Summarize

```php
use MuazzamBuilds\FilamentAiActions\Actions\SummarizeAction;

SummarizeAction::make()
    ->attributes(['title', 'body'])
    ->applyTo('summary'); // optional: write result back to the record
```

### Rewrite

```php
use MuazzamBuilds\FilamentAiActions\Actions\RewriteAction;

RewriteAction::make()
    ->attributes(['body'])
    ->applyTo('body')
    ->tones([
        'professional' => 'Professional',
        'casual' => 'Casual',
        'concise' => 'Concise',
    ]);
```

### Classify

```php
use MuazzamBuilds\FilamentAiActions\Actions\ClassifyAction;

ClassifyAction::make()
    ->attributes(['body'])
    ->labels([
        'bug' => 'Bug report',
        'feature' => 'Feature request',
        'question' => 'Question',
    ])
    ->applyTo('category');
```

Classification only accepts one of the configured label keys (or values for a list). JSON responses such as `{"label":"bug"}` are supported, and invalid or ambiguous model output is never persisted.

### Generate

```php
use MuazzamBuilds\FilamentAiActions\Actions\GenerateAction;

GenerateAction::make()
    ->attributes(['title', 'body']) // optional context
    ->applyTo('generated_copy');
```

The modal asks for a generation prompt and lets the user review or edit the context before submitting.

### Translate

```php
use MuazzamBuilds\FilamentAiActions\Actions\TranslateAction;

TranslateAction::make()
    ->attributes(['body'])
    ->defaultLanguage('es')
    ->applyTo('translated_body');
```

The language field is a searchable dropdown containing all 183 ISO 639-1 languages by default. Use `languages([...])` only when you want to restrict or customize the available targets:

```php
TranslateAction::make()
    ->languages([
        'en' => 'English',
        'es' => 'Spanish',
        'ur' => 'Urdu',
    ]);
```

### Custom content source

```php
SummarizeAction::make()
    ->content(fn ($record) => "Title: {$record->title}\n\n{$record->body}");
```

### Generation controls and result handling

All actions support per-action model controls and custom prompts:

```php
SummarizeAction::make()
    ->model('gpt-4o')
    ->temperature(0.2)
    ->maxTokens(600)
    ->systemPrompt(fn ($record) => "Summarize this {$record->type} for executives.")
    ->applyTo('summary');
```

`applyTo()` saves the model by default. To fill the model and refresh an open Filament form without saving it automatically, use `saveResult(false)`:

```php
RewriteAction::make()
    ->attributes(['body'])
    ->applyTo('body')
    ->saveResult(false);
```

For complete control, replace the default assignment and persistence behavior:

```php
GenerateAction::make()
    ->applyResultUsing(function (string $result, $record, $livewire): void {
        // Store, transform, dispatch, or audit the result.
    });
```

The generated result is also shown in a persistent success notification. `saveResult(false)` is the recommended option when a user must review and save changes manually.

### Table example

```php
use Filament\Tables\Table;
use MuazzamBuilds\FilamentAiActions\Actions\ClassifyAction;
use MuazzamBuilds\FilamentAiActions\Actions\GenerateAction;
use MuazzamBuilds\FilamentAiActions\Actions\RewriteAction;
use MuazzamBuilds\FilamentAiActions\Actions\SummarizeAction;
use MuazzamBuilds\FilamentAiActions\Actions\TranslateAction;

public function table(Table $table): Table
{
    return $table
        ->recordActions([
            SummarizeAction::make()->attributes(['title', 'body'])->applyTo('summary'),
            RewriteAction::make()->attributes(['body'])->applyTo('body'),
            ClassifyAction::make()
                ->attributes(['body'])
                ->labels(['bug', 'feature', 'question'])
                ->applyTo('category'),
            GenerateAction::make()->attributes(['title', 'body'])->applyTo('draft'),
            TranslateAction::make()->attributes(['body'])->applyTo('translated_body'),
        ]);
}
```

Actions hide themselves when `OPENAI_API_KEY` is missing, when the panel plugin is disabled, or when their own `enabled()` condition evaluates to false.

## Configuration

```php
// config/filament-ai-actions.php
return [
    'api_key' => env('OPENAI_API_KEY'),
    'organization' => env('OPENAI_ORGANIZATION'),
    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
    'temperature' => 0.4,
    'max_tokens' => 1200,
];
```


## Support

If this package helps you, consider supporting development:

[![Buy Me A Coffee](https://img.shields.io/badge/Buy%20Me%20a%20Coffee-ffdd00?style=for-the-badge&logo=buy-me-a-coffee&logoColor=black)](https://buymeacoffee.com/muazzambuilds)

## License
MIT — see [LICENSE](LICENSE).
