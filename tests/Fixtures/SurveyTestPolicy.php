<?php

namespace Lalalili\SurveyFilament\Tests\Fixtures;

use Lalalili\SurveyCore\Models\Survey;

/**
 * 授權測試用 Policy：Filament v5 預設 shouldCheckPolicyExistence，
 * 只認 Policy 方法而非 Gate::define 的 ability，故以靜態旗標控制放行結果。
 */
class SurveyTestPolicy
{
    public static bool $allowView = true;

    public static bool $allowCreate = true;

    public static bool $allowUpdate = true;

    public static function reset(): void
    {
        static::$allowView = true;
        static::$allowCreate = true;
        static::$allowUpdate = true;
    }

    public function viewAny(User $user): bool
    {
        return static::$allowView;
    }

    public function view(User $user, Survey $survey): bool
    {
        return static::$allowView;
    }

    public function create(User $user): bool
    {
        return static::$allowCreate;
    }

    public function update(User $user, Survey $survey): bool
    {
        return static::$allowUpdate;
    }
}
