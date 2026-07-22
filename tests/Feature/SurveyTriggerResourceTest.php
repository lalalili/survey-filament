<?php

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Lalalili\SurveyCore\Actions\EvaluateAnswerRuleTreeAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyField;
use Lalalili\SurveyCore\Models\SurveyPage;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyCore\Models\SurveyTriggerAllowedHost;
use Lalalili\SurveyCore\Models\SurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Forms\Components\RuleTreeField;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\SurveyTriggerActionPresetResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\SurveyTriggerAllowedHostResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\SurveyTriggerRuleResource;
use Livewire\Component;

/**
 * 取得資源表單的扁平欄位 key（沿用 SurveyPluginSmokeTest 的 schema host 慣例）。
 *
 * @param  class-string  $resource
 * @return list<string>
 */
function triggerFormFieldKeys(string $resource): array
{
    $host = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    return array_keys($resource::form(Schema::make($host))->getFlatFields());
}

/**
 * 取得指定問卷在「篩選條件」規則樹可選的欄位定義。
 *
 * @return list<array<string, mixed>>
 */
function triggerRuleAvailableFields(int $surveyId): array
{
    $host = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $schema = SurveyTriggerRuleResource::form(Schema::make($host));
    $schema->fill(['survey_id' => $surveyId]);

    /** @var RuleTreeField $field */
    $field = $schema->getFlatFields()['rule_tree_json'];

    return $field->getAvailableFields();
}

it('registers index/create/edit pages for the three trigger resources', function (): void {
    $resources = [
        SurveyTriggerRuleResource::class,
        SurveyTriggerAllowedHostResource::class,
        SurveyTriggerActionPresetResource::class,
    ];

    foreach ($resources as $resource) {
        expect($resource::getPages())->toHaveKeys(['index', 'create', 'edit']);
    }
});

it('binds the correct model to each trigger resource', function (): void {
    expect(SurveyTriggerRuleResource::getModel())->toBe(SurveyTriggerRule::class)
        ->and(SurveyTriggerAllowedHostResource::getModel())->toBe(SurveyTriggerAllowedHost::class)
        ->and(SurveyTriggerActionPresetResource::getModel())->toBe(SurveyTriggerActionPreset::class);
});

it('exposes key form fields for SurveyTriggerRuleResource', function (): void {
    expect(triggerFormFieldKeys(SurveyTriggerRuleResource::class))
        ->toContain('survey_id', 'name', 'is_active', 'schedule_enabled');
});

it('lists filter fields in the same order as the survey results page', function (): void {
    $survey = Survey::create(['title' => '排序問卷', 'status' => SurveyStatus::Draft]);

    $pageTwo = SurveyPage::create(['survey_id' => $survey->id, 'page_key' => 'p2', 'title' => '第二頁', 'sort_order' => 2]);
    $pageOne = SurveyPage::create(['survey_id' => $survey->id, 'page_key' => 'p1', 'title' => '第一頁', 'sort_order' => 1]);

    // 建立順序刻意打亂，只有頁面與題目的 sort_order 才是正解。
    SurveyField::create(['survey_id' => $survey->id, 'survey_page_id' => $pageTwo->id, 'field_key' => 'p2_q1', 'type' => 'short_text', 'label' => '第二頁第一題', 'sort_order' => 2001]);
    SurveyField::create(['survey_id' => $survey->id, 'survey_page_id' => $pageOne->id, 'field_key' => 'p1_q2', 'type' => 'nps', 'label' => '第一頁第二題', 'sort_order' => 1002]);
    SurveyField::create(['survey_id' => $survey->id, 'survey_page_id' => $pageOne->id, 'field_key' => 'p1_q1', 'type' => 'short_text', 'label' => '第一頁第一題', 'sort_order' => 1001]);
    SurveyField::create(['survey_id' => $survey->id, 'survey_page_id' => $pageOne->id, 'field_key' => 'p1_retired', 'type' => 'short_text', 'label' => '已退場題', 'sort_order' => 1003, 'retired_at' => now()]);
    SurveyField::create(['survey_id' => $survey->id, 'survey_page_id' => $pageOne->id, 'field_key' => 'p1_copy', 'type' => 'description_block', 'label' => '說明文字', 'sort_order' => 1004]);
    SurveyField::create(['survey_id' => $survey->id, 'survey_page_id' => $pageOne->id, 'field_key' => 'p1_secret', 'type' => 'short_text', 'label' => '其他隱藏欄位', 'sort_order' => 1005, 'is_hidden' => true]);

    // 名單對應的固定欄位（隱藏），結果頁固定排在最前面。
    SurveyField::create(['survey_id' => $survey->id, 'survey_page_id' => $pageOne->id, 'field_key' => 'system_context_location', 'type' => 'short_text', 'label' => '據點', 'sort_order' => 1007, 'is_hidden' => true]);
    SurveyField::create(['survey_id' => $survey->id, 'survey_page_id' => $pageOne->id, 'field_key' => 'system_context_dealer', 'type' => 'short_text', 'label' => '經銷商', 'sort_order' => 1006, 'is_hidden' => true]);

    $fields = triggerRuleAvailableFields($survey->id);

    expect(array_column($fields, 'key'))->toBe([
        EvaluateAnswerRuleTreeAction::META_DAYS_SINCE_INVITATION,
        'system_context_dealer',
        'system_context_location',
        'p1_q1',
        'p1_q2',
        'p2_q1',
    ])->and($fields[4]['type'])->toBe('number');
});

