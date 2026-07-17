<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules;

use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\SurveyCore\Actions\EvaluateAnswerRuleTreeAction;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyField;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyCore\Models\SurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Forms\Components\RuleTreeField;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages\CreateSurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages\EditSurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages\ListSurveyTriggerRules;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\RelationManagers\TriggerDispatchesRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\RelationManagers\TriggerRuleRunsRelationManager;

class SurveyTriggerRuleResource extends Resource
{
    protected static ?string $model = SurveyTriggerRule::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-bolt';
    }

    protected static ?string $navigationLabel = 'DMS立案規則';

    protected static ?string $modelLabel = 'DMS立案規則';

    protected static ?string $pluralModelLabel = 'DMS立案規則';

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('survey-filament.trigger_rule_navigation_enabled', true);
    }

    public static function getNavigationGroup(): ?string
    {
        return '系統';
    }

    public static function getNavigationSort(): ?int
    {
        return 82;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('基本設定')->schema([
                Grid::make(2)->schema([
                    Select::make('survey_id')
                        ->label('所屬問卷')
                        ->options(Survey::query()->pluck('title', 'id'))
                        ->searchable()
                        ->required()
                        ->live(),

                    TextInput::make('name')
                        ->label('規則名稱')
                        ->required()
                        ->maxLength(255),
                ]),

                Toggle::make('is_active')
                    ->label('啟用')
                    ->default(true),
            ]),

            Section::make('排程設定')
                ->description('開啟後，系統每日於指定時間批次掃描近 N 天內、尚未派送過的填答，符合條件者自動派送動作。')
                ->schema([
                    Toggle::make('schedule_enabled')
                        ->label('啟用排程')
                        ->default(false)
                        ->live(),

                    Grid::make(2)
                        ->schema([
                            TimePicker::make('schedule_time')
                                ->label('每日執行時間')
                                ->seconds(false)
                                ->format('H:i')
                                ->required(fn (Get $get): bool => (bool) $get('schedule_enabled')),

                            TextInput::make('schedule_window_days')
                                ->label('掃描近幾天填答')
                                ->numeric()
                                ->minValue(1)
                                ->default(7)
                                ->suffix('天')
                                ->required(fn (Get $get): bool => (bool) $get('schedule_enabled')),
                        ])
                        ->visible(fn (Get $get): bool => (bool) $get('schedule_enabled')),
                ]),

            Section::make('篩選條件')->schema([
                RuleTreeField::make('rule_tree_json')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->availableFields(function (Get $get): array {
                        $surveyId = $get('survey_id');
                        if (! $surveyId) {
                            return [];
                        }

                        // meta pseudo-field（非問卷答案）：回填距邀請天數，供「X 天內回填」條件使用。
                        $meta = [[
                            'key' => EvaluateAnswerRuleTreeAction::META_DAYS_SINCE_INVITATION,
                            'label' => '回填距邀請天數',
                            'type' => 'number',
                            'options' => [],
                        ]];

                        $surveyFields = SurveyField::where('survey_id', $surveyId)
                            ->orderBy('sort_order')
                            ->get()
                            ->map(fn (SurveyField $field): array => [
                                'key' => $field->field_key,
                                'label' => $field->label ?? $field->field_key,
                                // 數值題（NPS／評分）給 number 型別，規則樹才會提供 > >= < <= 等運算子。
                                'type' => in_array($field->type->value, ['nps', 'rating', 'number', 'integer'], true) ? 'number' : 'string',
                                'options' => [],
                            ])
                            ->values()
                            ->all();

                        return array_merge($meta, $surveyFields);
                    })
                    ->default(['op' => 'AND', 'children' => []]),
            ]),

            Section::make('觸發動作')->schema([
                // 虛擬欄位：只存 preset id 陣列；實際 actions_json 由 Create/Edit 頁的
                // mutateFormData hook 在 preset_ids ↔ [{type:preset,preset_id}] 間轉換。
                Select::make('preset_ids')
                    ->label('觸發動作')
                    ->multiple()
                    ->required()
                    ->validationMessages([
                        'required' => '請選擇至少一個觸發動作。',
                    ])
                    ->options(fn (): array => SurveyTriggerActionPreset::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->helperText('選擇命中後要執行的動作。動作內容（DMS endpoint／payload 等）由系統管理員於「DMS 動作設定」維護。')
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('survey.title')
                    ->label('問卷')
                    ->searchable(),

                TextColumn::make('name')
                    ->label('規則名稱')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('啟用')
                    ->boolean(),

                IconColumn::make('schedule_enabled')
                    ->label('排程')
                    ->boolean(),

                TextColumn::make('schedule_time')
                    ->label('排程時間')
                    ->placeholder('—'),

                TextColumn::make('triggered_count')
                    ->label('觸發次數')
                    ->numeric(),

                TextColumn::make('last_triggered_at')
                    ->label('最近觸發')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime('Y/m/d')
                    ->sortable(),
            ])
            ->recordUrl(null)
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelationManagers(): array
    {
        return [
            TriggerRuleRunsRelationManager::class,
            TriggerDispatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurveyTriggerRules::route('/'),
            'create' => CreateSurveyTriggerRule::route('/create'),
            'edit' => EditSurveyTriggerRule::route('/{record}/edit'),
        ];
    }

    /**
     * preset id 陣列 → actions_json 參照格式。
     *
     * @param  array<int, int|string>  $presetIds
     * @return array<int, array{type: string, preset_id: int}>
     */
    public static function presetIdsToActions(array $presetIds): array
    {
        return collect($presetIds)
            ->filter()
            ->map(fn ($id): array => ['type' => 'preset', 'preset_id' => (int) $id])
            ->values()
            ->all();
    }

    /**
     * actions_json 參照格式 → preset id 陣列（供表單下拉預選）。
     *
     * @param  array<int, mixed>  $actions
     * @return array<int, int>
     */
    public static function actionsToPresetIds(array $actions): array
    {
        return collect($actions)
            ->filter(fn ($a): bool => is_array($a) && ($a['type'] ?? '') === 'preset')
            ->pluck('preset_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
}
