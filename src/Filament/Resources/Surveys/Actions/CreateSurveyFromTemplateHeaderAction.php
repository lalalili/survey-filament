<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Lalalili\SurveyCore\Actions\CreateSurveyFromBuilderTemplateAction;
use Lalalili\SurveyCore\Support\SurveyBuilderTemplateRegistry;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

class CreateSurveyFromTemplateHeaderAction
{
    public static function make(): Action
    {
        return Action::make('create_from_template')
            ->label('從範本建立')
            ->icon('heroicon-o-rectangle-stack')
            ->visible(fn (): bool => SurveyResource::canCreate())
            ->schema([
                Select::make('template')
                    ->label('選擇範本')
                    ->options(self::templateOptions())
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
            });
    }

    /**
     * @return array<string, string>
     */
    private static function templateOptions(): array
    {
        return collect(app(SurveyBuilderTemplateRegistry::class)->all())
            ->mapWithKeys(fn (array $template, string $slug): array => [
                $slug => $template['name'].'（'.$template['category'].'）',
            ])
            ->all();
    }
}
