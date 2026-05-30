<?php

namespace Lalalili\SurveyFilament\Support;

use Filament\Facades\Filament;

/**
 * 依目前 Filament panel 取得 label 覆寫值（config: survey-filament.panel_labels.{panel}.{key}）。
 * 未設定時回傳 null，呼叫端 fallback 至資源預設字串。
 *
 * 例：demo panel 將問卷「回應」相關字樣統一改稱「回覆」，其他 panel 維持「回應」。
 */
class PanelLabel
{
    public static function get(string $key): ?string
    {
        $panelId = Filament::getCurrentPanel()?->getId();

        if ($panelId === null) {
            return null;
        }

        $value = config("survey-filament.panel_labels.{$panelId}.{$key}");

        return is_string($value) && $value !== '' ? $value : null;
    }
}
