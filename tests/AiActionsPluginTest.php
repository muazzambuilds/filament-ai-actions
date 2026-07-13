<?php

namespace MuazzamBuilds\FilamentAiActions\Tests;

use MuazzamBuilds\FilamentAiActions\Actions\ClassifyAction;
use MuazzamBuilds\FilamentAiActions\Actions\RewriteAction;
use MuazzamBuilds\FilamentAiActions\Actions\SummarizeAction;
use MuazzamBuilds\FilamentAiActions\AiActionsPlugin;

class AiActionsPluginTest extends TestCase
{
    public function test_plugin_defaults(): void
    {
        $plugin = AiActionsPlugin::make()->model('gpt-4o');

        $this->assertSame('filament-ai-actions', $plugin->getId());
        $this->assertTrue($plugin->isEnabled());
        $this->assertSame('gpt-4o', $plugin->getModel());
    }

    public function test_actions_have_expected_names(): void
    {
        $this->assertSame('aiSummarize', SummarizeAction::getDefaultName());
        $this->assertSame('aiRewrite', RewriteAction::getDefaultName());
        $this->assertSame('aiClassify', ClassifyAction::getDefaultName());
    }

    public function test_rewrite_tones_and_classify_labels(): void
    {
        $rewrite = RewriteAction::make()->tones([
            'formal' => 'Formal',
        ]);

        $this->assertSame(['formal' => 'Formal'], $rewrite->getTones());

        $classify = ClassifyAction::make()->labels([
            'bug' => 'Bug',
            'feature' => 'Feature',
        ]);

        $this->assertSame([
            'bug' => 'Bug',
            'feature' => 'Feature',
        ], $classify->getLabels());
    }
}
