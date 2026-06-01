<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Responses;

use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('survey.title')->label('問卷')->searchable()->sortable(),
                TextColumn::make('recipient.name')->label('收件人姓名')->searchable(),
                TextColumn::make('recipient.email')->label('收件人 Email')->searchable(),
                TextColumn::make('recipient.external_id')->label('外部 ID')->toggleable(),
                TextColumn::make('completion_status')
                    ->label('完成狀態')
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyResponseCompletionStatus ? $state->label() : $state->value)
                    ->badge(),
                TextColumn::make('quality_status')
                    ->label('品質')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyResponseQualityStatus ? $state->label() : $state)
                    ->color(fn ($state) => match ($state) {
                        SurveyResponseQualityStatus::Accepted    => 'success',
                        SurveyResponseQualityStatus::Flagged     => 'warning',
                        SurveyResponseQualityStatus::Quarantined => 'danger',
                        default                                  => 'gray',
                    }),
                TextColumn::make('tags')
                    ->label('標籤')
                    ->state(fn (SurveyResponse $record): string => $record->tags->pluck('name')->implode('、'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('submitted_at')->label('提交時間')->dateTime()->sortable(),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('survey_id')
                    ->label('問卷')
                    ->options(function (): array {
                        $query = Survey::query()->orderBy('title');
                        $scope = config('survey-filament.query_scope');
                        if ($scope instanceof Closure) {
                            $query = $scope($query, auth()->user());
                        }

                        return $query->pluck('title', 'id')->toArray();
                    })
                    ->searchable(),
                SelectFilter::make('quality_status')
                    ->label('品質狀態')
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
            ->headerActions([
                Action::make('export_csv')
                    ->label('匯出 CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $surveys = Survey::has('responses')->get();
                        if ($surveys->isEmpty()) {
                            return;
                        }

                        return app(ExportSurveyResponsesAction::class)->execute($surveys->first(), 'csv');
                    }),
                Action::make('export_xlsx')
                    ->label('匯出 Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(function () {
                        $surveys = Survey::has('responses')->get();
                        if ($surveys->isEmpty()) {
                            return;
                        }

                        return app(ExportSurveyResponsesAction::class)->execute($surveys->first(), 'xlsx');
                    }),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),

                    Action::make('accept')
                        ->label('標記為接受')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (SurveyResponse $record) => static::canEdit($record))
                        ->action(fn (SurveyResponse $record) => $record->update(['quality_status' => SurveyResponseQualityStatus::Accepted])),

                    Action::make('quarantine')
                        ->label('隔離')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn (SurveyResponse $record) => static::canEdit($record))
                        ->action(fn (SurveyResponse $record) => $record->update(['quality_status' => SurveyResponseQualityStatus::Quarantined])),

                    Action::make('export_survey_csv')
                        ->label('匯出此問卷 CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn (SurveyResponse $record) => static::canView($record))
                        ->action(fn (SurveyResponse $record) => app(ExportSurveyResponsesAction::class)->execute($record->survey, 'csv')),
                    Action::make('export_survey_xlsx')
                        ->label('匯出此問卷 Excel')
                        ->icon('heroicon-o-table-cells')
                        ->color('success')
                        ->visible(fn (SurveyResponse $record) => static::canView($record))
                        ->action(fn (SurveyResponse $record) => app(ExportSurveyResponsesAction::class)->execute($record->survey, 'xlsx')),
                    DeleteAction::make()->label('刪除'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('bulk_quarantine')
                        ->label('批次隔離')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->visible(fn () => static::canEdit(new SurveyResponse()))
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

        if ($scope instanceof Closure) {
            // Scope through the survey relationship so tenant isolation propagates.
            $query->whereHas('survey', fn (Builder $q) => $scope($q, auth()->user()));
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResponses::route('/'),
            'view'  => ViewResponse::route('/{record}'),
        ];
    }
}
