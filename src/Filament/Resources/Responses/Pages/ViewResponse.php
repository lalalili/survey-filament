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
use Lalalili\SurveyCore\Models\SurveyAnswer;
use Lalalili\SurveyCore\Models\SurveyResponse;
use Lalalili\SurveyCore\Models\SurveyTag;
use Throwable;
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
                            'name' => $data['name'],
                            'color' => $data['color'] ?? '#6366f1',
                        ])->id),
                ])
                ->fillForm(fn (): array => [
                    'notes' => $this->record->notes,
                    'tag_ids' => $this->record->tags->pluck('id')->all(),
                ])
                ->action(function (array $data): void {
                    $this->record->update(['notes' => $data['notes'] ?? null]);
                    $this->record->tags()->sync($data['tag_ids'] ?? []);
                    $this->record->refresh()->load(['survey', 'recipient', 'answers.field', 'tags']);
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('survey.title')->label('問卷'),
            TextEntry::make('response_number')->label('填答編號')->placeholder('—')->copyable(),
            TextEntry::make('recipient.name')->label('收件人姓名')->placeholder('—'),
            TextEntry::make('recipient.email')->label('收件人 Email')->placeholder('—'),
            TextEntry::make('submitted_at')->label('提交時間')->dateTime(),

            RepeatableEntry::make('answers')
                ->label('填答內容')
                ->schema([
                    TextEntry::make('field.label')->label('題目'),
                    TextEntry::make('value')
                        ->label('答案')
                        ->state(fn (SurveyAnswer $record) => $this->isFileUploadAnswer($record)
                            ? (data_get($record->answer_json, 'filename') ?: '檔案')
                            : (is_array($record->answer_json) ? implode('、', $record->answer_json) : ($record->answer_text ?? '—')))
                        ->url(fn (SurveyAnswer $record): ?string => $this->isFileUploadAnswer($record) ? $this->fileUrl($record) : null)
                        ->openUrlInNewTab(),
                ])
                // 電腦版兩題一行（手機仍單欄）。
                ->grid(['default' => 1, 'md' => 2])
                ->columnSpanFull(),
        ]);
    }

    private function isFileUploadAnswer(SurveyAnswer $answer): bool
    {
        return $answer->field->type->value === 'file_upload';
    }

    /**
     * 檔案上傳答案的連結：優先 Google Drive 連結，否則本地後援。
     */
    private function fileUrl(SurveyAnswer $answer): ?string
    {
        $fieldKey = $answer->field->field_key;

        $media = $this->record->getMedia('survey_files')
            ->first(fn ($item): bool => $item->getCustomProperty('survey_field_key') === $fieldKey);

        if ($media === null) {
            return null;
        }

        $driveLink = $media->getCustomProperty('google_drive_link');

        if (is_string($driveLink) && $driveLink !== '') {
            return $driveLink;
        }

        try {
            return $media->getUrl();
        } catch (Throwable) {
            return null;
        }
    }
}
