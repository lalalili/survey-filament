<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\View as ViewFacade;
use Lalalili\MarketingAutomation\Enums\Channel;
use Lalalili\SurveyCore\Enums\SurveyRecipientStatus;
use Lalalili\SurveyCore\Enums\SurveyTokenStatus;
use Lalalili\SurveyCore\Models\SurveyRecipient;
use Lalalili\SurveyCore\Models\SurveyToken;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = '收件人';

    protected static ?string $modelLabel = '收件人';

    protected static ?string $pluralModelLabel = '收件人';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('姓名')->maxLength(255),
            TextInput::make('email')->label('Email')->email()->maxLength(255),
            TextInput::make('external_id')->label('外部 ID')->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('姓名')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('external_id')->label('ID')->searchable()->toggleable(isToggledHiddenByDefault: true),

                // 手機／車牌取自名單 payload_json（上游調查名單欄位），供發券回填 DMS 辨識顧客。
                TextColumn::make('mobile')
                    ->label('手機')
                    ->state(fn (SurveyRecipient $record): ?string => $record->payload_json['mobile'] ?? null)
                    ->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('payload_json->mobile', 'like', "%{$search}%")),

                TextColumn::make('regono')
                    ->label('車牌')
                    ->state(fn (SurveyRecipient $record): ?string => $record->payload_json['regono'] ?? null)
                    ->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('payload_json->regono', 'like', "%{$search}%")),

                TextColumn::make('status')
                    ->label('狀態')
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyRecipientStatus ? $state->label() : $state->value)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tokens_count')
                    ->counts('tokens')
                    ->label('已產生連結數')
                    ->toggleable(isToggledHiddenByDefault: true),

                // 邀請信被開啟（email tracking pixel），由 email-campaign 回寫。
                TextColumn::make('invitation_opened_at')
                    ->label('Email開啟時間')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('未開啟')
                    ->sortable()
                    ->toggleable(),

                // MIN 聚合會略過 NULL，等同「最早的 viewed_at」；用 withMin 避免逐列子查詢
                TextColumn::make('invitation_viewed_at')
                    ->label('連結開啟時間')
                    ->state(fn (SurveyRecipient $record): ?string => $record->getAttribute('tokens_min_viewed_at'))
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('未開啟')
                    ->toggleable(),
            ])
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withMin('tokens', 'viewed_at'))
            ->headerActions([CreateAction::make()->label('新增收件人')])
            ->actions([
                ActionGroup::make([
                    EditAction::make()->label('編輯'),

                    Action::make('show_links')
                        ->label('顯示連結')
                        ->icon('heroicon-o-link')
                        ->modalHeading('個人化連結')
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('關閉')
                        ->modalContent(fn (SurveyRecipient $record): View => ViewFacade::make('survey-filament::modals.recipient-links', [
                            'links' => $this->activeLinksFor($record),
                        ])),

                    DeleteAction::make()->label('刪除'),
                ]),
            ]);
    }

    /**
     * 收件人所有「啟用中」的個人化連結，含對應通道與產生時間（新到舊）。
     *
     * @return Collection<int, array{channel: ?string, created_at: ?Carbon, url: string}>
     */
    private function activeLinksFor(SurveyRecipient $record): Collection
    {
        $routePrefix = config('survey-core.route_prefix', 'survey');
        $publicKey = $record->survey->public_key;

        return $record->tokens()
            ->where('status', SurveyTokenStatus::Active->value)
            ->latest()
            ->get()
            ->map(fn (SurveyToken $token): array => [
                'channel' => $this->resolveChannelLabel($token->id),
                'created_at' => $token->created_at,
                'url' => url($routePrefix.'/'.$publicKey.'?t='.$token->token),
            ]);
    }

    /**
     * 由 token 解析其發送通道（透過 marketing-automation 的 short link → dispatch）。
     * survey-filament 不硬依賴 marketing-automation：無對應資料表或 Channel enum 時優雅退回原始值/null。
     */
    private function resolveChannelLabel(int $tokenId): ?string
    {
        if (! SchemaFacade::hasTable('activity_short_links') || ! SchemaFacade::hasTable('activity_dispatches')) {
            return null;
        }

        $channel = DB::table('activity_short_links')
            ->join('activity_dispatches', 'activity_dispatches.id', '=', 'activity_short_links.activity_dispatch_id')
            ->where('activity_short_links.survey_token_id', $tokenId)
            ->value('activity_dispatches.channel');

        if ($channel === null) {
            return null;
        }

        $channelEnum = Channel::class;

        if (class_exists($channelEnum)) {
            return $channelEnum::tryFrom($channel)?->label() ?? $channel;
        }

        return $channel;
    }
}
