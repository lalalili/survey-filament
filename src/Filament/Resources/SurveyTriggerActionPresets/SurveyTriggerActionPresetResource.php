<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets;

use BackedEnum;
use Closure;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyCore\Models\SurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages\CreateSurveyTriggerActionPreset;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages\EditSurveyTriggerActionPreset;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages\ListSurveyTriggerActionPresets;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\RelationManagers\DmsAttemptsRelationManager;

/**
 * 系統管理員維護的觸發動作預設（DMS 動作）。操作員於觸發規則以下拉選單參照。
 */
class SurveyTriggerActionPresetResource extends Resource
{
    /**
     * @var array<string, string>
     */
    private const DMS_CONFIRMATIONS = [
        'ticket_type_id' => '案件類型',
        'open_department_code' => '立案部門',
        'open_method_id' => '立案方式',
        'category_path' => '案件分類',
        'employee_code_source' => '員工代碼來源',
        'description_format' => '案件描述格式',
        'gender_mapping' => '性別代碼對照',
        'ticket_number_strategy' => '案件編號策略',
        'response_semantics' => '回應成功／失敗判定',
        'wsdl_contract' => 'WSDL 契約',
    ];

    protected static ?string $model = SurveyTriggerActionPreset::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    protected static ?string $navigationLabel = 'DMS動作設定';

    protected static ?string $modelLabel = 'DMS動作設定';

