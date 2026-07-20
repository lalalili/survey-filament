<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Enums\SurveyUniquenessMode;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

class SurveyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('標題')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('描述')
                ->rows(3)
                ->columnSpanFull(),

            Select::make('status')
                ->label('狀態')
                ->options(collect(SurveyStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]))
                ->required()
                ->default(SurveyStatus::Draft->value),

            TextInput::make('category')
                ->label('分類')
                ->maxLength(10)
                ->helperText('用於分組與篩選問卷的短代碼，例如 CSI、SSI。')
                ->datalist(fn (): array => SurveyResource::existingCategories()),

            TextInput::make('public_key')
                ->label('公開金鑰')
                ->disabled()
                ->dehydrated(false)
                ->visibleOn('edit'),

            Toggle::make('allow_anonymous')
                ->label('允許匿名填寫')
                ->default(false),

            Toggle::make('allow_multiple_submissions')
                ->label('允許多次提交')
                ->default(false),

            TextInput::make('max_responses')
                ->label('回收上限')
                ->numeric()
                ->minValue(1)
                ->placeholder('不限制'),

            DateTimePicker::make('starts_at')
                ->label('開始時間'),

            DateTimePicker::make('ends_at')
                ->label('結束時間'),

            Textarea::make('submit_success_message')
                ->label('提交成功訊息')
                ->rows(2)
                ->columnSpanFull(),

            Textarea::make('quota_message')
                ->label('額滿訊息')
                ->rows(2)
                ->columnSpanFull(),

            Select::make('uniqueness_mode')
                ->label('防重填模式')
                ->options(collect(SurveyUniquenessMode::cases())->mapWithKeys(fn ($mode) => [$mode->value => $mode->label()]))
                ->default(SurveyUniquenessMode::None->value)
                ->required(),

            TextInput::make('uniqueness_message')
                ->label('重複填寫提示')
                ->maxLength(255),

            Select::make('settings_json.personalization.audience_list_id')
                ->label('個性化名單')
                ->options(fn (): array => class_exists(AudienceList::class)
                    ? AudienceList::query()->orderBy('name')->pluck('name', 'id')->toArray()
                    : [])
                ->searchable()
                ->nullable()
                ->live()
                ->afterStateUpdated(fn (Set $set, mixed $state): mixed => $set(
                    'settings_json.personalization.required',
                    filled($state) ? true : null,
                ))
                ->helperText('選擇名單後，可用名單欄位自動填入個性化題目，且必須使用個性化網址填寫。'),

            Select::make('settings_json.personalization.name_column')
                ->label('姓名欄位')
                ->options(fn ($get): array => self::audienceColumnOptions($get('settings_json.personalization.audience_list_id')))
                ->searchable()
                ->nullable()
                ->helperText('同步名單時寫入收件人姓名，方便後台辨識、匯出與後續訊息個人化。'),

            Select::make('settings_json.personalization.email_column')
                ->label('Email 欄位')
                ->options(fn ($get): array => self::audienceColumnOptions($get('settings_json.personalization.audience_list_id')))
                ->searchable()
                ->nullable()
                ->helperText('同步為收件人 Email，Email 活動選擇此問卷時可沿用此欄位作為收件地址來源。'),

            Select::make('settings_json.personalization.external_id_column')
                ->label('外部識別碼欄位')
                ->options(fn ($get): array => self::audienceColumnOptions($get('settings_json.personalization.audience_list_id')))
                ->searchable()
                ->nullable()
                ->helperText('同步 CRM、DMS 或會員系統 ID，便於對帳、去重與跨系統追蹤；未指定時使用名單資料列 ID。'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private static function audienceColumnOptions(mixed $audienceListId): array
    {
        if (! $audienceListId) {
            return [];
        }

        if (! class_exists(AudienceList::class)) {
            return [];
        }

        $audienceList = AudienceList::query()->find((int) $audienceListId);

        return $audienceList?->columnOptions() ?? [];
    }
}
