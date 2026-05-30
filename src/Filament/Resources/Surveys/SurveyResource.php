<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lalalili\SurveyCore\Actions\CloseSurveyAction;
use Lalalili\SurveyCore\Actions\DuplicateSurveyAction;
use Lalalili\SurveyCore\Actions\ExportSurveyBuilderSchemaAction;
use Lalalili\SurveyCore\Actions\PublishSurveyAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Enums\SurveyUniquenessMode;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Responses\ResponseResource;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\CreateSurvey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\EditSurvey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\EditSurveyBuilder;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\ListSurveys;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\SurveyAnalytics;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\ViewSurvey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\CollectorsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\FieldsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\RecipientsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\ResponsesRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\TagsRelationManager;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    protected static ?string $navigationLabel = '問卷';

    protected static ?string $modelLabel = '問卷';

    protected static ?string $pluralModelLabel = '問卷列表';

    public static function getNavigationGroup(): ?string
    {
        return config('survey-filament.navigation_group', '問卷管理');
    }

    public static function getNavigationSort(): ?int
    {
        return config('survey-filament.navigation_sort', 50);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('標題')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('描述')
                ->rows(3)
                ->columnSpanFull(),

            Select::make('status')
                ->label('狀態')
                ->options(collect(SurveyStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                ->required()
                ->default(SurveyStatus::Draft->value),

            TextInput::make('public_key')
                ->label('公開金鑰')
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit'),

            Toggle::make('allow_anonymous')
                ->label('允許匿名填寫')
                ->default(false),

            Toggle::make('allow_multiple_submissions')
                ->label('允許多次提交')
                ->default(false),

            TextInput::make('max_responses')
                ->label('回收上限')
                ->numeric()
                ->minValue(1)
                ->placeholder('不限制'),

            DateTimePicker::make('starts_at')
                ->label('開始時間'),

            DateTimePicker::make('ends_at')
                ->label('結束時間'),

            Textarea::make('submit_success_message')
                ->label('提交成功訊息')
                ->rows(2)
                ->columnSpanFull(),

            Textarea::make('quota_message')
                ->label('額滿訊息')
                ->rows(2)
                ->columnSpanFull(),

            Select::make('uniqueness_mode')
                ->label('防重填模式')
                ->options(collect(SurveyUniquenessMode::cases())->mapWithKeys(fn ($mode) => [$mode->value => $mode->label()]))
                ->default(SurveyUniquenessMode::None->value)
                ->required(),

            TextInput::make('uniqueness_message')
                ->label('重複填寫提示')
                ->maxLength(255),

            Select::make('settings_json.personalization.audience_list_id')
                ->label('個性化名單')
                ->options(fn (): array => AudienceList::query()->orderBy('name')->pluck('name', 'id')->toArray())
                ->searchable()
                ->nullable()
                ->live()
                ->helperText('選擇名單後，可用名單欄位自動填入個性化題目。'),

            Toggle::make('settings_json.personalization.required')
                ->label('必須使用個性化網址填寫')
                ->default(true)
                ->helperText('啟用後，未帶個性化 token 的公開問卷連結不能填寫。'),

            Select::make('settings_json.personalization.name_column')
                ->label('姓名欄位')
                ->options(fn ($get): array => self::audienceColumnOptions($get('settings_json.personalization.audience_list_id')))
                ->searchable()
                ->nullable()
                ->helperText('同步名單時寫入收件人姓名，方便後台辨識、匯出與後續訊息個人化。'),

            Select::make('settings_json.personalization.email_column')
                ->label('Email 欄位')
                ->options(fn ($get): array => self::audienceColumnOptions($get('settings_json.personalization.audience_list_id')))
                ->searchable()
                ->nullable()
                ->helperText('同步為收件人 Email，Email 活動選擇此問卷時可沿用此欄位作為收件地址來源。'),

            Select::make('settings_json.personalization.external_id_column')
                ->label('外部 ID 欄位')
                ->options(fn ($get): array => self::audienceColumnOptions($get('settings_json.personalization.audience_list_id')))
                ->searchable()
                ->nullable()
                ->helperText('同步 CRM、DMS 或會員系統 ID，便於對帳、去重與跨系統追蹤；未指定時使用名單資料列 ID。'),

            KeyValue::make('settings_json.personalization.field_mappings')
                ->label('個性化題目欄位對應')
                ->helperText('左側填問卷 field_key，右側填名單欄位名稱；題目本身也可在「題目」頁籤設定個性化鍵值。')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('標題')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Survey $record) => route('survey.show', $record->public_key))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->iconPosition(IconPosition::After)
                    ->iconColor('primary'),

                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        SurveyStatus::Published => 'success',
                        SurveyStatus::Closed    => 'warning',
                        SurveyStatus::Archived  => 'danger',
                        default                 => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyStatus ? $state->label() : $state),

                TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label('題目數')
                    ->url(fn (Survey $record) => SurveyResource::getUrl('view', ['record' => $record]))
                    ->color('primary'),

                TextColumn::make('recipients_count')
                    ->counts('recipients')
                    ->label('個性化連結數'),

                TextColumn::make('responses_count')
                    ->counts('responses')
                    ->label('回應數')
                    // Filament v5 將 table filters 的 query string 別名為 'filters'（#[Url(as: 'filters')]）；
                    // 舊的 'tableFilters' key 不會被還原，會導致連結帶入後過濾失效（顯示全部回應）。
                    ->url(fn (Survey $record) => ResponseResource::getUrl('index').'?'.http_build_query(['filters' => ['survey_id' => ['value' => $record->getKey()]]]))
                    ->color('primary'),

                TextColumn::make('starts_at')
                    ->label('開始時間')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ends_at')
                    ->label('結束時間')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('狀態')
                    ->options(collect(SurveyStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
            ])
            ->actions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label('編輯')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Survey $record): string => SurveyResource::getUrl('builder', ['record' => $record])),
                    Action::make('analytics')
                        ->label('分析')
                        ->icon('heroicon-o-chart-bar-square')
                        ->url(fn (Survey $record): string => SurveyResource::getUrl('analytics', ['record' => $record])),
                    Action::make('export_builder_json')
                        ->label('匯出問卷 JSON')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn (Survey $record) => static::canView($record))
                        ->action(function (Survey $record) {
                            $exportSchema = app(ExportSurveyBuilderSchemaAction::class);

                            return response()->streamDownload(
                                function () use ($exportSchema, $record): void {
                                    echo $exportSchema->toJson($record);
                                },
                                $exportSchema->filename($record),
                                ['Content-Type' => 'application/json; charset=UTF-8'],
                            );
                        }),

                    Action::make('publish')
                        ->label('發佈')
                        ->icon('heroicon-o-rocket-launch')
                        ->color('success')
                        ->visible(fn (Survey $record) => static::canEdit($record) && in_array($record->status, [SurveyStatus::Draft, SurveyStatus::Closed]))
                        ->action(fn (Survey $record) => app(PublishSurveyAction::class)->execute($record))
                        ->requiresConfirmation(),

                    Action::make('close')
                        ->label('關閉')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->visible(fn (Survey $record) => static::canEdit($record) && $record->status === SurveyStatus::Published)
                        ->action(fn (Survey $record) => app(CloseSurveyAction::class)->execute($record))
                        ->requiresConfirmation(),

                    Action::make('duplicate')
                        ->label('複製')
                        ->icon('heroicon-o-document-duplicate')
                        ->visible(fn () => static::canCreate())
                        ->action(fn (Survey $record) => app(DuplicateSurveyAction::class)->execute($record)),

                    Action::make('clear_responses')
                        ->label('清除全部回應')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (Survey $record) => static::canDelete($record))
                        ->requiresConfirmation()
                        ->modalHeading('清除全部回應')
                        ->modalDescription(fn (Survey $record): string => "確定要刪除「{$record->title}」的所有回應嗎？此操作無法復原。")
                        ->modalSubmitActionLabel('確認清除')
                        ->action(fn (Survey $record) => $record->responses()->delete()),

                    DeleteAction::make()->label('刪除'),
                ]),
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
            FieldsRelationManager::class,
            CollectorsRelationManager::class,
            RecipientsRelationManager::class,
            ResponsesRelationManager::class,
            TagsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'     => ListSurveys::route('/'),
            'create'    => CreateSurvey::route('/create'),
            'edit'      => EditSurvey::route('/{record}/edit'),
            'builder'   => EditSurveyBuilder::route('/{record}/builder'),
            'analytics' => SurveyAnalytics::route('/{record}/analytics'),
            'view'      => ViewSurvey::route('/{record}'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function audienceColumnOptions(mixed $audienceListId): array
    {
        if (! $audienceListId) {
            return [];
        }

        $audienceList = AudienceList::query()->find((int) $audienceListId);

        return $audienceList?->columnOptions() ?? [];
    }
}
