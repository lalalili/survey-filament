<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Recipients;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\Pages\CreateRecipient;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\Pages\EditRecipient;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\Pages\ImportRecipients;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\Pages\ListRecipients;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RelationManagers\RowsRelationManager;

class RecipientResource extends Resource
{
    protected static ?string $model = AudienceList::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-users';
    }

    protected static ?string $navigationLabel = '名單';

    protected static ?string $modelLabel = '名單';

    protected static ?string $pluralModelLabel = '名單';

    public static function getNavigationGroup(): ?string
    {
        return config('survey-filament.recipient_navigation_group',
               config('survey-filament.navigation_group', '問卷管理'));
    }

    public static function getNavigationSort(): ?int
    {
        return config('survey-filament.recipient_navigation_sort',
               config('survey-filament.navigation_sort', 51));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('名單名稱')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('說明')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('名單名稱')->searchable()->sortable(),
                TextColumn::make('rows_count')->label('資料筆數')->sortable(),
                TextColumn::make('columns_json')
                    ->label('欄位')
                    ->formatStateUsing(fn ($state): string => self::formatColumnsState($state))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('imported_at')->label('匯入時間')->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('created_at')->label('建立時間')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                EditAction::make()->label('編輯'),

                Action::make('import')
                    ->label('重新匯入')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->url(fn (AudienceList $record) => static::getUrl('import', ['audience_list_id' => $record->id])),

                DeleteAction::make()->label('刪除'),
            ])
            ->bulkActions([]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $scope = config('survey-filament.query_scope');

        if ($scope instanceof Closure) {
            $query = $scope($query, auth()->user());
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            RowsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListRecipients::route('/'),
            'create' => CreateRecipient::route('/create'),
            'edit'   => EditRecipient::route('/{record}/edit'),
            'import' => ImportRecipients::route('/import'),
        ];
    }

    public static function formatColumnsState(mixed $state): string
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);
            $state = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $state)));
        }

        if (! is_array($state)) {
            return '';
        }

        $labels = array_map(
            fn (mixed $col) => is_array($col) ? ($col['label'] ?? $col['key'] ?? '') : (string) $col,
            array_slice($state, 0, 6)
        );

        return implode('、', array_filter($labels));
    }
}
