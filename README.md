# Filament AI Actions

OpenAI-powered actions for **Filament v5**. Drop complete **Summarize**, **Rewrite**, and **Classify** actions onto tables and forms — no DIY prompt wiring.

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
);
```

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

### Custom content source

```php
SummarizeAction::make()
    ->content(fn ($record) => "Title: {$record->title}\n\n{$record->body}");
```

### Table example

```php
use Filament\Tables\Table;
use MuazzamBuilds\FilamentAiActions\Actions\ClassifyAction;
use MuazzamBuilds\FilamentAiActions\Actions\RewriteAction;
use MuazzamBuilds\FilamentAiActions\Actions\SummarizeAction;

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
        ]);
}
```

Actions hide themselves when `OPENAI_API_KEY` is missing.

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
