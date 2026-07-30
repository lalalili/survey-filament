<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Lalalili\SurveyCore\Actions\Triggers\DispatchManualDmsTestAction;
use Lalalili\SurveyCore\Enums\SurveyTriggerActionAttemptStatus;
use Lalalili\SurveyCore\Models\SurveyTriggerActionAttempt;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\SurveyTriggerActionPresetResource;
use Throwable;

class EditSurveyTriggerActionPreset extends EditRecord
{
    protected static string $resource = SurveyTriggerActionPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->testDmsQaAction(),
            SurveyTriggerActionPresetResource::deleteAction(),
        ];
    }

    private function testDmsQaAction(): Action
    {
        return Action::make('testDmsQa')
            ->label('測試 DMS QA')
            ->icon('heroicon-o-beaker')
            ->color('warning')
            ->visible(fn (): bool => $this->canTestDmsQa())
            ->authorize(fn (): bool => $this->canTestDmsQa())
            ->modalHeading('測試 DMS QA')
            ->modalDescription('只會送往 QA profile。送出前請確認以下資料不含真實顧客個資。')
            ->modalSubmitActionLabel('送出 QA 測試')
            ->schema([
                TextInput::make('ticketno')
                    ->label('測試案件編號')
                    ->helperText('可留空，由系統產生 QA 測試案件編號。'),
                Select::make('category')
                    ->label('問卷類別')
                    ->options([
                        'CSI' => 'CSI',
                        'SSI' => 'SSI',
                        'IQS' => 'IQS',
                    ])
                    ->default('CSI')
                    ->required(),
                DateTimePicker::make('submitted_at')
                    ->label('填答時間')
                    ->default(fn () => now())
                    ->seconds(false)
                    ->required(),
                TextInput::make('customername')
                    ->label('測試姓名')
                    ->default('QA 測試人員')
                    ->required(),
                Select::make('genderid')
                    ->label('性別代碼')
                    ->options([
                        'M' => 'M',
                        'F' => 'F',
                        'O' => 'O',
                    ])
                    ->default('O'),
                TextInput::make('mobilephone')
                    ->label('測試手機')
                    ->default('0900000000'),
                TextInput::make('regono')
                    ->label('測試車牌')
                    ->default('QA-0000'),
                TextInput::make('acb_dealercode')
                    ->label('經銷商代碼')
                    ->default('QA'),
                TextInput::make('acb_deptcode')
                    ->label('據點代碼')
                    ->default('QA00'),
                TextInput::make('open_answer')
                    ->label('測試意見')
                    ->default('DMS QA 串接測試'),
                TextInput::make('delivery_date')
                    ->label('交車日期')
                    ->default(fn (): string => now()->toDateString()),
                Placeholder::make('ticket_preview')
                    ->label('Ticket 預覽')
                    ->content(fn (Get $get): string => filled($get('ticketno'))
                        ? (string) $get('ticketno')
                        : '送出時由系統產生 QA 測試案件編號'),
                Placeholder::make('request_parameters_preview')
                    ->label('Request parameters 預覽')
                    ->content(fn (Get $get): string => $this->manualRequestParametersPreview($get)),
                Checkbox::make('confirm_qa_send')
                    ->label('我確認這是 QA 測試資料，且不含真實顧客個資')
                    ->accepted()
                    ->required(),
            ])
            ->action(function (array $data): void {
                abort_unless($this->canTestDmsQa(), 403);

                unset($data['confirm_qa_send']);
                $initiatedBy = auth()->id();

                try {
                    $attempt = app(DispatchManualDmsTestAction::class)->execute(
                        $this->preset(),
                        $data,
                        is_numeric($initiatedBy) ? (int) $initiatedBy : null,
                    );
                } catch (Throwable $exception) {
                    report($exception);
                    Notification::make()
                        ->danger()
                        ->title('DMS QA 測試失敗')
                        ->body('DMS QA 設定不完整或無法送出，請檢查 profile 與必要欄位。')
                        ->persistent()
                        ->send();

                    return;
                }

                $this->sendDmsAttemptNotification($attempt);
            });
    }

    private function canTestDmsQa(): bool
    {
        $preset = $this->preset();
        $action = $preset->action_json;

        return (bool) config('survey-core.triggers.dms.manual_test_enabled', false)
            && config('survey-core.triggers.dms.profile', 'qa') === 'qa'
            && ($action['type'] ?? null) === 'dms_soap'
            && ($action['profile'] ?? null) === 'qa'
            && (auth()->user()?->can('testDms', $preset) ?? false);
    }

    private function sendDmsAttemptNotification(SurveyTriggerActionAttempt $attempt): void
    {
        match ($attempt->status) {
            SurveyTriggerActionAttemptStatus::Success => Notification::make()
                ->success()
                ->title('DMS QA 測試成功')
                ->body('Ticket：'.($attempt->ticket_no ?? '—'))
                ->send(),
            SurveyTriggerActionAttemptStatus::PendingReview => Notification::make()
                ->warning()
                ->title('DMS QA 回應待判讀')
                ->body($attempt->error ?? '請查看測試紀錄中的完整回應。')
                ->persistent()
                ->send(),
            default => Notification::make()
                ->danger()
                ->title('DMS QA 測試失敗')
                ->body($attempt->error ?? '請查看測試紀錄中的完整回應。')
                ->persistent()
                ->send(),
        };
    }

    private function manualRequestParametersPreview(Get $get): string
    {
        $action = $this->preset()->action_json;
        $category = strtoupper((string) $get('category'));
        $parameters = [
            'ticketno' => filled($get('ticketno')) ? (string) $get('ticketno') : '[送出時產生]',
            'tickettypeid' => $action['ticket_type_id'] ?? null,
            'gradeid' => $action['grade_id'] ?? null,
            'opendealercode' => $action['open_dealer_code'] ?? null,
            'opendeptcode' => $action['open_department_code'] ?? null,
            'openmethodid' => $action['open_method_id'] ?? null,
            'categorypath' => $action['category_path'] ?? null,
            'survey_category' => $category,
            'customername' => $get('customername'),
            'genderid' => $get('genderid'),
            'mobilephone' => $get('mobilephone'),
            'regono' => $get('regono'),
            'acb_dealercode' => $get('acb_dealercode'),
            'acb_deptcode' => $get('acb_deptcode'),
            'open_answer' => $get('open_answer'),
            'delivery_date' => $get('delivery_date'),
        ];

        return json_encode(
            array_filter($parameters, fn (mixed $value): bool => filled($value)),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '—';
    }

    private function preset(): SurveyTriggerActionPreset
    {
        /** @var SurveyTriggerActionPreset $record */
        $record = $this->getRecord();

        return $record;
    }
}
