<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Responses;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Lalalili\SurveyCore\Actions\ExportSurveyResponsesAction;
use Lalalili\SurveyCore\Actions\ReviewSurveyResponseAction;
use Lalalili\SurveyCore\Enums\SurveyResponseCompletionStatus;
use Lalalili\SurveyCore\Enums\SurveyResponseQualityStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyResponse;
use Lalalili\SurveyCore\Models\SurveyTag;
use Lalalili\SurveyFilament\Filament\Resources\Responses\Pages\ListResponses;
use Lalalili\SurveyFilament\Filament\Resources\Responses\Pages\ViewResponse;
use Lalalili\SurveyFilament\Support\SurveyQueryScopes;

/**
 * @extends resource<SurveyResponse>
 */
class ResponseResource extends Resource
{
    protected static ?string $model = SurveyResponse::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-inbox-stack';
    }

    protected static ?string $navigationLabel = '回覆紀錄';

    protected static ?string $modelLabel = '回應';

    protected static ?string $pluralModelLabel = '回應列表';

    public static function getNavigationLabel(): string
    {
        return static::panelLabel('response')
            ?? config('survey-filament.response_navigation_label', parent::getNavigationLabel());
    }

    public static function getModelLabel(): string
    {
        return static::panelLabel('response') ?? parent::getModelLabel();
    }

    public static function getPluralModelLabel(): string
    {
        return static::panelLabel('response_plural') ?? parent::getPluralModelLabel();
    }

    /**
     * 將品質判定旗標代碼轉成可讀的中文說明。
     *
     * 旗標來源：Lalalili\SurveyCore\Actions\EvaluateResponseQualityAction
     */
    public static function qualityFlagLabel(string $flag): string
    {
        return match ($flag) {
            'too_fast' => '作答過快',
            'anomaly_duplicate' => '疑似重複作答',
            'honeypot_hit' => '命中蜜罐',
            'all_same_answer' => '答案全部相同',
            'ip_blacklisted' => 'IP 黑名單',
            default => $flag,
        };
    }

    /**
     * 依目前所在 panel 取得可覆寫的 label（config: survey-filament.panel_labels.{panelId}.{key}）。
     * 未設定時回傳 null，由呼叫端 fallback 至預設 label。
     */
    protected static function panelLabel(string $key): ?string
    {
        $panelId = Filament::getCurrentPanel()?->getId();

        if ($panelId === null) {
            return null;
        }

        $value = config("survey-filament.panel_labels.{$panelId}.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }

    public static function getNavigationGroup(): ?string
    {
        return config('survey-filament.response_navigation_group', '報表');
    }

    public static function getNavigationSort(): ?int
    {
        return 63;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('tags'))
            ->columns([
                TextColumn::make('id')->label('ID')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('response_number')
                    ->label('填答編號')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('survey.title')->label('問卷')->searchable()->sortable(),
                TextColumn::make('recipient.name')->label('收件人姓名')->searchable(),
                TextColumn::make('recipient.email')->label('收件人 Email')->searchable(),
                TextColumn::make('recipient.external_id')->label('外部 ID')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completion_status')
                    ->label('完成狀態')
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyResponseCompletionStatus ? $state->label() : $state->value)
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('quality_status')
                    ->label('接受狀態')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyResponseQualityStatus ? $state->label() : $state)
                    ->color(fn ($state) => match ($state) {
                        SurveyResponseQualityStatus::Accepted => 'success',
                        SurveyResponseQualityStatus::Flagged => 'warning',
                        SurveyResponseQualityStatus::Quarantined => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('quality_flags_json')
                    ->label('判定原因')
                    ->badge()
                    ->color('gray')
                    ->state(fn (SurveyResponse $record): array => collect($record->quality_flags_json ?? [])
                        ->map(fn (string $flag): string => self::qualityFlagLabel($flag))
                        ->all())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('tags')
                    ->label('標籤')
                    ->state(fn (SurveyResponse $record): string => $record->tags->pluck('name')->implode('、'))
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitted_at')->label('提交時間')->dateTime('Y/m/d H:i')->sortable(),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('submitted_at')
                    ->label('提交時間')
                    // 預設區間為「近一個月」（今天往前一個月至今日），由欄位 default() 帶入。
                    ->schema([
                        DatePicker::make('submitted_from')
                            ->label('起')
                            ->default(fn () => now()->subMonthNoOverflow()->toDateString()),
                        DatePicker::make('submitted_until')
                            ->label('迄')
                            ->default(fn () => now()->toDateString()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['submitted_from'] ?? null),
                                fn (Builder $q) => $q->where('submitted_at', '>=', Carbon::parse($data['submitted_from'])->startOfDay()),
                            )
                            ->when(
                                filled($data['submitted_until'] ?? null),
                                fn (Builder $q) => $q->where('submitted_at', '<=', Carbon::parse($data['submitted_until'])->endOfDay()),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['submitted_from'] ?? null)) {
                            $indicators[] = '提交時間 ≥ '.Carbon::parse($data['submitted_from'])->toDateString();
                        }

                        if (filled($data['submitted_until'] ?? null)) {
                            $indicators[] = '提交時間 ≤ '.Carbon::parse($data['submitted_until'])->toDateString();
                        }

                        return $indicators;
                    }),
                SelectFilter::make('survey_id')
                    ->label('問卷')
                    ->options(fn (): array => SurveyQueryScopes::surveys(Survey::query()->orderBy('title'))->pluck('title', 'id')->toArray())
                    ->searchable(),
                SelectFilter::make('completion_status')
                    ->label('完成狀態')
                    ->options(collect(SurveyResponseCompletionStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])),
                SelectFilter::make('quality_status')
                    ->label('接受狀態')
                    ->options(collect(SurveyResponseQualityStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])),
                TrashedFilter::make(),
                SelectFilter::make('tag')
                    ->label('標籤')
                    ->options(SurveyTag::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereKey($data['value']))
                        : $query),
                TernaryFilter::make('is_test')
                    ->label('測試資料')
                    ->placeholder('全部資料')
                    ->trueLabel('僅測試資料')
                    ->falseLabel('僅正式資料')
                    ->queries(
                        true: fn (Builder $query): Builder => static::scopeIsTestFilter($query, true),
                        false: fn (Builder $query): Builder => static::scopeIsTestFilter($query, false),
                        blank: fn (Builder $query): Builder => static::scopeIsTestFilter($query, null),
                    ),
            ])
            ->filtersFormColumns(2)
            ->columnToggleFormColumns(2)
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('set_quality_status')
                        ->label('調整接受狀態')
                        ->icon('heroicon-o-adjustments-horizontal')
                        ->visible(fn (SurveyResponse $record) => static::canEdit($record))
                        ->schema([
                            Select::make('quality_status')
                                ->label('接受狀態')
                                ->options(collect(SurveyResponseQualityStatus::cases())
                                    ->mapWithKeys(fn (SurveyResponseQualityStatus $status) => [$status->value => $status->label()]))
                                ->required()
                                ->native(false),
                            Placeholder::make('status_help')
                                ->label('狀態用途')
                                ->content('已接受：納入報表計算。待檢查：暫不納入報表，待人工確認。已隔離：排除於報表計算。'),
                            Textarea::make('notes')
                                ->label('備註')
                                ->rows(4),
                        ])
                        ->fillForm(fn (SurveyResponse $record): array => [
                            'quality_status' => $record->quality_status->value,
                            'notes' => $record->notes,
                        ])
                        ->action(function (SurveyResponse $record, array $data): void {
                            app(ReviewSurveyResponseAction::class)->execute(
                                response: $record,
                                status: SurveyResponseQualityStatus::from($data['quality_status']),
                                notes: $data['notes'] ?? null,
                                source: 'manual',
                                causer: Filament::auth()->user(),
                            );

                            Notification::make()
                                ->title('接受狀態已更新')
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make()->label('刪除'),
                    RestoreAction::make()->label('還原'),
                    ForceDeleteAction::make()->label('永久刪除'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('export_xlsx')
                        ->label('匯出')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('primary')
                        ->visible(fn () => static::canView(new SurveyResponse))
                        ->action(function (Collection $records) {
                            $surveyIds = $records->pluck('survey_id')->unique();

                            if ($surveyIds->count() > 1) {
                                Notification::make()
                                    ->title('請限定單一問卷')
                                    ->body('所選回覆橫跨多份問卷，匯出欄位無法對齊。請先用「問卷」篩選器選定單一問卷，或只勾選同一問卷的回覆。')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $survey = Survey::query()->where('id', $surveyIds->first())->first();

                            if ($survey === null) {
                                return;
                            }

                            $asyncAction = self::resolveResponseExportHandler();

                            if ($asyncAction !== null) {
                                $asyncAction($survey, $records);

                                return;
                            }

                            return app(ExportSurveyResponsesAction::class)->execute($survey, 'xlsx', $records, answersOnly: true);
                        }),
                    BulkAction::make('bulk_quarantine')
                        ->label('批次隔離')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn () => static::canEdit(new SurveyResponse))
                        ->action(function (Collection $records): void {
                            $reviewResponse = app(ReviewSurveyResponseAction::class);

                            foreach ($records as $response) {
                                if (! $response instanceof SurveyResponse) {
                                    continue;
                                }

                                $reviewResponse->execute(
                                    response: $response,
                                    status: SurveyResponseQualityStatus::Quarantined,
                                    notes: $response->notes,
                                    source: 'bulk',
                                    causer: Filament::auth()->user(),
                                );
                            }
                        }),
                    DeleteBulkAction::make()->label('批次刪除'),
                    RestoreBulkAction::make()->label('批次還原'),
                    ForceDeleteBulkAction::make()->label('批次永久刪除'),
                ]),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    /**
     * 回覆批次匯出的非同步覆寫（見 README「回覆批次匯出」）。優先取容器綁定，
     * 其次取 config；兩者皆未設定時回傳 null，呼叫端 fallback 到同步匯出。
     *
     * @return (callable(Survey, Collection<int, SurveyResponse>): void)|null
     */
    public static function resolveResponseExportHandler(): ?callable
    {
        $handler = app()->bound('survey-filament.response_export_action')
            ? app('survey-filament.response_export_action')
            : config('survey-filament.response_export_action');

        return is_callable($handler) ? $handler : null;
    }

    /**
     * @param  Builder<SurveyResponse>  $query
     * @return Builder<SurveyResponse>
     */
    public static function scopeIsTestFilter(Builder $query, ?bool $isTest): Builder
    {
        return $isTest === null ? $query : $query->where('is_test', $isTest);
    }

    public static function getEloquentQuery(): Builder
    {
        return SurveyQueryScopes::responses(parent::getEloquentQuery());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResponses::route('/'),
            'view' => ViewResponse::route('/{record}'),
        ];
    }
}
