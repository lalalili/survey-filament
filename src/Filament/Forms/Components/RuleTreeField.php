<?php

namespace Lalalili\SurveyFilament\Filament\Forms\Components;

use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Livewire\Attributes\Renderless;

class RuleTreeField extends Field
{
    protected string $view = 'survey-filament::forms.components.rule-tree-field';

    /**
     * @var Closure|array<int, array<string, mixed>>
     */
    protected Closure|array $availableFields = [];

    protected ?Closure $previewUsing = null;

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

    public function previewUsing(Closure $callback): static
    {
        $this->previewUsing = $callback;

        return $this;
    }

    public function hasPreview(): bool
    {
        return $this->previewUsing instanceof Closure;
    }

    /**
     * @param  array<string, mixed>  $ruleTree
     * @param  array<int, mixed>  $nodePath
     * @return array{count: int}
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function previewNode(array $ruleTree, array $nodePath): array
    {
        if (! $this->previewUsing instanceof Closure) {
            throw new \InvalidArgumentException('無法預覽目前的篩選條件。');
        }

        /** @var array{count: int} $result */
        $result = $this->evaluate($this->previewUsing, [
            'ruleTree' => $ruleTree,
            'nodePath' => array_values(array_map('intval', $nodePath)),
        ]);

        return $result;
    }
}
