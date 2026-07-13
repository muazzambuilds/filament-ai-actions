<?php

namespace MuazzamBuilds\FilamentAiActions\Tests;

use MuazzamBuilds\FilamentAiActions\FilamentAiActionsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentAiActionsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('filament-ai-actions.api_key', 'sk-test');
        $app['config']->set('filament-ai-actions.model', 'gpt-4o-mini');
        $app['config']->set('filament-ai-actions.base_url', 'https://api.openai.com/v1');
    }
}
