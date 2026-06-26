<?php

namespace Lalalili\SurveyFilament\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;

class RuleTreeField extends Field
{
    protected string $view = 'survey-filament::forms.components.rule-tree-field';

    /**
     * @var Closure|array<int, array<string, mixed>>
     */
    protected Closure|array $availableFields = [];

    /**
     * @param  Closure|array<int, array<string, mixed>>  $fields
     */
    public function availableFields(Closure|array $fields): static
    {
        $this->availableFields = $fields;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableFields(): array
    {
        return $this->evaluate($this->availableFields);
    }

    public function getAvailableFieldsJson(): string
    {
        return json_encode($this->getAvailableFields(), JSON_UNESCAPED_UNICODE) ?: '[]';
    }
}