    protected static ?string $pluralModelLabel = 'DMS動作設定';

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('survey-filament.trigger_action_preset_navigation_enabled', true);
    }

    public static function getNavigationGroup(): ?string
    {
        return '系統';
    }

    public static function getNavigationSort(): ?int
    {
        return 83;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('基本設定')
                ->columns(1)
                ->schema([
                    TextInput::make('name')
                        ->label('動作名稱')
                        ->placeholder('顧關立案')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('key')
                        ->label('代碼（key）')
                        ->placeholder('dms_case')
                        ->helperText('唯一識別碼，供程式／seed 參照')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('description')
                        ->label('說明')
                        ->maxLength(255),

                    Toggle::make('is_active')
                        ->label('啟用')
                        ->default(true)
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if (! $value) {
                                    return;
                                }

                                $actionType = $get('action_json.type') ?? 'http_post';

                                if ($actionType === 'dms_voucher') {
                                    $fail('DMS 維修抵用劵 API 規格尚待客戶提供，完成串接與測試前不得啟用。');

                                    return;
                                }

                                if ($actionType !== 'dms_soap') {
                                    return;
                                }

                                $unconfirmed = collect(array_keys(self::DMS_CONFIRMATIONS))
                                    ->reject(fn (string $key): bool => $get("action_json.parameter_confirmations.{$key}") === 'confirmed')
                                    ->values();

                                if ($unconfirmed->isNotEmpty()) {
                                    $fail('DMS SOAP 必要確認項目尚未全部確認，不得啟用；請先停用並儲存為草稿。');
                                }

                                if ($get('action_json.profile') !== 'production') {
                                    $fail('DMS SOAP 正式啟用必須使用 production profile；QA profile 僅供人工測試。');
                                }
                            },
                        ]),
                ]),

            Section::make('動作定義')
                ->columns(1)
                ->schema([
                    Select::make('action_json.type')
                        ->label('動作類型')
                        ->options([
                            'http_post' => 'JSON HTTP POST',
                            'dms_soap' => 'DMS SOAP',
                            'dms_voucher' => 'DMS 維修抵用劵（規格待確認）',
                        ])
                        ->default('http_post')
                        ->required()
                        ->live(),
                ]),

            Section::make('HTTP 動作設定')
                ->key('http_action_settings')
                ->columns(1)
                ->visible(fn (Get $get): bool => ($get('action_json.type') ?? 'http_post') === 'http_post')
                ->schema([

                    TextInput::make('action_json.endpoint')
                        ->label('Endpoint URL')
                        ->url()
                        ->required()
                        ->maxLength(500),

                    KeyValue::make('action_json.headers')
                        ->label('Headers')
                        ->keyLabel('名稱')
                        ->valueLabel('值')
                        ->helperText('值可用 {{env.ENV_VAR}} token（執行時讀取，不入庫明文）'),

                    Textarea::make('action_json.payload_template')
                        ->label('Payload 模板（JSON）')
                        ->helperText('可使用 {{response.id}}、{{answer.field_key}}、{{recipient.payload.mobile}}、{{env.ENV_VAR}} 等 token')
                        ->rows(8)
                        ->rules(['json'])
                        ->validationMessages([
                            'json' => 'Payload 模板必須是有效的 JSON。',
                        ])
                        ->formatStateUsing(fn ($state): string => is_array($state)
                            ? (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '')
                            : (string) ($state ?? ''))
                        ->dehydrateStateUsing(fn ($state) => is_string($state) ? (json_decode($state, true) ?? []) : ($state ?? [])),

                    Toggle::make('action_json.require_valid_token')
                        ->label('僅限有效邀請連結觸發')
                        ->helperText('開啟後，只有「透過邀請連結（token）且未逾期」的填答才會觸發此動作。發點券請開啟，避免對匿名公開填答發券。')
                        ->default(false),

                    TextInput::make('action_json.timeout')
                        ->label('Timeout（秒）')
                        ->numeric()
                        ->default(10),

                    TextInput::make('action_json.retry.times')
                        ->label('重試次數')
                        ->numeric()
                        ->default(3),

                    TextInput::make('action_json.retry.sleep_ms')
                        ->label('重試間隔（ms）')
                        ->numeric()
                        ->default(200),
                ]),

            Section::make('DMS SOAP 設定')
                ->key('dms_soap_settings')
                ->columns(1)
                ->visible(fn (Get $get): bool => $get('action_json.type') === 'dms_soap')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('action_json.profile')
                            ->label('執行環境')
                            ->readOnly()
                            ->default(fn (): string => (string) config('survey-core.triggers.dms.profile', 'qa'))
                            ->dehydrateStateUsing(fn (): string => (string) config('survey-core.triggers.dms.profile', 'qa'))
                            ->helperText('由系統環境設定控制；不在資料庫保存 DMS 密鑰。'),

                        Placeholder::make('dms_profile_readiness')
                            ->label('Profile 設定狀態')
                            ->content(fn (): string => self::dmsProfileReadiness()),
                    ]),

                    Grid::make(2)->schema([
                        TextInput::make('action_json.ticket_type_id')
                            ->label('案件類型 ID')
                            ->default('CST-FOLLOWUP')
                            ->helperText('顧關追蹤為 CST-FOLLOWUP。')
                            ->required(),
                        TextInput::make('action_json.grade_id')
                            ->label('案件等級 ID')
                            ->default('B')
                            ->required(),
                        TextInput::make('action_json.open_dealer_code')
                            ->label('立案經銷商代碼')
                            ->default('LUXGEN')
                            ->required(),
                        TextInput::make('action_json.open_department_code')
                            ->label('立案部門代碼')
                            ->default('R0100')
                            ->required(),
                        TextInput::make('action_json.open_method_id')
                            ->label('立案方式 ID')
                            ->default('I')
                            ->helperText('滿意度調查為 I。')
                            ->required(),
                        TextInput::make('action_json.category_path')
                            ->label('案件分類路徑（預設值）')
                            ->helperText('未針對問卷類別另外設定時採用此值。')
                            ->required(),
                        TextInput::make('action_json.employee_code')
                            ->label('員工代碼固定值／來源')
                            ->helperText('填入廠商確認的固定值或來源代碼；不得填入 DMS sKey。'),
                        TextInput::make('action_json.create_user_code')
                            ->label('建立人員代碼')
                            ->default('CSC01')
                            ->required(),
                        TextInput::make('action_json.last_modified_by_code')
                            ->label('最後修改人員代碼')
                            ->default('CSC01')
                            ->required(),
                        TextInput::make('action_json.close_by_code')
                            ->label('結案人員代碼')
                            ->default('CSC01')
                            ->required(),
                    ]),

                    Fieldset::make('各問卷類別開放題欄位')
                        ->schema([
                            TextInput::make('action_json.open_question_keys.CSI')
                                ->label('CSI 欄位 key')
                                ->required(),
                            TextInput::make('action_json.open_question_keys.SSI')
                                ->label('SSI 欄位 key')
                                ->required(),
                            TextInput::make('action_json.open_question_keys.IQS')
                                ->label('IQS 欄位 key')
                                ->required(),
                        ]),

                    Fieldset::make('各問卷類別案件歸屬分類')
                        ->schema([
                            TextInput::make('action_json.category_paths.CSI')
                                ->label('CSI 案件分類路徑')
                                ->helperText('格式為「第二層 > 第三層」，中間以空格加大於符號分隔；第三層不可選時只帶第二層。留空時採用上方預設值。'),
                            TextInput::make('action_json.category_paths.SSI')
                                ->label('SSI 案件分類路徑'),
                            TextInput::make('action_json.category_paths.IQS')
                                ->label('IQS 案件分類路徑'),
                        ]),

                    Fieldset::make('各問卷類別案件描述模板')
                        ->schema([
                            Textarea::make('action_json.description_templates.CSI')
                                ->label('CSI 描述模板')
                                ->helperText('可使用 {{survey_category_label}}（服務／銷售滿意度回饋）、{{survey_category}}、{{submitted_at}}、{{delivery_date}}、{{open_answer}}。')
                                ->rows(5)
                                ->required(),
                            Textarea::make('action_json.description_templates.SSI')
                                ->label('SSI 描述模板')
                                ->rows(5)
                                ->required(),
                            Textarea::make('action_json.description_templates.IQS')
                                ->label('IQS 描述模板')
                                ->rows(5)
                                ->required(),
                        ]),

                    KeyValue::make('action_json.gender_mapping')
                        ->label('性別代碼對照')
                        ->keyLabel('來源值')
                        ->valueLabel('DMS 值')
                        ->default([
                            'M' => 'M',
                            'F' => 'F',
                            'O' => 'O',
                        ])
                        ->helperText('DMS 值僅允許 M、F、O；請先以 QA 資料驗證。'),

                    Toggle::make('action_json.empty_response_is_success')
                        ->label('空白回應視為成功')
                        ->helperText('只有取得廠商書面確認後才可開啟，並將「回應成功／失敗判定」標記為已確認。')
                        ->default(false),

                    Section::make('參數確認')
                        ->description('每個必要項目需記錄目前值、確認狀態與備註；只有全部為「已確認」才能正式啟用。')
                        ->schema(self::dmsConfirmationFields()),
                ]),

            Section::make('DMS 維修抵用劵設定')
                ->key('dms_voucher_settings')
                ->columns(1)
                ->description('此功能會串接 DMS，但使用獨立的 API 接口與參數，不沿用顧關立案的 ws_setTicket。')
                ->visible(fn (Get $get): bool => $get('action_json.type') === 'dms_voucher')
                ->schema([
                    TextInput::make('action_json.profile')
                        ->label('執行環境')
                        ->readOnly()
                        ->default(fn (): string => (string) config('survey-core.triggers.dms.profile', 'qa'))
                        ->dehydrateStateUsing(fn (): string => (string) config('survey-core.triggers.dms.profile', 'qa'))
                        ->helperText('QA／正式環境仍由系統設定控制；抵用劵 API 的 endpoint 與認證方式待文件確認。'),

                    Placeholder::make('dms_voucher_spec_status')
                        ->label('API 規格狀態')
                        ->content('待客戶提供 API 文件；完成接口、認證、請求參數與成功判定確認前，系統會強制保持停用。'),
                ]),
        ]);
    }

    /**
     * @return array<Fieldset>
     */
    private static function dmsConfirmationFields(): array
    {
        return collect(self::DMS_CONFIRMATIONS)
            ->map(fn (string $label, string $key): Fieldset => Fieldset::make($label)
                ->columns(3)
                ->schema([
                    TextInput::make("action_json.parameter_confirmation_values.{$key}")
                        ->label('目前值／來源')
                        ->maxLength(1000),
                    Select::make("action_json.parameter_confirmations.{$key}")
                        ->label('確認狀態')
                        ->options([
                            'pending' => '待確認',
                            'tested' => '已測試',
                            'confirmed' => '已確認',
                        ])
                        ->default('pending')
                        ->required(),
                    Textarea::make("action_json.parameter_confirmation_notes.{$key}")
                        ->label('備註')
                        ->rows(2)
                        ->maxLength(2000),
                ]))
            ->values()
            ->all();
    }

    private static function dmsProfileReadiness(): string
    {
        $profile = (string) config('survey-core.triggers.dms.profile', 'qa');
        $profileConfig = config("survey-core.triggers.dms.profiles.{$profile}", []);

        if (! is_array($profileConfig)) {
            return '尚未設定';
        }

        $missing = collect(['endpoint', 'wsdl', 'key'])
            ->reject(fn (string $key): bool => filled($profileConfig[$key] ?? null))
            ->values();

        return $missing->isEmpty()
            ? '已設定（密鑰已遮蔽）'
            : '缺少：'.$missing->implode('、');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('動作名稱')
                    ->searchable(),

                TextColumn::make('key')
                    ->label('代碼')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('action_json.endpoint')
                    ->label('Endpoint')
                    ->limit(40)
                    ->placeholder('—'),

                IconColumn::make('is_active')
                    ->label('啟用')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime('Y/m/d')
                    ->sortable(),
            ])
            ->recordUrl(null)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    self::deleteAction(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalHeading(fn (SurveyTriggerActionPreset $record): string => '刪除 '.$record->name)
            ->modalDescription('刪除後將無法復原，確定要進行嗎?')
            ->before(fn (DeleteAction $action, SurveyTriggerActionPreset $record) => self::guardAgainstDeletingReferencedPreset($action, $record));
    }

    public static function guardAgainstDeletingReferencedPreset(DeleteAction $action, SurveyTriggerActionPreset $preset): void
    {
        $referenceCount = self::referencingRuleCount($preset);

        if ($referenceCount === 0) {
            return;
        }

        Notification::make()
            ->danger()
            ->title('無法刪除 DMS 動作設定')
            ->body("此動作設定仍被 {$referenceCount} 筆發送設定引用，請先移除引用後再刪除。")
            ->persistent()
            ->send();

        $action->halt();
    }

    public static function referencingRuleCount(SurveyTriggerActionPreset $preset): int
    {
        $referenceCount = 0;

        SurveyTriggerRule::query()
            ->select(['id', 'actions_json'])
            ->lazyById()
            ->each(function (SurveyTriggerRule $rule) use ($preset, &$referenceCount): void {
                foreach ($rule->actions_json ?? [] as $definition) {
                    if (($definition['type'] ?? null) === 'preset' && (int) ($definition['preset_id'] ?? 0) === $preset->getKey()) {
                        $referenceCount++;
                        break;
                    }
                }
            });

        return $referenceCount;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurveyTriggerActionPresets::route('/'),
            'create' => CreateSurveyTriggerActionPreset::route('/create'),
            'edit' => EditSurveyTriggerActionPreset::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            DmsAttemptsRelationManager::class,
        ];
    }
}
