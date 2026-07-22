<?php

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyAnswer;
use Lalalili\SurveyCore\Models\SurveyField;
use Lalalili\SurveyCore\Models\SurveyRecipient;
use Lalalili\SurveyCore\Models\SurveyResponse;
use Lalalili\SurveyCore\Models\SurveyResponseConsent;
use Lalalili\SurveyCore\Models\SurveyResponseEvent;
use Lalalili\SurveyCore\Models\SurveyTag;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyCore\Models\SurveyTriggerAllowedHost;
use Lalalili\SurveyCore\Models\SurveyTriggerDispatch;
use Lalalili\SurveyCore\Models\SurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\Pages\ListRecipients;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RelationManagers\RowsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Responses\ResponseResource;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\RecipientsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Tables\SurveysTable;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\SurveyTriggerActionPresetResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\SurveyTriggerAllowedHostResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\SurveyTriggerRuleResource;
use Livewire\Component;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

it('uses the draft survey title in the recoverable delete warning', function (): void {
    $survey = Survey::create([
        'title' => '已發佈標題',
        'draft_schema' => ['title' => '草稿標題'],
        'status' => SurveyStatus::Draft,
    ]);
    $action = SurveyResource::deleteAction()->record($survey);

    expect($action->getModalHeading())->toBe('刪除 草稿標題')
        ->and($action->getModalDescription())->toBe('刪除後可從「已刪除」還原，確定要進行嗎?');
});

it('configures response soft delete restore force delete and bulk warnings', function (): void {
    $survey = Survey::create(['title' => '問卷', 'status' => SurveyStatus::Draft]);
    $response = SurveyResponse::create(['survey_id' => $survey->id, 'response_number' => 'R-001']);
    $fallback = SurveyResponse::create(['survey_id' => $survey->id]);

    $bulkLivewire = Mockery::mock(Component::class)->makePartial();
    $bulkLivewire->shouldReceive('getSelectedTableRecords')->andReturn(new Collection([$response, $fallback]));
    $deleteBulkAction = ResponseResource::deleteBulkAction()->livewire($bulkLivewire);
    $forceDeleteBulkAction = ResponseResource::forceDeleteBulkAction()->livewire($bulkLivewire);

    expect(ResponseResource::deleteAction()->record($response)->getModalHeading())->toBe('刪除 R-001')
        ->and(ResponseResource::deleteAction()->record($response)->getModalDescription())->toBe('刪除後可從「已刪除」還原，確定要進行嗎?')
        ->and(ResponseResource::restoreAction()->record($fallback)->getModalHeading())->toBe('還原 #'.$fallback->id)
        ->and(ResponseResource::forceDeleteAction()->record($response)->getModalHeading())->toBe('永久刪除 R-001')
        ->and(ResponseResource::forceDeleteAction()->record($response)->getModalDescription())
        ->toContain('答案、標籤關聯、同意紀錄、觸發派送、案件與上傳檔案')
        ->and($deleteBulkAction->getModalHeading())->toBe('刪除已選取的 2 筆回應')
        ->and($deleteBulkAction->getModalDescription())->toBe('刪除後可從「已刪除」還原，確定要進行嗎?')
        ->and($forceDeleteBulkAction->getModalHeading())->toBe('永久刪除已選取的 2 筆回應')
        ->and($forceDeleteBulkAction->getModalDescription())->toContain('事件紀錄將保留但解除回應關聯');
});

