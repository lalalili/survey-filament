<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Recipients;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\AudienceCore\Support\AudienceListReferenceChecker;
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

    public static function shouldRegisterNavigation(): bool
    {
        if (! (bool) config('survey-filament.recipient_navigation_enabled', true)) {
            return false;
        }

        if (! (bool) config('survey-filament.recipient_navigation_super_admin_only', false)) {
            return true;
        }

        return (bool) data_get(auth()->user(), 'is_super_admin', false);
    }

    public static function getNavigationGroup(): ?string
    {
        return config(
            'survey-filament.recipient_navigation_group',
            config('survey-filament.navigation_group', '問卷管理')
        );
    }

    public static function getNavigationSort(): ?int
    {
        return config(
            'survey-filament.recipient_navigation_sort',
            config('survey-filament.navigation_sort', 51)
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('名單名稱')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('schema_profile')
                ->label('資料設定檔')
                ->options(fn (): array => self::schemaProfileOptions())
                ->placeholder('未指定')
                ->helperText('用於確認名單欄位結構是否符合問卷分類。')
                ->native(false),

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
                TextColumn::make('schema_profile')
                    ->label('資料設定檔')
                    ->formatStateUsing(fn (?string $state): string => self::schemaProfileOptions()[$state] ?? $state ?? '—')
                    ->badge()
                    ->color(fn (?string $state): string => filled($state) ? 'info' : 'gray')
                    ->sortable(),
                TextColumn::make('rows_count')->label('資料筆數')->sortable(),
                TextColumn::make('columns_json')
                    ->label('欄位')
                    ->state(fn (AudienceList $record): string => self::formatColumnsState($record->columns_json))
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('imported_at')->label('匯入時間')->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('created_at')->label('建立時間')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()->label('編輯'),

                    Action::make('import')
                        ->label('重新匯入')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->url(fn (AudienceList $record) => static::getUrl('import', ['audience_list_id' => $record->id])),

                    self::deleteAction(),
                ]),
            ])
            ->bulkActions([]);
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->label('刪除')
            ->modalHeading(fn (AudienceList $record): string => '刪除 '.$record->name)
            ->modalDescription('刪除後將無法復原，且會一併刪除名單資料列、分群、活動及管道對應；匯入紀錄將保留但解除名單關聯，確定要進行嗎?')
            ->before(function (DeleteAction $action, AudienceList $record): void {
                self::prepareActivityDispatchReferencesForDelete($record, $action);
            });
    }

    public static function prepareActivityDispatchReferencesForDelete(AudienceList $record, DeleteAction $action): int
    {
        $references = (new AudienceListReferenceChecker)->check($record);

        if ($references->isEmpty()) {
            return 0;
        }

        if (config('survey-filament.recipient_activity_dispatch_delete_strategy', 'restrict') === 'restrict') {
            Notification::make()
                ->danger()
                ->title('無法刪除名單')
                ->body('此名單仍被引用：'.implode('、', $references->summaryLines()).'。請保留名單以維持歷史紀錄完整。')
                ->persistent()
                ->send();

            $action->halt();

            return $references->activityDispatches;
        }

        return self::detachActivityDispatchReferences($record);
    }

    public static function activityDispatchReferencesCount(AudienceList $record): int
    {
        return (new AudienceListReferenceChecker)->check($record)->activityDispatches;
    }

    public static function detachActivityDispatchReferences(AudienceList $record): int
    {
        if (! self::hasActivityDispatchAudienceListRowColumn()) {
            return 0;
        }

        $values = ['audience_list_row_id' => null];

        if (SchemaFacade::hasColumn('activity_dispatches', 'updated_at')) {
            $values['updated_at'] = now();
        }

        return DB::table('activity_dispatches')
            ->whereIn('audience_list_row_id', $record->rows()->select('id'))
            ->update($values);
    }

    private static function hasActivityDispatchAudienceListRowColumn(): bool
    {
        return SchemaFacade::hasTable('activity_dispatches')
            && SchemaFacade::hasColumn('activity_dispatches', 'audience_list_row_id');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $scope = config('survey-filament.query_scope');

        if (is_callable($scope)) {
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
            'index' => ListRecipients::route('/'),
            'create' => CreateRecipient::route('/create'),
            'edit' => EditRecipient::route('/{record}/edit'),
            'import' => ImportRecipients::route('/import'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     */
    public static function formatColumnsState(array $columns): string
    {
        $visible = array_slice($columns, 0, 6);
        $labels = array_map(
            fn (array $col): string => (string) ($col['label'] ?? $col['key'] ?? ''),
            $visible,
        );

        if (count($columns) > count($visible)) {
            $labels[] = '...';
        }

        return implode('、', array_filter($labels));
    }

    /**
     * @return array<string, string>
     */
    public static function schemaProfileOptions(): array
    {
        $options = config('survey-filament.audience_schema_profile_options', []);

        if (! is_array($options)) {
            return [];
        }

        return collect($options)
            ->mapWithKeys(fn (mixed $label, mixed $value): array => [(string) $value => (string) $label])
            ->all();
    }
}
