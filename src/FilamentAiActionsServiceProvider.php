<?php

namespace MuazzamBuilds\FilamentAiActions;

use MuazzamBuilds\FilamentAiActions\OpenAI\OpenAiClient;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class FilamentAiActionsServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-ai-actions';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(static::$name)
            ->hasConfigFile('filament-ai-actions')
            ->hasTranslations();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OpenAiClient::class);
    }
}
