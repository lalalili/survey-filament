<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Lalalili\SurveyCore\Actions\Triggers\ExpandPresetsAction;
use Lalalili\SurveyCore\Actions\Triggers\ResolveActionPayloadAction;
use Lalalili\SurveyCore\Actions\Triggers\RunTriggerRuleBatchAction;
use Lalalili\SurveyCore\Enums\TriggerRunType;
use Lalalili\SurveyCore\Models\SurveyRecipient;
use Lalalili\SurveyCore\Models\SurveyResponse;
use Lalalili\SurveyCore\Models\SurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\SurveyTriggerRuleResource;

class EditSurveyTriggerRule extends EditRecord
{
    protected static string $resource = SurveyTriggerRuleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['preset_ids'] = SurveyTriggerRuleResource::actionsToPresetIds($data['actions_json'] ?? []);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['actions_json'] = SurveyTriggerRuleResource::presetIdsToActions($data['preset_ids'] ?? []);
        unset($data['preset_ids']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewPayload')
                ->label('預覽 Payload')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->schema([
                    Select::make('response_id')
                        ->label('選擇填答記錄')
                        ->options(function (): array {
                            if (! $this->record instanceof SurveyTriggerRule) {
                                return [];
                            }

                            return SurveyResponse::where('survey_id', $this->record->survey_id)
                                ->latest()
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (SurveyResponse $r): array => [
                                    $r->id => "#{$r->id} — ".($r->submitted_at ? $r->submitted_at->format('Y/m/d H:i') : '未知時間'),
                                ])
                                ->all();
                        })
                        ->placeholder('（選擇一筆填答以試算）')
                        ->live()
                        ->afterStateUpdated(function (?int $state, Set $set): void {
                            if (! $state) {
                                $set('preview', '');

                                return;
                            }
                            $set('preview', $this->resolvePreviewPayload($state));
                        }),

                    Textarea::make('preview')
                        ->label('解析後 Payload（第一個動作）')
                        ->rows(14)
                        ->readOnly()
                        ->visible(fn (Get $get): bool => (bool) $get('response_id')),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('關閉'),

            Action::make('runForRecipient')
                ->label('手動執行')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->schema([
                    Select::make('recipient_id')
                        ->label('選擇會員')
                        ->placeholder('（以姓名／手機／車牌搜尋）')
                        ->searchable()
                        ->required()
                        ->getSearchResultsUsing(function (string $search): array {
                            if (! $this->record instanceof SurveyTriggerRule) {
                                return [];
                            }

                            return SurveyRecipient::query()
                                ->where('survey_id', $this->record->survey_id)
                                ->where('is_test', false)
                                ->where(function ($query) use ($search): void {
                                    $like = "%{$search}%";
                                    $query->where('name', 'like', $like)
                                        ->orWhere('external_id', 'like', $like)
                                        ->orWhere('payload_json->mobile', 'like', $like)
                                        ->orWhere('payload_json->regono', 'like', $like)
                                        ->orWhere('payload_json->name', 'like', $like);
                                })
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (SurveyRecipient $r): array => [
                                    $r->id => $this->recipientLabel($r),
                                ])
                                ->all();
                        })
                        ->getOptionLabelUsing(function ($value): ?string {
                            $recipient = SurveyRecipient::query()->whereKey($value)->first();

                            return $recipient ? $this->recipientLabel($recipient) : null;
                        }),
                ])
                ->action(function (array $data): void {
                    if (! $this->record instanceof SurveyTriggerRule) {
                        return;
                    }

                    $response = SurveyResponse::query()
                        ->where('survey_id', $this->record->survey_id)
                        ->where('survey_recipient_id', $data['recipient_id'])
                        ->whereNotNull('submitted_at')
                        ->latest('submitted_at')
                        ->first();

                    if (! $response) {
                        Notification::make()
                            ->title('此會員尚無填答記錄')
                            ->danger()
                            ->send();

                        return;
                    }

                    $run = app(RunTriggerRuleBatchAction::class)
                        ->execute($this->record, TriggerRunType::Manual, $response->id);

                    Notification::make()
                        ->title("已執行：符合 {$run->matched_count} 筆／派送 {$run->dispatched_count} 筆")
                        ->success()
                        ->send();
                })
                ->modalSubmitActionLabel('執行'),

            DeleteAction::make(),
        ];
    }

    /**
     * 會員下拉顯示字串：姓名 · 手機 · 車牌（取自 survey_recipients.payload_json，回退欄位值）。
     */
    private function recipientLabel(SurveyRecipient $recipient): string
    {
        $payload = $recipient->payload_json ?? [];

        $name = $recipient->name ?? ($payload['name'] ?? null);
        $mobile = $payload['mobile'] ?? null;
        $regono = $payload['regono'] ?? null;

        $parts = array_filter([
            $name ?? '（未具名）',
            $mobile,
            $regono,
        ], fn ($v): bool => $v !== null && $v !== '');

        return implode(' · ', $parts);
    }

    private function resolvePreviewPayload(int $responseId): string
    {
        $response = SurveyResponse::with('answers.field')->find($responseId);
        if (! $response) {
            return '（找不到填答記錄）';
        }

        if (! $this->record instanceof SurveyTriggerRule) {
            return '（找不到觸發規則）';
        }

        // 先把 preset 參照展開為具體 http_post 動作，再取第一個試算 payload。
        $actions = app(ExpandPresetsAction::class)->execute($this->record->actions_json ?? []);
        $firstAction = collect($actions)->firstWhere('type', 'http_post');
        if (! is_array($firstAction)) {
            return '（規則未設定 http_post 動作）';
        }

        $template = $firstAction['payload_template'] ?? [];
        if (is_string($template)) {
            $template = json_decode($template, true) ?? [];
        }

        $answerMap = $response->answers
            ->mapWithKeys(fn ($a): array => [$a->field->field_key => $a->getValue()])
            ->all();

        $resolved = app(ResolveActionPayloadAction::class)->execute($template, $response, $answerMap);

        return json_encode($resolved, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '（無法序列化）';
    }
}
