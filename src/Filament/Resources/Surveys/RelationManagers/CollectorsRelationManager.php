<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Lalalili\SurveyCore\Models\SurveyCollector;

class CollectorsRelationManager extends RelationManager
{
    protected static string $relationship = 'collectors';

    protected static ?string $title = '回收管道';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('類型')
                ->options(self::typeOptions())
                ->default('web_link')
                ->required(),

            TextInput::make('name')
                ->label('名稱')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($set, ?string $state) => $set('slug', Str::slug($state ?? ''))),

            TextInput::make('slug')
                ->label('Slug')
                ->helperText('公開短連結會使用 /s/{slug}。留空時會由系統自動產生。')
                ->maxLength(120)
                ->unique(ignoreRecord: true),

            Select::make('status')
                ->label('狀態')
                ->options([
                    'active' => '啟用',
                    'paused' => '暫停',
                    'archived' => '封存',
                ])
                ->default('active')
                ->required(),

            KeyValue::make('tracking_json')
                ->label('UTM / 追蹤參數')
                ->keyLabel('鍵')
                ->valueLabel('值')
                ->nullable()
                ->columnSpanFull(),

            KeyValue::make('settings_json')
                ->label('進階設定')
                ->keyLabel('鍵')
                ->valueLabel('值')
                ->nullable()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('名稱')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('類型')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::typeOptions()[$state] ?? $state),

                TextColumn::make('slug')
                    ->label('短連結')
                    ->state(fn (SurveyCollector $record): string => route('survey.collector.show', $record->slug))
                    ->copyable()
                    ->copyMessage('短連結已複製')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'paused' => 'warning',
                        'archived' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '啟用',
                        'paused' => '暫停',
                        'archived' => '封存',
                        default => $state,
                    }),

                TextColumn::make('responses_count')
                    ->counts('responses')
                    ->label('回應數'),

                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('類型')
                    ->options(self::typeOptions()),
                SelectFilter::make('status')
                    ->label('狀態')
                    ->options([
                        'active' => '啟用',
                        'paused' => '暫停',
                        'archived' => '封存',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('新增回收管道'),
            ])
            ->actions([
                EditAction::make()->label('編輯'),
                DeleteAction::make()->label('刪除'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        return [
            'web_link' => '網頁連結',
            'email_invite' => 'Email 邀請',
            'qr_code' => 'QR Code',
            'embed_iframe' => '嵌入 iframe',
        ];
    }
}
