<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\SurveyCore\Actions\GenerateSurveyTokenAction;
use Lalalili\SurveyCore\Enums\SurveyRecipientStatus;
use Lalalili\SurveyCore\Enums\SurveyTokenStatus;
use Lalalili\SurveyCore\Models\SurveyRecipient;

class RecipientsRelationManager extends RelationManager
{
    protected static string $relationship = 'recipients';

    protected static ?string $title = '收件人';

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
                TextColumn::make('external_id')->label('外部 ID')->toggleable(),
                TextColumn::make('status')
                    ->label('狀態')
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyRecipientStatus ? $state->label() : $state->value),
                TextColumn::make('tokens_count')
                    ->counts('tokens')
                    ->label('Token 數'),

                TextColumn::make('invitation_viewed_at')
                    ->label('已讀取邀請')
                    ->state(
                        fn (SurveyRecipient $record): ?string => $record->tokens()
                            ->whereNotNull('viewed_at')
                            ->orderBy('viewed_at')
                            ->value('viewed_at')
                    )
                    ->dateTime()
                    ->placeholder('未讀取')
                    ->toggleable(),
            ])
            ->headerActions([CreateAction::make()->label('新增收件人')])
            ->actions([
                EditAction::make()->label('編輯'),

                Action::make('generate_token')
                    ->label('產生 Token')
                    ->icon('heroicon-o-key')
                    ->action(function (SurveyRecipient $record) {
                        app(GenerateSurveyTokenAction::class)->execute(
                            $record->survey,
                            $record,
                        );
                    }),

                Action::make('copy_link')
                    ->label('複製連結')
                    ->icon('heroicon-o-link')
                    ->action(function (SurveyRecipient $record) {
                        $token = $record->tokens()
                            ->where('status', SurveyTokenStatus::Active->value)
                            ->latest()
                            ->first();

                        if (! $token) {
                            Notification::make()
                                ->warning()
                                ->title('找不到有效 Token，請先產生一個。')
                                ->send();

                            return;
                        }

                        $survey = $record->survey;
                        $url = url(config('survey-core.route_prefix', 'survey').'/'.$survey->public_key.'?t='.$token->token);

                        $this->js("navigator.clipboard.writeText('{$url}')");

                        Notification::make()
                            ->success()
                            ->title('連結已複製')
                            ->send();
                    }),

                Action::make('deactivate_token')
                    ->label('停用 Token')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('確認停用所有 Token')
                    ->action(function (SurveyRecipient $record) {
                        $record->tokens()
                            ->where('status', SurveyTokenStatus::Active->value)
                            ->update(['status' => SurveyTokenStatus::Inactive->value]);
                    }),

                DeleteAction::make()->label('刪除'),
            ]);
    }
}
