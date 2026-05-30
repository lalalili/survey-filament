<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyField;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyCore\Models\SurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Forms\Components\RuleTreeField;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages\CreateSurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages\EditSurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages\ListSurveyTriggerRules;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\RelationManagers\TriggerDispatchesRelationManager;

class SurveyTriggerRuleResource extends Resource
{
    protected static ?string $model = SurveyTriggerRule::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-bolt';
    }

    protected static ?string $navigationLabel = '問卷觸發規則';

    protected static ?string $modelLabel = '觸發規則';

    protected static ?string $pluralModelLabel = '觸發規則列表';

    public static function getNavigationGroup(): ?string
    {
        return config('survey-filament.navigation_group', '問卷管理');
    }

    public static function getNavigationSort(): ?int
    {
        return config('survey-filament.navigation_sort', 50) + 5;
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

            Section::make('篩選條件')->schema([
                RuleTreeField::make('rule_tree_json')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->availableFields(function (Get $get): array {
                        $surveyId = $get('survey_id');
                        if (! $surveyId) {
                            return [];
                        }

                        return SurveyField::where('survey_id', $surveyId)
                            ->orderBy('sort_order')
                            ->get()
                            ->map(fn (SurveyField $field): array => [
                                'key'     => $field->field_key,
                                'label'   => $field->label ?? $field->field_key,
                                'type'    => 'string',
                                'options' => [],
                            ])
                            ->values()
                            ->all();
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
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<int, class-string>
     */
    public static function getRelationManagers(): array
    {
        return [
            TriggerDispatchesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSurveyTriggerRules::route('/'),
            'create' => CreateSurveyTriggerRule::route('/create'),
            'edit'   => EditSurveyTriggerRule::route('/{record}/edit'),
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