it('clears the rule tree when the survey selection changes', function (): void {
    $host = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $schema = SurveyTriggerRuleResource::form(Schema::make($host));
    $schema->fill([
        'survey_id' => 1,
        'rule_tree_json' => ['op' => 'AND', 'children' => [['field' => 'old_field', 'operator' => 'eq', 'value' => '1']]],
    ]);

    $surveySelect = $schema->getFlatFields()['survey_id'];
    $surveySelect->state(2);
    $surveySelect->callAfterStateUpdated();

    expect($schema->getFlatFields()['rule_tree_json']->getState())
        ->toBe(['op' => 'AND', 'children' => []]);
});

it('lays out trigger rule form fields in a single column', function (): void {
    $resource = file_get_contents(__DIR__.'/../../src/Filament/Resources/SurveyTriggerRules/SurveyTriggerRuleResource.php');

    expect($resource)
        ->not->toBeFalse()
        ->toContain('->columns(1)')
        ->not->toContain('Grid::make(2)')
        ->toContain('Grid::make(1)');
});

it('preserves the rule tree builder across Livewire validation updates', function (): void {
    $view = file_get_contents(__DIR__.'/../../resources/views/forms/components/rule-tree-field.blade.php');

    expect($view)
        ->not->toBeFalse()
        ->toContain('wire:ignore')
        ->toContain('wire:key="rule-tree-builder-')
        ->toContain('<rule-tree-builder');
});

it('exposes an optional renderless rule preview callback', function (): void {
    $field = RuleTreeField::make('rule_tree_json')
        ->previewUsing(fn (array $ruleTree, array $nodePath): array => [
            'count' => count($ruleTree['children'] ?? []) + count($nodePath),
        ]);

    expect($field->hasPreview())->toBeTrue()
        ->and($field->previewNode(
            ruleTree: ['op' => 'AND', 'children' => [['field' => 'email']]],
            nodePath: [0],
        ))->toBe([
            'count' => 2,
        ]);
});

it('uses a translated required message for trigger actions', function (): void {
    $host = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $presetField = SurveyTriggerRuleResource::form(Schema::make($host))
        ->getFlatFields()['preset_ids'];

    expect($presetField->getValidationMessages())
        ->toMatchArray(['required' => '請選擇至少一個觸發動作。']);
});

it('exposes key form fields for SurveyTriggerAllowedHostResource', function (): void {
    expect(triggerFormFieldKeys(SurveyTriggerAllowedHostResource::class))
        ->toContain('host', 'description');
});

it('exposes key form fields for SurveyTriggerActionPresetResource', function (): void {
    expect(triggerFormFieldKeys(SurveyTriggerActionPresetResource::class))
        ->toContain('name', 'key', 'is_active');
});

it('persists json and boolean casts for SurveyTriggerRule', function (): void {
    $survey = Survey::create([
        'title' => '觸發規則測試問卷',
        'status' => SurveyStatus::Draft,
    ]);

    $rule = SurveyTriggerRule::create([
        'survey_id' => $survey->id,
        'name' => '測試規則',
        'is_active' => true,
        'rule_tree_json' => ['op' => 'and', 'children' => []],
        'actions_json' => [['type' => 'http']],
    ]);

    $fresh = $rule->fresh();

    expect($fresh->is_active)->toBeTrue()
        ->and($fresh->rule_tree_json)->toBe(['op' => 'and', 'children' => []])
        ->and($fresh->actions_json)->toBe([['type' => 'http']]);
});

it('persists json and boolean casts for SurveyTriggerActionPreset', function (): void {
    $preset = SurveyTriggerActionPreset::create([
        'key' => 'preset-key',
        'name' => '測試動作預設',
        'action_json' => ['endpoint' => 'https://example.test/webhook'],
        'is_active' => true,
    ]);

    $fresh = $preset->fresh();

    expect($fresh->is_active)->toBeTrue()
        ->and($fresh->action_json)->toBe(['endpoint' => 'https://example.test/webhook']);
});

it('creates a SurveyTriggerAllowedHost with host and description', function (): void {
    $host = SurveyTriggerAllowedHost::create([
        'host' => 'example.com',
        'description' => '測試允許來源',
    ]);

    expect($host->fresh())
        ->host->toBe('example.com')
        ->description->toBe('測試允許來源');
});
