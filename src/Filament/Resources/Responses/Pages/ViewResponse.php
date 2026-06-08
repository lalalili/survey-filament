<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Responses\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Lalalili\SurveyCore\Enums\SurveyResponseCompletionStatus;
use Lalalili\SurveyCore\Models\SurveyResponse;
use Lalalili\SurveyCore\Models\SurveyTag;
use Lalalili\SurveyFilament\Filament\Resources\Responses\ResponseResource;

/**
 * @property SurveyResponse $record
 */
class ViewResponse extends ViewRecord
{
    protected static string $resource = ResponseResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return ResponseResource::getEloquentQuery()
            ->with(['survey', 'recipient', 'answers.field', 'tags'])
            ->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit_notes_tags')
                ->label('編輯備註與標籤')
                ->schema([
                    Textarea::make('notes')
                        ->label('備註')
                        ->rows(4),
                    Select::make('tag_ids')
                        ->label('標籤')
                        ->multiple()
                        ->options(fn () => SurveyTag::query()
                            ->where('survey_id', $this->record->survey_id)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('名稱')
                                ->required()
                                ->maxLength(255),
                            ColorPicker::make('color')
                                ->label('顏色')
                                ->default('#6366f1')
                                ->required(),
                        ])
                        ->createOptionUsing(fn (array $data): int => SurveyTag::create([
                            'survey_id' => $this->record->survey_id,
                            'name'      => $data['name'],
                            'color'     => $data['color'] ?? '#6366f1',
                        ])->id),
                ])
                ->fillForm(fn (): array => [
                    'notes'   => $this->record->notes,
                    'tag_ids' => $this->record->tags->pluck('id')->all(),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['notes' => $data['notes'] ?? null]);
                    $this->record->tags()->sync($data['tag_ids'] ?? []);
                    $this->record->refresh()->load(['tags']);
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('survey.title')->label('問卷'),
            TextEntry::make('recipient.name')->label('收件人姓名')->placeholder('—'),
            TextEntry::make('recipient.email')->label('收件人 Email')->placeholder('—'),
            TextEntry::make('recipient.external_id')->label('外部 ID')->placeholder('—'),
            TextEntry::make('completion_status')
                ->label('完成狀態')
                ->formatStateUsing(fn ($state) => $state instanceof SurveyResponseCompletionStatus ? $state->label() : $state->value),
            TextEntry::make('submitted_at')->label('提交時間')->dateTime(),
            TextEntry::make('ip')->label('IP')->placeholder('—'),
            TextEntry::make('notes')->label('備註')->placeholder('—')->columnSpanFull(),
            TextEntry::make('tags')
                ->label('標籤')
                ->state(fn () => $this->record->tags->pluck('name')->implode('、') ?: '—'),
            TextEntry::make('user_agent')->label('User Agent')->columnSpanFull()->placeholder('—'),

            RepeatableEntry::make('answers')
                ->label('填答內容')
                ->schema([
                    TextEntry::make('field.label')->label('題目'),
                    TextEntry::make('field.type')
                        ->label('類型')
                        ->formatStateUsing(fn ($state) => $state?->label() ?? '—'),
                    TextEntry::make('field.is_hidden')
                        ->label('隱藏')
                        ->formatStateUsing(fn ($state) => $state ? '是' : '否'),
                    TextEntry::make('value')
                        ->label('答案')
                        ->state(fn ($record) => is_array($record->answer_json)
                            ? implode('、', $record->answer_json)
                            : ($record->answer_text ?? '—')),
                ])
                ->columnSpanFull(),
        ]);
    }
}
