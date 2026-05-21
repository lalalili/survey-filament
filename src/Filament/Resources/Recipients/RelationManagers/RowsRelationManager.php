<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Recipients\RelationManagers;

use Filament\Actions\Action;
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
                    ->state(fn ($record): string => is_string($record->getRawOriginal('data_json'))
                        ? $record->getRawOriginal('data_json')
                        : json_encode($record->getRawOriginal('data_json') ?? []))
                    ->formatStateUsing(function ($state): string {
                        if (is_string($state)) {
                            $decoded = json_decode($state, true);
                            $state = is_array($decoded) ? $decoded : [];
                        }

                        if (! is_array($state) || empty($state)) {
                            return '—';
                        }

                        $priorityKeys = ['name', 'regono', 'mobile', 'rono', 'rbo_no', 'dlr', 'modelfamily', 'modelfamily_code'];
                        $preview = [];

                        foreach ($priorityKeys as $key) {
                            if (isset($state[$key]) && $state[$key] !== null && $state[$key] !== '') {
                                $preview[$key] = $state[$key];
                                if (count($preview) >= 4) {
                                    break;
                                }
                            }
                        }

                        if (empty($preview)) {
                            foreach ($state as $key => $value) {
                                if ($value !== null && $value !== '') {
                                    $preview[$key] = $value;
                                    if (count($preview) >= 4) {
                                        break;
                                    }
                                }
                            }
                        }

                        $text = collect($preview)
                            ->map(fn ($value, $key): string => "{$key}: {$value}")
                            ->implode(' / ');

                        $hasMore = count($state) > count($preview);

                        return $hasMore ? $text . ' ...' : $text;
                    })
                    ->action(
                        Action::make('viewRowData')
                            ->label('查看完整資料')
                            ->modalHeading(fn ($record) => '名單資料 #' . $record->id)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('關閉')
                            ->modalContent(fn ($record): \Illuminate\Contracts\View\View => view(
                                'survey-filament::modals.row-data',
                                [
                                    'data' => is_array($record->data_json)
                                        ? $record->data_json
                                        : (json_decode($record->getRawOriginal('data_json') ?? '{}', true) ?? []),
                                ]
                            ))
                    )
                    ->wrap(),
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