it('configures hard delete warnings for survey management records', function (): void {
    $list = AudienceList::create(['name' => 'VIP 名單']);
    $host = SurveyTriggerAllowedHost::create(['host' => 'dms.internal']);
    $survey = Survey::create(['title' => '問卷', 'status' => SurveyStatus::Draft]);
    $rule = SurveyTriggerRule::create([
        'survey_id' => $survey->id,
        'name' => '回訪規則',
        'rule_tree_json' => [],
        'actions_json' => [],
    ]);
    $recipient = SurveyRecipient::create(['survey_id' => $survey->id, 'email' => 'guest@example.com']);

    expect(RecipientResource::deleteAction()->record($list)->getModalHeading())->toBe('刪除 VIP 名單')
        ->and(RecipientResource::deleteAction()->record($list)->getModalDescription())->toContain('可從「已刪除」還原')
        ->and(RecipientResource::restoreAction()->record($list)->getModalHeading())->toBe('還原 VIP 名單')
        ->and(RecipientResource::forceDeleteAction()->record($list)->getModalHeading())->toBe('永久刪除 VIP 名單')
        ->and(RecipientResource::forceDeleteAction()->record($list)->getModalDescription())->toContain('名單資料列、分群、活動及管道對應')
        ->and(SurveyTriggerAllowedHostResource::deleteAction()->record($host)->getModalHeading())->toBe('刪除 dms.internal')
        ->and(SurveyTriggerAllowedHostResource::deleteAction()->record($host)->getModalDescription())->toContain('不再允許 DMS HTTP action')
        ->and(SurveyTriggerRuleResource::deleteAction()->record($rule)->getModalHeading())->toBe('刪除 回訪規則')
        ->and(SurveyTriggerRuleResource::deleteAction()->record($rule)->getModalDescription())->toContain('可從「已刪除」還原')
        ->and(SurveyTriggerRuleResource::restoreAction()->record($rule)->getModalHeading())->toBe('還原 回訪規則')
        ->and(SurveyTriggerRuleResource::forceDeleteAction()->record($rule)->getModalHeading())->toBe('永久刪除 回訪規則')
        ->and(SurveyTriggerRuleResource::forceDeleteAction()->record($rule)->getModalDescription())->toContain('排程執行與派送紀錄')
        ->and(RecipientsRelationManager::deleteAction()->record($recipient)->getModalHeading())->toBe('刪除 guest@example.com')
        ->and(RecipientsRelationManager::deleteAction()->record($recipient)->getModalDescription())->toContain('回應將保留但解除收件人及連結關聯');
});

it('exposes deleted audience lists for restore or permanent deletion', function (): void {
    $list = AudienceList::create(['name' => '已刪除名單']);
    $list->delete();

    $table = RecipientResource::table(Table::make(new ListRecipients));

    expect(array_keys($table->getFilters()))->toContain('trashed')
        ->and(array_keys($table->getFlatActions()))->toContain('restore', 'forceDelete')
        ->and(RecipientResource::getRecordRouteBindingEloquentQuery()->find($list->id))->not->toBeNull();
});

it('halts deleting an action preset referenced by trigger rules', function (): void {
    $survey = Survey::create(['title' => '問卷', 'status' => SurveyStatus::Draft]);
    $preset = SurveyTriggerActionPreset::create([
        'name' => '顧關立案',
        'key' => 'dms_case',
        'action_json' => ['type' => 'http_post', 'endpoint' => 'https://example.com'],
        'is_active' => true,
    ]);
    SurveyTriggerRule::create([
        'survey_id' => $survey->id,
        'name' => '引用規則',
        'rule_tree_json' => [],
        'actions_json' => [
            ['type' => 'preset', 'preset_id' => $preset->id],
            ['type' => 'preset', 'preset_id' => $preset->id],
        ],
    ]);
    SurveyTriggerRule::create([
        'survey_id' => $survey->id,
        'name' => '第二筆引用規則',
        'rule_tree_json' => [],
        'actions_json' => [['type' => 'preset', 'preset_id' => $preset->id]],
    ]);

    expect(SurveyTriggerActionPresetResource::referencingRuleCount($preset))->toBe(2);

    expect(fn () => SurveyTriggerActionPresetResource::guardAgainstDeletingReferencedPreset(DeleteAction::make(), $preset))
        ->toThrow(Halt::class);

    Notification::assertNotified('無法刪除 DMS 動作設定');
});

