<?php

namespace MuazzamBuilds\FilamentAiActions;

use Closure;
use Filament\Contracts\Plugin;
use Filament\Panel;

class AiActionsPlugin implements Plugin
{
    protected bool | Closure $enabled = true;

    protected string | Closure | null $model = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-ai-actions';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function enabled(bool | Closure $condition = true): static
    {
        $this->enabled = $condition;

        return $this;
    }

    public function model(string | Closure | null $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function isEnabled(): bool
    {
        return (bool) $this->evaluate($this->enabled);
    }

    public function getModel(): ?string
    {
        $model = $this->evaluate($this->model);

        return filled($model) ? (string) $model : null;
    }

    protected function evaluate(mixed $value): mixed
    {
        return $value instanceof Closure ? $value() : $value;
    }
}
