<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Lalalili\SurveyCore\Actions\Triggers\ExpandPresetsAction;
use Lalalili\SurveyCore\Actions\Triggers\ResolveActionPayloadAction;
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

            DeleteAction::make(),
        ];
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
