<?php

namespace Lalalili\SurveyFilament\Filament\Resources\DmsAttempts;

use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lalalili\SurveyCore\Enums\SurveyTriggerActionAttemptStatus;
use Lalalili\SurveyCore\Models\SurveyTriggerActionAttempt;
use Lalalili\SurveyFilament\Filament\Resources\DmsAttempts\Pages\ListDmsAttempts;
use Lalalili\SurveyFilament\Support\DmsAttemptDetailSchema;
use Lalalili\SurveyFilament\Support\DmsAttemptPresenter;

/**
 * 跨動作設定的 DMS 傳送紀錄總覽（唯讀）。動作設定頁的關聯清單只看得到單一設定，
 * 維運需要一個能一次篩出「待判讀／失敗」的入口。
 */
class DmsAttemptResource extends Resource
{
    protected static ?string $model = SurveyTriggerActionAttempt::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    protected static ?string $navigationLabel = 'DMS傳送紀錄';

    protected static ?string $modelLabel = 'DMS傳送紀錄';

    protected static ?string $pluralModelLabel = 'DMS傳送紀錄';

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('survey-filament.trigger_action_preset_navigation_enabled', true);
    }

    public static function getNavigationGroup(): ?string
    {
        return '系統';
    }

    public static function getNavigationSort(): ?int
    {
        return 84;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->formatStateUsing(fn (SurveyTriggerActionAttemptStatus $status): string => DmsAttemptPresenter::statusLabel($status))
                    ->color(fn (SurveyTriggerActionAttemptStatus $status): string => DmsAttemptPresenter::statusColor($status)),
                TextColumn::make('ticket_no')
                    ->label('Ticket')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('preset.name')
                    ->label('動作設定')
                    ->placeholder('—'),
                TextColumn::make('mode')
                    ->label('模式')
                    ->formatStateUsing(fn (?string $state): string => DmsAttemptPresenter::modeLabel($state)),
                TextColumn::make('profile')
                    ->label('Profile'),
                TextColumn::make('sent_at')
                    ->label('傳送時間')
                    ->dateTime('Y/m/d H:i:s')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('response_http_status')
                    ->label('HTTP')
                    ->placeholder('—'),
                TextColumn::make('duration_ms')
                    ->label('耗時')
                    ->suffix(' ms')
                    ->placeholder('—'),
                TextColumn::make('error')
                    ->label('錯誤')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('狀態')
                    ->multiple()
                    ->options(DmsAttemptPresenter::statusOptions()),
                SelectFilter::make('profile')
                    ->label('Profile')
                    ->options([
                        'qa' => 'qa',
                        'production' => 'production',
                    ]),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('起始日'),
                        DatePicker::make('until')->label('結束日'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('created_at', '<=', $date)))
                    ->indicateUsing(fn (Get $get): ?string => filled($get('from')) || filled($get('until'))
                        ? '建立時間：'.($get('from') ?: '不限').' ~ '.($get('until') ?: '不限')
                        : null),
            ])
            ->recordUrl(null)
            ->recordActions([
                ViewAction::make()
                    ->label('查看／複製廠商除錯資訊')
                    ->modalHeading('DMS 傳送明細（敏感資訊已遮蔽）')
                    ->schema(fn (Schema $schema): Schema => $schema->components(
                        DmsAttemptDetailSchema::components(),
                    )),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('action_type', 'dms_soap')
            ->with('preset');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDmsAttempts::route('/'),
        ];
    }
}
