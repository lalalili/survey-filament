<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\SurveyCore\Enums\SurveyFieldType;
use Lalalili\SurveyCore\Models\AudienceList;
use Lalalili\SurveyCore\Models\SurveyField;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = '題目';

    protected static ?string $modelLabel = '題目';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('類型')
                ->options(collect(SurveyFieldType::cases())
                    ->reject(fn ($t) => $t === SurveyFieldType::Hidden)
                    ->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                ->required()
                ->live(),

            TextInput::make('label')
                ->label('欄位名稱')
                ->required()
                ->maxLength(255),

            Textarea::make('description')
                ->label('說明')
                ->rows(2)
                ->columnSpanFull(),

            TextInput::make('placeholder')
                ->label('預設提示文字')
                ->maxLength(255),

            TextInput::make('default_value')
                ->label('預設值')
                ->maxLength(255),

            TextInput::make('sort_order')
                ->label('排序')
                ->numeric()
                ->default(0),

            TextInput::make('page')
                ->label('頁次')
                ->helperText('多頁問卷時使用，填入頁碼（預設 1）。')
                ->numeric()
                ->minValue(1)
                ->default(1),

            Toggle::make('is_required')
                ->label('必填'),

            Toggle::make('is_hidden')
                ->label('隱藏（不在問卷中顯示，由系統自動填入）')
                ->live(),

            // 只有個性化欄位才需要設定收件人資料鍵值
            Select::make('personalized_key')
                ->label('名單欄位')
                ->helperText('對應名單中的欄位名稱，系統將自動填入此值。')
                ->options(function ($livewire): array {
                    $settings = $livewire->getOwnerRecord()->settings_json ?? [];
                    $audienceListId = $settings['personalization']['audience_list_id'] ?? null;

                    if (! $audienceListId) {
                        return [];
                    }

                    return AudienceList::query()->find((int) $audienceListId)?->columnOptions() ?? [];
                })
                ->searchable()
                ->nullable()
                ->visible(fn ($get) => (bool) $get('is_hidden')),

            // 跳題條件
            Select::make('show_if_field_key')
                ->label('顯示條件：當欄位')
                ->helperText('選擇觸發欄位；只有當其答案符合下方條件值時，本題才會顯示。留空代表恆顯示。')
                ->options(function ($livewire): array {
                    return SurveyField::where('survey_id', $livewire->getOwnerRecord()->id)
                        ->orderBy('sort_order')
                        ->pluck('label', 'field_key')
                        ->toArray();
                })
                ->nullable()
                ->searchable()
                ->live()
                ->columnSpanFull(),

            Select::make('show_if_value')
                ->label('顯示條件：答案值為')
                ->helperText('從觸發欄位的選項中選擇。若觸發欄位為評分，請選擇分數（1–5）。')
                ->options(function ($get, $livewire): array {
                    $triggerKey = $get('show_if_field_key');
                    if (! $triggerKey) {
                        return [];
                    }

                    $triggerField = SurveyField::where('survey_id', $livewire->getOwnerRecord()->id)
                        ->where('field_key', $triggerKey)
                        ->first();

                    if (! $triggerField) {
                        return [];
                    }

                    // 選項型：直接用 options_json（key → label 格式，Select 顯示 label 存 key）
                    if (! empty($triggerField->options_json)) {
                        return $triggerField->options_json;
                    }

                    // Rating：1–5
                    if ($triggerField->type === SurveyFieldType::Rating) {
                        return array_combine(
                            array_map('strval', range(1, 5)),
                            array_map('strval', range(1, 5)),
                        );
                    }

                    return [];
                })
                ->searchable()
                ->nullable()
                ->visible(fn ($get) => (bool) $get('show_if_field_key'))
                ->columnSpanFull(),

            // 選項：每行一個，key 自動產生（opt_1, opt_2, …）
            Textarea::make('options_json')
                ->label('選項（每行一個）')
                ->helperText('每行輸入一個選項文字，系統自動產生內部編號，順序即為顯示順序。')
                ->rows(5)
                ->afterStateHydrated(function ($component, $state) {
                    // {opt_1: 'Label', opt_2: 'Label'} → "Label\nLabel"
                    if (is_array($state) && ! empty($state)) {
                        $component->state(implode("\n", array_values($state)));
                    }
                })
                ->dehydrateStateUsing(function ($state): array {
                    // "Label\nLabel" → {opt_1: 'Label', opt_2: 'Label'}
                    if (empty(trim((string) $state))) {
                        return [];
                    }
                    $lines = array_values(array_filter(array_map('trim', explode("\n", $state))));
                    $result = [];
                    foreach ($lines as $i => $label) {
                        $result['opt_'.($i + 1)] = $label;
                    }

                    return $result;
                })
                ->columnSpanFull()
                ->visible(fn ($get) => in_array($get('type'), ['single_choice', 'multiple_choice', 'select'])),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('page')->label('頁')->sortable(),
                TextColumn::make('sort_order')->sortable()->label('#'),
                TextColumn::make('label')->label('欄位名稱')->searchable(),
                TextColumn::make('type')
                    ->label('類型')
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyFieldType ? $state->label() : $state),
                TextColumn::make('show_if_field_key')
                    ->label('條件欄位')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_required')->label('必填')->boolean(),
                IconColumn::make('is_hidden')->label('隱藏')->boolean(),
            ])
            ->headerActions([CreateAction::make()->label('新增題目')])
            ->actions([EditAction::make()->label('編輯'), DeleteAction::make()->label('刪除')])
            ->defaultSort('sort_order');
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