it('allows deleting an unreferenced action preset', function (): void {
    $preset = SurveyTriggerActionPreset::create([
        'name' => '未使用動作',
        'key' => 'unused_action',
        'action_json' => ['type' => 'http_post', 'endpoint' => 'https://example.com'],
        'is_active' => true,
    ]);

    SurveyTriggerActionPresetResource::guardAgainstDeletingReferencedPreset(DeleteAction::make(), $preset);

    expect(SurveyTriggerActionPresetResource::referencingRuleCount($preset))->toBe(0);
});

it('exposes recipient management actions without enabling row deletion', function (): void {
    $recipients = new RecipientsRelationManager;
    $recipientTable = $recipients->table(Table::make($recipients));
    $rows = new RowsRelationManager;
    $rowsTable = $rows->table(Table::make($rows));

    expect($recipients->isReadOnly())->toBeFalse()
        ->and(collect($recipientTable->getHeaderActions())->map(fn ($action): string => $action->getName())->all())->toContain('create')
        ->and(array_keys($recipientTable->getFlatActions()))->toContain('edit', 'delete')
        ->and(array_keys($rowsTable->getFlatActions()))->toContain('edit')
        ->and(array_keys($rowsTable->getFlatActions()))->not->toContain('delete');
});

it('force deletes all active and trashed responses through model events', function (): void {
    config()->set('media-library.media_model', Media::class);
    DB::statement('PRAGMA foreign_keys = ON');
    $survey = Survey::create(['title' => '問卷', 'status' => SurveyStatus::Draft]);
    $active = SurveyResponse::create(['survey_id' => $survey->id]);
    $trashed = SurveyResponse::create(['survey_id' => $survey->id]);
    $field = SurveyField::create([
        'survey_id' => $survey->id,
        'field_key' => 'comment',
        'type' => 'short_text',
        'label' => '意見',
        'sort_order' => 1,
    ]);
    $answer = SurveyAnswer::create([
        'survey_response_id' => $active->id,
        'survey_field_id' => $field->id,
        'answer_text' => '內容',
    ]);
    $consent = SurveyResponseConsent::create([
        'survey_response_id' => $active->id,
        'type' => 'privacy',
        'accepted_at' => now(),
    ]);
    $tag = SurveyTag::create(['survey_id' => $survey->id, 'name' => '待處理']);
    $active->tags()->attach($tag);
    $rule = SurveyTriggerRule::create([
        'survey_id' => $survey->id,
        'name' => '派送規則',
        'rule_tree_json' => [],
        'actions_json' => [],
    ]);
    $dispatch = SurveyTriggerDispatch::create([
        'survey_trigger_rule_id' => $rule->id,
        'survey_response_id' => $active->id,
    ]);
    $event = SurveyResponseEvent::create([
        'survey_id' => $survey->id,
        'survey_response_id' => $active->id,
        'event' => 'submitted',
        'occurred_at' => now(),
    ]);
    $trashed->delete();
    $forceDeletedIds = [];
    SurveyResponse::forceDeleted(function (SurveyResponse $response) use (&$forceDeletedIds): void {
        $forceDeletedIds[] = $response->id;
    });

    expect(SurveysTable::clearResponses($survey))->toBe(2)
        ->and($forceDeletedIds)->toEqualCanonicalizing([$active->id, $trashed->id])
        ->and(SurveyResponse::withTrashed()->where('survey_id', $survey->id)->exists())->toBeFalse()
        ->and(SurveyAnswer::find($answer->id))->toBeNull()
        ->and(SurveyResponseConsent::find($consent->id))->toBeNull()
        ->and(SurveyTriggerDispatch::find($dispatch->id))->toBeNull()
        ->and(DB::table('survey_response_tag')->where('survey_response_id', $active->id)->exists())->toBeFalse()
        ->and($event->fresh())->not->toBeNull()
        ->and($event->fresh()?->survey_response_id)->toBeNull();
});
