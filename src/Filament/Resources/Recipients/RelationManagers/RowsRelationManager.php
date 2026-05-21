<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Recipients\RelationManagers;

use Filament\Forms\Components\KeyValue;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RowsRelationManager extends RelationManager
{
    protected static string $relationship = 'rows';

    protected static ?string $title = '資料';

    protected static ?string $modelLabel = '名單資料';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            KeyValue::make('data_json')
                ->label('欄位資料')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('data_json')
                    ->label('資料')
                    ->formatStateUsing(fn ($state): string => collect(is_array($state) ? $state : [])
                        ->take(5)
                        ->map(fn ($value, $key): string => "{$key}: {$value}")
                        ->implode(' / '))
                    ->searchable(),
                TextColumn::make('status')->label('狀態')->badge(),
                TextColumn::make('created_at')->label('建立時間')->dateTime()->sortable(),
            ])
            ->defaultSort('id');
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
