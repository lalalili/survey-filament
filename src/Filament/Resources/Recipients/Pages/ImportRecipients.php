<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Recipients\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Lalalili\AudienceCore\Actions\ImportAudienceListAction;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;
use Lalalili\SurveyFilament\Support\AudienceFileReader;
use RuntimeException;

/**
 * @property Schema $form
 */
class ImportRecipients extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $resource = RecipientResource::class;

    protected string $view = 'survey-filament::recipient-import';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'audience_list_id' => request()->integer('audience_list_id') ?: null,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Hidden::make('audience_list_id'),

                TextInput::make('name')
                    ->label('名單名稱')
                    ->required()
                    ->maxLength(255)
                    ->visible(fn ($get): bool => blank($get('audience_list_id'))),

                FileUpload::make('import_file')
                    ->label('名單檔案')
                    ->acceptedFileTypes([
                        'text/csv',
                        'text/plain',
                        'application/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ])
                    ->disk('local')
                    ->directory('audience-list-imports')
                    ->required()
                    ->helperText('支援 CSV、XLSX、XLS。第一列會視為欄位名稱。'),
            ]);
    }

    public function import(AudienceFileReader $reader, ImportAudienceListAction $action): void
    {
        $data = $this->form->getState();
        $storedFile = $data['import_file'] ?? null;
        $storedPath = is_array($storedFile) ? reset($storedFile) : $storedFile;

        if (! is_string($storedPath) || $storedPath === '') {
            Notification::make()->danger()->title('請上傳名單檔案。')->send();

            return;
        }

        $path = Storage::disk('local')->path($storedPath);

        try {
            $parsed = $reader->read($path);
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('匯入失敗')->body($e->getMessage())->send();

            return;
        }

        $audienceList = $action->execute(
            parsed: $parsed,
            name: filled($data['audience_list_id'] ?? null) ? null : (string) $data['name'],
            existingId: filled($data['audience_list_id'] ?? null) ? (int) $data['audience_list_id'] : null,
            createdBy: Auth::id(),
        );

        Storage::disk('local')->delete($storedPath);

        Notification::make()
            ->success()
            ->title('名單匯入完成')
            ->body("已匯入 {$audienceList->rows_count} 筆資料。")
            ->send();

        $this->redirect(RecipientResource::getUrl('edit', ['record' => $audienceList]));
    }
}
