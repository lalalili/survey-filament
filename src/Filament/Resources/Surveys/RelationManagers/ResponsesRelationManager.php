<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lalalili\SurveyCore\Enums\SurveyResponseCompletionStatus;
use Lalalili\SurveyFilament\Support\PanelLabel;

class ResponsesRelationManager extends RelationManager
{
    protected static string $relationship = 'responses';

    protected static ?string $title = '回應';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return PanelLabel::get('response') ?? static::$title;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('recipient.name')->label('收件人姓名')->searchable(),
                TextColumn::make('recipient.email')->label('收件人 Email')->searchable(),
                TextColumn::make('completion_status')
                    ->label('完成狀態')
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyResponseCompletionStatus ? $state->label() : $state->value),
                TextColumn::make('submitted_at')->label('提交時間')->dateTime()->sortable(),
                TextColumn::make('ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->actions([])
            ->headerActions([]);
    }
}
