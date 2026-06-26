<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\SurveyFilament\Support\PanelLabel;

class TagsRelationManager extends RelationManager
{
    protected static string $relationship = 'tags';

    protected static ?string $title = '標籤';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('名稱')
                ->required()
                ->maxLength(255),
            ColorPicker::make('color')
                ->label('顏色')
                ->default('#6366f1')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('名稱')->searchable(),
                ColorColumn::make('color')->label('顏色'),
                TextColumn::make('responses_count')->counts('responses')->label(PanelLabel::get('response_count') ?? '回應數'),
            ])
            ->headerActions([
                CreateAction::make()->label('新增標籤'),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()->label('編輯'),
                    DeleteAction::make()->label('刪除'),
                ]),
            ]);
    }
}
