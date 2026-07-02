<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\CreateSurvey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\EditSurvey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\EditSurveyBuilder;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\ListSurveys;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\SurveyAnalytics;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\ViewSurvey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\CollectorsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\FieldsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\RecipientsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\ResponsesRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\TagsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Schemas\SurveyForm;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Tables\SurveysTable;
use Lalalili\SurveyFilament\Support\SurveyQueryScopes;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    protected static ?string $navigationLabel = '問卷管理';

    protected static ?string $modelLabel = '問卷';

    protected static ?string $pluralModelLabel = '問卷';

    public static function getNavigationGroup(): ?string
    {
        return config('survey-filament.navigation_group', '活動自動化');
    }

    public static function getNavigationSort(): ?int
    {
        return 30;
    }

    public static function form(Schema $schema): Schema
    {
        return SurveyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SurveysTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return SurveyQueryScopes::surveys(parent::getEloquentQuery());
    }

    public static function isSurveyTableColumnHidden(string $column): bool
    {
        return in_array($column, config('survey-filament.survey_table_hidden_columns', []), true);
    }

    public static function builderJsonActionsEnabled(): bool
    {
        return (bool) config('survey-filament.builder_json_actions_enabled', false);
    }

    /**
     * Distinct, non-empty survey categories for the form datalist and table filter.
     *
     * @return array<string, string>
     */
    public static function existingCategories(): array
    {
        return Survey::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category', 'category')
            ->all();
    }

    public static function getRelations(): array
    {
        return [
            FieldsRelationManager::class,
            CollectorsRelationManager::class,
            RecipientsRelationManager::class,
            ResponsesRelationManager::class,
            TagsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurveys::route('/'),
            'create' => CreateSurvey::route('/create'),
            'edit' => EditSurvey::route('/{record}/edit'),
            'builder' => EditSurveyBuilder::route('/{record}/builder'),
            'analytics' => SurveyAnalytics::route('/{record}/analytics'),
            'view' => ViewSurvey::route('/{record}'),
        ];
    }
}
