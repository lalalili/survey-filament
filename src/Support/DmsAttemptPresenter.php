<?php

namespace Lalalili\SurveyFilament\Support;

use Illuminate\Support\Str;
use Lalalili\SurveyCore\Enums\DmsExecutionMode;
use Lalalili\SurveyCore\Enums\SurveyTriggerActionAttemptStatus;
use Lalalili\SurveyCore\Models\SurveyTriggerActionAttempt;

final class DmsAttemptPresenter
{
    public static function statusLabel(SurveyTriggerActionAttemptStatus $status): string
    {
        return match ($status) {
            SurveyTriggerActionAttemptStatus::PendingReview => '待判讀',
            SurveyTriggerActionAttemptStatus::Skipped => '已略過',
            SurveyTriggerActionAttemptStatus::ConfigurationError => '設定錯誤',
            SurveyTriggerActionAttemptStatus::Success => '成功',
            SurveyTriggerActionAttemptStatus::BusinessError => '業務錯誤',
            SurveyTriggerActionAttemptStatus::SoapFault => 'SOAP 錯誤',
            SurveyTriggerActionAttemptStatus::HttpError => 'HTTP 錯誤',
            SurveyTriggerActionAttemptStatus::ConnectionError => '連線失敗',
        };
    }

    public static function statusColor(SurveyTriggerActionAttemptStatus $status): string
    {
        return match ($status) {
            SurveyTriggerActionAttemptStatus::Success => 'success',
            SurveyTriggerActionAttemptStatus::PendingReview => 'warning',
            SurveyTriggerActionAttemptStatus::Skipped => 'gray',
            default => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return collect(SurveyTriggerActionAttemptStatus::cases())
            ->mapWithKeys(fn (SurveyTriggerActionAttemptStatus $status): array => [
                $status->value => self::statusLabel($status),
            ])
            ->all();
    }

    public static function modeLabel(?string $mode): string
    {
        return match ($mode) {
            DmsExecutionMode::ManualQa->value => 'QA 手動測試',
            DmsExecutionMode::Automatic->value => '自動觸發',
            default => (string) $mode,
        };
    }

    public static function debugInformation(SurveyTriggerActionAttempt $attempt): string
    {
        return collect([
            'status' => $attempt->status->value,
            'ticket_no' => $attempt->ticket_no,
            'profile' => $attempt->profile,
            'endpoint' => $attempt->endpoint,
            'sent_at' => $attempt->sent_at?->toIso8601String(),
            'http_status' => $attempt->response_http_status,
            'duration_ms' => $attempt->duration_ms,
            'request_parameters' => self::redact($attempt->request_parameters),
            'request_soap' => self::redactXml($attempt->request_body),
            'response_headers' => self::redact($attempt->response_headers),
            'response_body' => self::redactXml($attempt->response_body),
            'parsed_response' => self::redact($attempt->parsed_response),
            'error' => self::redactString($attempt->error),
        ])->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<array-key, mixed>|null  $value
     */
    public static function prettyJson(?array $value): string
    {
        if ($value === null) {
            return '—';
        }

        return json_encode(
            self::redact($value),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '—';
    }

    public static function redactXml(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return self::redactString($value);
    }

    private static function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::isSensitiveKey($key)) {
            return '[REDACTED]';
        }

        if (! is_array($value)) {
            return is_string($value) ? self::redactString($value) : $value;
        }

        $redacted = [];

        foreach ($value as $itemKey => $itemValue) {
            $redacted[$itemKey] = self::redact($itemValue, (string) $itemKey);
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool
    {
        return Str::of($key)
            ->lower()
            ->replace(['-', '_'], '')
            ->is(['skey', 'key', 'authorization', 'cookie', 'setcookie', 'apikey', 'token']);
    }

    private static function redactString(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return preg_replace(
            [
                '/(<(?:\\w+:)?sKey\\b[^>]*>).*?(<\\/(?:\\w+:)?sKey>)/is',
                '/(["\']sKey["\']\\s*:\\s*["\']).*?(["\'])/is',
                '/(\\bsKey\\s*=\\s*)[^&\\s]+/i',
                '/(\\bsKey\\s*:\\s*)[^,;\\s]+/i',
            ],
            [
                '$1[REDACTED]$2',
                '$1[REDACTED]$2',
                '$1[REDACTED]',
                '$1[REDACTED]',
            ],
            $value,
        ) ?? $value;
    }
}
