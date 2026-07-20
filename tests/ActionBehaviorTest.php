<?php

namespace MuazzamBuilds\FilamentAiActions\Tests;

use Filament\Actions\Enums\ActionStatus;
use Filament\FilamentManager;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use MuazzamBuilds\FilamentAiActions\Actions\ClassifyAction;
use MuazzamBuilds\FilamentAiActions\Actions\GenerateAction;
use MuazzamBuilds\FilamentAiActions\Actions\SummarizeAction;
use MuazzamBuilds\FilamentAiActions\Actions\TranslateAction;
use MuazzamBuilds\FilamentAiActions\AiActionsPlugin;
use MuazzamBuilds\FilamentAiActions\Support\LanguageOptions;

class ActionBehaviorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('ai_action_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('category')->nullable();
            $table->text('result')->nullable();
        });
    }

    protected function tearDown(): void
    {
        if (app()->bound('filament')) {
            filament()->setCurrentPanel(null);
        }

        parent::tearDown();
    }

    public function test_plugin_enabled_state_and_model_reach_actions(): void
    {
        $this->bindFilamentManager();

        $panel = Panel::make()
            ->id('admin')
            ->plugin(AiActionsPlugin::make()->enabled(false)->model('plugin-model'));

        filament()->setCurrentPanel($panel);

        $this->assertFalse(SummarizeAction::make()->isVisible());

        $panel->plugin(AiActionsPlugin::make()->enabled()->model('plugin-model'));
        Http::fake($this->completion('Plugin model result'));

        SummarizeAction::make()
            ->applyResultUsing(fn (): null => null)
            ->call([
                'data' => ['content' => 'Source'],
                'record' => null,
                'livewire' => null,
            ]);

        Http::assertSent(fn ($request): bool => $request['model'] === 'plugin-model');
    }

    public function test_action_options_override_plugin_and_config_defaults(): void
    {
        $this->bindFilamentManager();

        $panel = Panel::make()
            ->id('admin')
            ->plugin(AiActionsPlugin::make()->model('plugin-model'));

        filament()->setCurrentPanel($panel);
        Http::fake($this->completion('Configured result'));

        $action = SummarizeAction::make()
            ->model('action-model')
            ->temperature(0.1)
            ->maxTokens(321)
            ->systemPrompt('Custom system prompt')
            ->applyResultUsing(fn (): null => null);

        $this->assertNull($action->getCustomModel());

        $action->call([
            'data' => ['content' => 'Source'],
            'record' => null,
            'livewire' => null,
        ]);

        Http::assertSent(function ($request): bool {
            return $request['model'] === 'action-model'
                && $request['temperature'] === 0.1
                && $request['max_tokens'] === 321
                && $request['messages'][0]['content'] === 'Custom system prompt';
        });
    }

    public function test_apply_to_saves_by_default(): void
    {
        $post = AiActionPost::query()->create(['body' => 'Original']);
        Http::fake($this->completion('Saved summary'));

        SummarizeAction::make()
            ->attributes(['body'])
            ->applyTo('result')
            ->record($post)
            ->call([
                'data' => ['content' => 'Original'],
                'record' => $post,
                'livewire' => null,
            ]);

        $this->assertSame('Saved summary', $post->fresh()->result);
    }

    public function test_save_result_false_fills_model_without_persisting(): void
    {
        $post = AiActionPost::query()->create(['body' => 'Original', 'result' => 'Existing']);
        $livewire = new class
        {
            public array $refreshed = [];

            public function refreshFormData(array $attributes): void
            {
                $this->refreshed = $attributes;
            }
        };
        Http::fake($this->completion('Unsaved summary'));

        SummarizeAction::make()
            ->applyTo('result')
            ->saveResult(false)
            ->record($post)
            ->call([
                'data' => ['content' => 'Original'],
                'record' => $post,
                'livewire' => $livewire,
            ]);

        $this->assertSame('Unsaved summary', $post->result);
        $this->assertSame('Existing', $post->fresh()->result);
        $this->assertSame(['result'], $livewire->refreshed);
    }

    public function test_custom_result_application_receives_context_and_skips_default_persistence(): void
    {
        $post = AiActionPost::query()->create(['body' => 'Original']);
        $captured = null;
        Http::fake($this->completion('Custom result'));

        SummarizeAction::make()
            ->applyTo('result')
            ->applyResultUsing(function (string $result, ?Model $record) use (&$captured): void {
                $captured = [$result, $record?->getKey()];
            })
            ->record($post)
            ->call([
                'data' => ['content' => 'Original'],
                'record' => $post,
                'livewire' => null,
            ]);

        $this->assertSame(['Custom result', $post->getKey()], $captured);
        $this->assertNull($post->fresh()->result);
    }

    public function test_classification_accepts_structured_case_insensitive_labels(): void
    {
        $post = AiActionPost::query()->create(['body' => 'The application crashes']);
        Http::fake($this->completion('```json' . "\n" . '{"label":"BUG"}' . "\n" . '```'));

        ClassifyAction::make()
            ->labels(['bug' => 'Bug report', 'feature' => 'Feature request'])
            ->applyTo('category')
            ->record($post)
            ->call([
                'data' => ['content' => $post->body],
                'record' => $post,
                'livewire' => null,
            ]);

        $this->assertSame('bug', $post->fresh()->category);
    }

    public function test_classification_does_not_persist_invalid_or_ambiguous_output(): void
    {
        $post = AiActionPost::query()->create(['body' => 'A request']);
        Http::fake($this->completion('This might be a bug or feature'));

        $action = ClassifyAction::make()
            ->labels(['bug', 'feature'])
            ->applyTo('category')
            ->record($post);

        $action->call([
            'data' => ['content' => $post->body],
            'record' => $post,
            'livewire' => null,
        ]);

        $this->assertNull($post->fresh()->category);
        $this->assertSame(ActionStatus::Failure, $action->getStatus());
    }

    public function test_generate_uses_prompt_context_and_custom_result_callback(): void
    {
        $captured = null;
        Http::fake($this->completion('Generated copy'));

        GenerateAction::make()
            ->applyResultUsing(function (string $result) use (&$captured): void {
                $captured = $result;
            })
            ->call([
                'data' => [
                    'prompt' => 'Write a headline',
                    'context' => 'Product: Atlas',
                ],
                'record' => null,
                'livewire' => null,
            ]);

        $this->assertSame('Generated copy', $captured);
        Http::assertSent(fn ($request): bool => str_contains(
            $request['messages'][1]['content'],
            "Write a headline\n\nContext:\nProduct: Atlas",
        ));
    }

    public function test_translate_validates_language_and_generates_translation(): void
    {
        $captured = null;
        Http::fake($this->completion('Bonjour'));

        TranslateAction::make()
            ->languages(['fr' => 'French'])
            ->defaultLanguage('fr')
            ->applyResultUsing(function (string $result) use (&$captured): void {
                $captured = $result;
            })
            ->call([
                'data' => ['content' => 'Hello', 'language' => 'fr'],
                'record' => null,
                'livewire' => null,
            ]);

        $this->assertSame('Bonjour', $captured);
        Http::assertSent(fn ($request): bool => str_contains($request['messages'][0]['content'], 'French'));
    }

    public function test_translate_includes_the_complete_iso_639_1_language_set_by_default(): void
    {
        $languages = TranslateAction::make()->getLanguages();

        $this->assertCount(183, $languages);
        $this->assertSame(LanguageOptions::all(), $languages);
        $this->assertSame('English', $languages['en']);
        $this->assertSame('Spanish', $languages['es']);
        $this->assertSame('Urdu', $languages['ur']);
        $this->assertSame('Chinese', $languages['zh']);
        $this->assertCount(183, array_unique(array_keys($languages)));
    }

    public function test_translate_custom_languages_still_override_the_default_set(): void
    {
        $languages = TranslateAction::make()
            ->languages(['custom' => 'Custom language'])
            ->getLanguages();

        $this->assertSame(['custom' => 'Custom language'], $languages);
    }

    public function test_translate_rejects_unknown_language_without_requesting_openai(): void
    {
        Http::fake();

        TranslateAction::make()
            ->languages(['fr' => 'French'])
            ->call([
                'data' => ['content' => 'Hello', 'language' => 'de'],
                'record' => null,
                'livewire' => null,
            ]);

        Http::assertNothingSent();
    }

    public function test_empty_content_fails_before_requesting_openai(): void
    {
        Http::fake();
        $action = SummarizeAction::make();

        $action->call([
            'data' => ['content' => '   '],
            'record' => null,
            'livewire' => null,
        ]);

        Http::assertNothingSent();
        $this->assertSame(ActionStatus::Failure, $action->getStatus());
    }

    /**
     * @return array<string, \Illuminate\Http\Client\Response>
     */
    private function completion(string $content): array
    {
        return [
            '*' => Http::response([
                'choices' => [
                    ['message' => ['content' => $content]],
                ],
            ]),
        ];
    }

    private function bindFilamentManager(): void
    {
        app()->singleton('filament', FilamentManager::class);
    }
}

class AiActionPost extends Model
{
    protected $table = 'ai_action_posts';

    protected $guarded = [];

    public $timestamps = false;
}
