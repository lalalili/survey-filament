<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages\CreateSurveyTriggerActionPreset;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages\EditSurveyTriggerActionPreset;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages\ListSurveyTriggerActionPresets;

/**
 * 系統管理員維護的觸發動作預設（DMS 動作）。操作員於觸發規則以下拉選單參照。
 */
class SurveyTriggerActionPresetResource extends Resource
{
    protected static ?string $model = SurveyTriggerActionPreset::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-cog-6-tooth';
    }

    protected static ?string $navigationLabel = 'DMS 動作設定';

    protected static ?string $modelLabel = 'DMS 動作';

    protected static ?string $pluralModelLabel = 'DMS 動作設定';

    public static function getNavigationGroup(): ?string
    {
        return config('survey-filament.navigation_group', '問卷管理');
    }

    public static function getNavigationSort(): ?int
    {
        return config('survey-filament.navigation_sort', 50) + 6;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('基本設定')->schema([
                Grid::make(2)->schema([
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
                ]),

                TextInput::make('description')
                    ->label('說明')
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('啟用')
                    ->default(true),
            ]),

            Section::make('HTTP 動作定義')->schema([
                Hidden::make('action_json.type')->default('http_post'),

                TextInput::make('action_json.endpoint')
                    ->label('Endpoint URL')
                    ->url()
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),

                KeyValue::make('action_json.headers')
                    ->label('Headers')
                    ->keyLabel('名稱')
                    ->valueLabel('值')
                    ->helperText('值可用 {{env.ENV_VAR}} token（執行時讀取，不入庫明文）')
                    ->columnSpanFull(),

                Textarea::make('action_json.payload_template')
                    ->label('Payload 模板（JSON）')
                    ->helperText('可使用 {{response.id}}、{{answer.field_key}}、{{recipient.payload.mobile}}、{{env.ENV_VAR}} 等 token')
                    ->rows(8)
                    ->columnSpanFull()
                    ->formatStateUsing(fn ($state): string => is_array($state)
                        ? (json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '')
                        : (string) ($state ?? ''))
                    ->dehydrateStateUsing(fn ($state) => is_string($state) ? (json_decode($state, true) ?? []) : ($state ?? [])),

                Toggle::make('action_json.require_valid_token')
                    ->label('僅限有效邀請連結觸發')
                    ->helperText('開啟後，只有「透過邀請連結（token）且未逾期」的填答才會觸發此動作。發點券請開啟，避免對匿名公開填答發券。')
                    ->default(false)
                    ->columnSpanFull(),

                Grid::make(3)->schema([
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
            ]),
        ]);
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
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListSurveyTriggerActionPresets::route('/'),
            'create' => CreateSurveyTriggerActionPreset::route('/create'),
            'edit'   => EditSurveyTriggerActionPreset::route('/{record}/edit'),
        ];
    }
}
