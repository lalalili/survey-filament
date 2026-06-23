<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts;

use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\SurveyCore\Models\SurveyTriggerAllowedHost;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\Pages\CreateSurveyTriggerAllowedHost;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\Pages\EditSurveyTriggerAllowedHost;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\Pages\ListSurveyTriggerAllowedHosts;

class SurveyTriggerAllowedHostResource extends Resource
{
    protected static ?string $model = SurveyTriggerAllowedHost::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-shield-check';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $navigationLabel = '觸發白名單';

    protected static ?string $modelLabel = '允許 Host';

    protected static ?string $pluralModelLabel = '允許 Host 列表';

    public static function getNavigationGroup(): ?string
    {
        return config('survey-filament.navigation_group', '問卷管理');
    }

    public static function getNavigationSort(): ?int
    {
        return config('survey-filament.navigation_sort', 50) + 10;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('host')
                ->label('Host（網域名稱）')
                ->placeholder('dms.internal')
                ->helperText('僅填網域，不含協定與路徑。例：dms.internal、api.example.com')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),

            TextInput::make('description')
                ->label('說明')
                ->maxLength(255)
                ->placeholder('DMS 客訴通報 API'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('host')
                    ->label('Host')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('description')
                    ->label('說明')
                    ->placeholder('—'),

                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime('Y/m/d')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('host');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurveyTriggerAllowedHosts::route('/'),
            'create' => CreateSurveyTriggerAllowedHost::route('/create'),
            'edit' => EditSurveyTriggerAllowedHost::route('/{record}/edit'),
        ];
    }
}
