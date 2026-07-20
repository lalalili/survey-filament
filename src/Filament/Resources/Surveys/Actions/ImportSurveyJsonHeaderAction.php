<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use JsonException;
use Lalalili\SurveyCore\Actions\ImportSurveyBuilderSchemaAction;
use Lalalili\SurveyCore\Exceptions\SurveyValidationException;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

class ImportSurveyJsonHeaderAction
{
    public static function make(): Action
    {
        return Action::make('import_builder_json')
            ->label('匯入問卷 JSON')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn (): bool => (bool) config('survey-filament.builder_json_actions_enabled', false) && SurveyResource::canCreate())
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
                abort_unless(SurveyResource::canCreate(), 403);

                $importSchema = app(ImportSurveyBuilderSchemaAction::class);
                $path = self::uploadedJsonPath($data['json_file'] ?? null);

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
            });
    }

    private static function uploadedJsonPath(mixed $value): ?string
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
