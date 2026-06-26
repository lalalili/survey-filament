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
use Lalalili\AudienceCore\Support\AudienceFileReader;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;
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

    /**
     * @var array{columns: list<string>, rows: list<array<string, string>>, total_rows: int, displayed_rows: int}|null
     */
    public ?array $previewData = null;

    public ?string $previewedImportPath = null;

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

    public function preview(AudienceFileReader $reader): void
    {
        $this->previewData = null;
        $this->previewedImportPath = null;

        try {
            $upload = $this->readUploadedFile($reader);
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('無法預覽名單')->body($e->getMessage())->send();

            return;
        }

        $columns = $upload['parsed']['columns'];
        $previewRows = array_slice($upload['parsed']['rows'], 0, 5);

        $this->previewData = [
            'columns' => $columns,
            'rows' => array_map(
                fn (array $row): array => $this->formatPreviewRow($columns, $row),
                $previewRows,
            ),
            'total_rows' => count($upload['parsed']['rows']),
            'displayed_rows' => count($previewRows),
        ];
        $this->previewedImportPath = $upload['stored_path'];
    }

    public function import(AudienceFileReader $reader, ImportAudienceListAction $action): void
    {
        if ($this->previewData === null) {
            Notification::make()->danger()->title('請先預覽名單檔案。')->send();

            return;
        }

        try {
            $upload = $this->readUploadedFile($reader);
        } catch (RuntimeException $e) {
            Notification::make()->danger()->title('匯入失敗')->body($e->getMessage())->send();

            return;
        }

        if ($this->previewedImportPath !== $upload['stored_path']) {
            $this->previewData = null;
            $this->previewedImportPath = null;

            Notification::make()->danger()->title('檔案已變更，請重新預覽。')->send();

            return;
        }

        $data = $upload['data'];

        $audienceList = $action->execute(
            parsed: $upload['parsed'],
            name: filled($data['audience_list_id'] ?? null) ? null : (string) $data['name'],
            existingId: filled($data['audience_list_id'] ?? null) ? (int) $data['audience_list_id'] : null,
            createdBy: Auth::id() === null ? null : (int) Auth::id(),
        );

        Storage::disk('local')->delete($upload['stored_path']);

        Notification::make()
            ->success()
            ->title('名單匯入完成')
            ->body("已匯入 {$audienceList->rows_count} 筆資料。")
            ->send();

        $this->redirect(RecipientResource::getUrl('edit', ['record' => $audienceList]));
    }

    /**
     * @return array{
     *     data: array<string, mixed>,
     *     stored_path: string,
     *     parsed: array{columns: list<string>, rows: list<array<string, mixed>>}
     * }
     */
    private function readUploadedFile(AudienceFileReader $reader): array
    {
        $data = $this->form->getState();
        $storedFile = $data['import_file'] ?? null;
        $storedPath = is_array($storedFile) ? reset($storedFile) : $storedFile;

        if (! is_string($storedPath) || $storedPath === '') {
            throw new RuntimeException('請上傳名單檔案。');
        }

        $path = Storage::disk('local')->path($storedPath);

        return [
            'data' => $data,
            'stored_path' => $storedPath,
            'parsed' => $reader->read($path),
        ];
    }

    /**
     * @param  list<string>  $columns
     * @param  array<string, mixed>  $row
     * @return array<string, string>
     */
    private function formatPreviewRow(array $columns, array $row): array
    {
        $previewRow = [];

        foreach ($columns as $column) {
            $previewRow[$column] = $this->formatPreviewValue($row[$column] ?? null);
        }

        return $previewRow;
    }

    private function formatPreviewValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '';
    }
}
