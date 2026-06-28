<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Lalalili\SurveyCore\Actions\CreateBlankSurveyBuilderSurveyAction;
use Lalalili\SurveyCore\Actions\CreateSurveyFromBuilderTemplateAction;
use Lalalili\SurveyCore\Actions\ImportSurveyBuilderSchemaAction;
use Lalalili\SurveyCore\Exceptions\SurveyValidationException;
use Lalalili\SurveyCore\Support\SurveyBuilderTemplateRegistry;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

class ListSurveys extends ListRecords
{
    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_builder_json')
                ->label('匯入問卷 JSON')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => (bool) config('survey-filament.builder_json_actions_enabled', false))
                ->schema([
                    FileUpload::make('json_file')
                        ->label('Builder JSON')
                        ->acceptedFileTypes(['application/json', 'text/plain', 'application/octet-stream'])
                        ->disk('local')
                        ->directory('survey-imports')
                        ->required(),
                    TextInput::make('title')
                        ->label('覆寫問卷標題')
                        ->maxLength(255),
                    Toggle::make('publish_after_import')
                        ->label('匯入後直接發佈')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    $importSchema = app(ImportSurveyBuilderSchemaAction::class);
                    $path = $this->uploadedJsonPath($data['json_file'] ?? null);

                    if ($path === null || ! Storage::disk('local')->exists($path)) {
                        Notification::make()
                            ->danger()
                            ->title('找不到匯入檔案')
                            ->send();

                        return null;
                    }

                    try {
                        $json = Storage::disk('local')->get($path);

                        if (! is_string($json)) {
                            Notification::make()
                                ->danger()
                                ->title('讀取匯入檔案失敗')
                                ->send();

                            return null;
                        }

                        $survey = $importSchema->fromJson(
                            $json,
                            $data['title'] ?? null,
                            (bool) ($data['publish_after_import'] ?? false),
                        );
                    } catch (JsonException) {
                        Notification::make()
                            ->danger()
                            ->title('JSON 格式不正確')
                            ->send();

                        return null;
                    } catch (SurveyValidationException $exception) {
                        Notification::make()
                            ->danger()
                            ->title('問卷 JSON 驗證失敗')
                            ->body(collect($exception->getErrors())->flatten()->take(5)->implode("\n"))
                            ->send();

                        return null;
                    }

                    Notification::make()
                        ->success()
                        ->title('問卷已匯入')
                        ->body($survey->title)
                        ->send();

                    return redirect(SurveyResource::getUrl('builder', ['record' => $survey]));
                }),
            Action::make('create_from_template')
                ->label('從範本建立')
                ->icon('heroicon-o-rectangle-stack')
                ->schema([
                    Select::make('template')
                        ->label('選擇範本')
                        ->options($this->templateOptions())
                        ->required()
                        ->native(false)
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    abort_unless(SurveyResource::canCreate(), 403);

                    $slug = $data['template'] ?? null;

                    if (! is_string($slug) || $slug === '') {
                        Notification::make()
                            ->danger()
                            ->title('請選擇範本')
                            ->send();

                        return null;
                    }

                    $survey = app(CreateSurveyFromBuilderTemplateAction::class)->execute($slug);

                    Notification::make()
                        ->success()
                        ->title('已從範本建立問卷')
                        ->body($survey->title)
                        ->send();

                    return redirect(SurveyResource::getUrl('builder', ['record' => $survey]));
                }),
            Action::make('create')
                ->label('新增問卷')
                ->icon('heroicon-o-plus')
                ->action(function () {
                    abort_unless(SurveyResource::canCreate(), 403);

                    $survey = app(CreateBlankSurveyBuilderSurveyAction::class)->execute();

                    Notification::make()
                        ->success()
                        ->title('問卷已建立')
                        ->body('請在設計問卷頁完成題目與問卷設定。')
                        ->send();

                    return redirect(SurveyResource::getUrl('builder', ['record' => $survey]));
                }),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function templateOptions(): array
    {
        return collect(app(SurveyBuilderTemplateRegistry::class)->all())
            ->mapWithKeys(fn (array $template, string $slug): array => [
                $slug => $template['name'].'（'.$template['category'].'）',
            ])
            ->all();
    }

    private function uploadedJsonPath(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $first = reset($value);

            return is_string($first) ? $first : null;
        }

        return null;
    }
}
