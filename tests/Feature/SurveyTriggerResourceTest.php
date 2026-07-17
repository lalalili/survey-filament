<?php

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyCore\Models\SurveyTriggerAllowedHost;
use Lalalili\SurveyCore\Models\SurveyTriggerRule;
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

it('lays out trigger rule form fields in a single column', function (): void {
    $resource = file_get_contents(__DIR__.'/../../src/Filament/Resources/SurveyTriggerRules/SurveyTriggerRuleResource.php');

    expect($resource)
        ->not->toBeFalse()
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
