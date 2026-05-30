<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                Repeater::make('actions_json')
                    ->label('動作列表')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('type')
                            ->label('動作類型')
                            ->options(['http_post' => 'HTTP POST'])
                            ->required()
                            ->default('http_post'),

                        TextInput::make('name')
                            ->label('動作名稱')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('endpoint')
                            ->label('Endpoint URL')
                            ->url()
                            ->required()
                            ->maxLength(500),

                        Textarea::make('payload_template')
                            ->label('Payload 模板（JSON）')
                            ->helperText('可使用 {{response.id}}、{{answer.field_key}}、{{recipient.payload.mobile}}、{{env.ENV_VAR}} 等 token')
                            ->rows(6)
                            ->columnSpanFull(),

                        Toggle::make('require_valid_token')
                            ->label('僅限有效邀請連結觸發')
                            ->helperText('開啟後，只有「透過邀請連結（token）且未逾期」的填答才會觸發此動作。發點券請開啟，避免對匿名公開填答發券。')
                            ->default(false)
                            ->columnSpanFull(),

                        Grid::make(3)->schema([
                            TextInput::make('timeout')
                                ->label('Timeout（秒）')
                                ->numeric()
                                ->default(10),

                            TextInput::make('retry.times')
                                ->label('重試次數')
                                ->numeric()
                                ->default(3),

                            TextInput::make('retry.sleep_ms')
                                ->label('重試間隔（ms）')
                                ->numeric()
                                ->default(200),
                        ]),
                    ])
                    ->addActionLabel('新增動作')
                    ->collapsible()
                    ->itemLabel(fn (array $state): ?string => $state['name'] ?? null),
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
}
