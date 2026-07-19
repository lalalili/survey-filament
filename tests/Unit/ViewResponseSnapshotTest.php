<?php

use Lalalili\SurveyCore\Enums\SurveyFieldType;
use Lalalili\SurveyCore\Models\SurveyAnswer;
use Lalalili\SurveyCore\Models\SurveyField;
use Lalalili\SurveyFilament\Filament\Resources\Responses\Pages\ViewResponse;

it('renders an answer with its snapshot option label', function (): void {
    $field = new SurveyField;
    $field->forceFill([
        'field_key' => 'changed_key',
        'label' => 'Changed question',
        'type' => SurveyFieldType::SingleChoice,
        'options_json' => [
            ['label' => 'Changed option', 'value' => 'changed_value'],
        ],
    ]);

    $answer = new SurveyAnswer;
    $answer->forceFill([
        'answer_text' => 'original_value',
        'snapshot_field_key' => 'original_key',
        'snapshot_field_label' => 'Original question',
        'snapshot_field_type' => SurveyFieldType::SingleChoice->value,
        'snapshot_options_json' => [
            ['label' => 'Original option', 'value' => 'original_value'],
        ],
    ]);
    $answer->setRelation('field', $field);

    $page = (new ReflectionClass(ViewResponse::class))->newInstanceWithoutConstructor();
    $displayValue = (new ReflectionMethod(ViewResponse::class, 'answerDisplayValue'))->invoke($page, $answer);

    expect($answer->fieldLabel())->toBe('Original question')
        ->and($displayValue)->toBe('Original option');
});
