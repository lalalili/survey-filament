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
                    ->state(fn ($record): string => self::formatRowDataState($record->data_json, $this->currentColumnLabelMap()))
                    ->action(
                        Action::make('viewRowData')
                            ->label('查看完整資料')
                            ->modalHeading(fn ($record) => '名單資料 #' . $record->id)
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('關閉')
                            ->modalContent(fn ($record): \Illuminate\Contracts\View\View => view(
                                /** @phpstan-ignore argument.type */
                                'survey-filament::modals.row-data',
                                [
                                    'data' => self::labelRowData($record->data_json, $this->currentColumnLabelMap()),
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

    /**
     * @param  array<string, string>  $labelMap
     */
    public static function formatRowDataState(mixed $state, array $labelMap = []): string
    {
        $data = self::normalizeRowData($state);

        if ($data === []) {
            return '—';
        }

        $priorityKeys = ['name', 'regono', 'mobile', 'rono', 'rbo_no', 'dlr', 'modelfamily', 'modelfamily_code'];
        $preview = [];

        foreach ($priorityKeys as $key) {
            if (isset($data[$key]) && $data[$key] !== '') {
                $preview[$key] = $data[$key];
                if (count($preview) >= 4) {
                    break;
                }
            }
        }

        if ($preview === []) {
            foreach ($data as $key => $value) {
                if ($value !== '') {
                    $preview[$key] = $value;
                    if (count($preview) >= 4) {
                        break;
                    }
                }
            }
        }

        $text = collect($preview)
            ->map(fn (mixed $value, string|int $key): string => self::labelForKey((string) $key, $labelMap) . ': ' . self::formatValue($value))
            ->implode(' / ');

        return count($data) > count($preview) ? $text . ' ...' : $text;
    }

    /**
     * @param  array<string, string>  $labelMap
     * @return array<string, mixed>
     */
    public static function labelRowData(mixed $state, array $labelMap = []): array
    {
        $data = self::normalizeRowData($state);
        $labeled = [];

        foreach ($data as $key => $value) {
            $labeled[self::modalLabelForKey((string) $key, $labelMap)] = $value;
        }

        return $labeled;
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, string>
     */
    public static function buildColumnLabelMap(array $columns): array
    {
        $labels = [];

        foreach ($columns as $column) {
            $key = $column['key'] ?? null;
            $label = $column['label'] ?? null;

            if (filled($key) && filled($label)) {
                $labels[(string) $key] = (string) $label;
            }
        }

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    private function currentColumnLabelMap(): array
    {
        return self::buildColumnLabelMap($this->getOwnerRecord()->columns_json ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeRowData(mixed $state): array
    {
        if (is_string($state)) {
            $decoded = json_decode($state, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($state) ? $state : [];
    }

    /**
     * @param  array<string, string>  $labelMap
     */
    private static function labelForKey(string $key, array $labelMap): string
    {
        return $labelMap[$key] ?? $key;
    }

    /**
     * @param  array<string, string>  $labelMap
     */
    private static function modalLabelForKey(string $key, array $labelMap): string
    {
        $label = self::labelForKey($key, $labelMap);

        return $label === $key ? $key : "{$label}({$key})";
    }

    private static function formatValue(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
    }
}
