<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Responses;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Lalalili\SurveyCore\Actions\ExportSurveyResponsesAction;
use Lalalili\SurveyCore\Enums\SurveyResponseCompletionStatus;
use Lalalili\SurveyCore\Enums\SurveyResponseQualityStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyResponse;
use Lalalili\SurveyCore\Models\SurveyTag;
use Lalalili\SurveyFilament\Filament\Resources\Responses\Pages\ListResponses;
use Lalalili\SurveyFilament\Filament\Resources\Responses\Pages\ViewResponse;

class ResponseResource extends Resource
{
    protected static ?string $model = SurveyResponse::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-inbox-stack';
    }

    protected static ?string $navigationLabel = '回應';

    protected static ?string $modelLabel = '回應';

    protected static ?string $pluralModelLabel = '回應列表';

    public static function getNavigationLabel(): string
    {
        return static::panelLabel('response') ?? parent::getNavigationLabel();
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
        return config('survey-filament.navigation_group', '問卷管理');
    }

    public static function getNavigationSort(): ?int
    {
        return config('survey-filament.navigation_sort', 52);
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
                    ->options(function (): array {
                        $query = Survey::query()->orderBy('title');
                        $scope = config('survey-filament.query_scope');
                        if (is_callable($scope)) {
                            $query = $scope($query, auth()->user());
                        }

                        return $query->pluck('title', 'id')->toArray();
                    })
                    ->searchable(),
                SelectFilter::make('completion_status')
                    ->label('完成狀態')
                    ->options(collect(SurveyResponseCompletionStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])),
                SelectFilter::make('quality_status')
                    ->label('接受狀態')
                    ->options(collect(SurveyResponseQualityStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])),
                SelectFilter::make('tag')
                    ->label('標籤')
                    ->options(SurveyTag::query()->orderBy('name')->pluck('name', 'id'))
                    ->query(fn ($query, array $data) => filled($data['value'] ?? null)
                        ? $query->whereHas('tags', fn ($tagQuery) => $tagQuery->whereKey($data['value']))
                        : $query),
                TernaryFilter::make('is_test')
                    ->label('測試資料')
                    ->placeholder('僅正式資料')
                    ->trueLabel('僅測試資料')
                    ->falseLabel('僅正式資料')
                    ->queries(
                        true: fn ($query) => $query->where('is_test', true),
                        false: fn ($query) => $query->where('is_test', false),
                        blank: fn ($query) => $query->where('is_test', false),
                    ),
            ])
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
                        ])
                        ->fillForm(fn (SurveyResponse $record): array => [
                            'quality_status' => $record->quality_status->value,
                        ])
                        ->action(function (SurveyResponse $record, array $data): void {
                            $record->update([
                                'quality_status' => SurveyResponseQualityStatus::from($data['quality_status']),
                            ]);

                            Notification::make()
                                ->title('接受狀態已更新')
                                ->success()
                                ->send();
                        }),

                    DeleteAction::make()->label('刪除'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('export_xlsx')
                        ->label('匯出 Excel')
                        ->icon('heroicon-o-table-cells')
                        ->color('success')
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

                            $surveyId = $surveyIds->first();
                            if (! is_numeric($surveyId)) {
                                return;
                            }

                            $survey = Survey::query()->whereKey((int) $surveyId)->first();

                            if ($survey === null) {
                                return;
                            }

                            $responses = SurveyResponse::query()
                                ->whereKey($records->pluck('id')->all())
                                ->get();

                            $asyncAction = app()->bound('survey-filament.response_export_action')
                                ? app('survey-filament.response_export_action')
                                : config('survey-filament.response_export_action');

                            if (is_callable($asyncAction)) {
                                $asyncAction($survey, $responses);

                                return;
                            }

                            return app(ExportSurveyResponsesAction::class)->execute($survey, 'xlsx', $responses, answersOnly: true);
                        }),
                    BulkAction::make('bulk_quarantine')
                        ->label('批次隔離')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn () => static::canEdit(new SurveyResponse))
                        ->action(fn ($records) => $records->each->update(['quality_status' => SurveyResponseQualityStatus::Quarantined])),
                    DeleteBulkAction::make()->label('批次刪除'),
                ]),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $scope = config('survey-filament.query_scope');

        if (is_callable($scope)) {
            // Scope through the survey relationship so tenant isolation propagates.
            $query->whereHas('survey', fn (Builder $q) => $scope($q, auth()->user()));
        }

        $responseScope = config('survey-filament.response_query_scope');

        if (is_callable($responseScope)) {
            // Applied directly to the response query so it can filter by recipient payload.
            $query = $responseScope($query, auth()->user());
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResponses::route('/'),
            'view' => ViewResponse::route('/{record}'),
        ];
    }
}
