<?php

namespace Lalalili\SurveyFilament\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Lalalili\EmailCampaign\Enums\EmailDeliveryStatus;
use Lalalili\EmailCampaign\Models\EmailDelivery;
use Lalalili\SurveyCore\Models\Survey;

class SurveyInvitationEmailStatsWidget extends BaseWidget
{
    public Model|Survey|null $record = null;

    protected function getStats(): array
    {
        if (! $this->record instanceof Survey) {
            return [];
        }

        if (! class_exists(EmailDelivery::class)) {
            return [
                Stat::make('邀請信統計', '—')
                    ->description('需安裝 email-campaign 套件'),
            ];
        }

        /** @var array{sent: int, opened: int} $counts */
        $counts = EmailDelivery::query()
            ->whereHas('campaign', fn ($q) => $q->where('survey_id', $this->record->id))
            ->where('status', EmailDeliveryStatus::Sent)
            ->selectRaw('COUNT(*) as sent, COUNT(CASE WHEN opened_at IS NOT NULL THEN 1 END) as opened')
            ->first()?->toArray() ?? ['sent' => 0, 'opened' => 0];

        $sent = (int) $counts['sent'];
        $opened = (int) $counts['opened'];
        $rate = $sent > 0 ? round($opened / $sent * 100, 1) : 0.0;

        return [
            Stat::make('邀請信已寄出', number_format($sent).' 封')
                ->icon('heroicon-o-envelope'),

            Stat::make('已開啟', number_format($opened).' 封')
                ->icon('heroicon-o-envelope-open'),

            Stat::make('開啟率', $rate.'%')
                ->icon('heroicon-o-chart-bar')
                ->color($rate >= 20 ? 'success' : ($rate >= 10 ? 'warning' : 'gray')),
        ];
    }
}
