<?php

namespace Lalalili\SurveyFilament\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Lalalili\SurveyCore\Models\GoogleDriveAccount;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Support\GoogleDriveClientFactory;
use Throwable;

/**
 * Plain web routes (not under a Filament panel context), so this controller
 * must avoid panel-bound APIs (Filament::getUrl / Notification). The caller
 * (a Filament row action) passes a validated `return` URL that we carry through
 * the encrypted OAuth `state` and redirect back to with a result flag.
 */
class GoogleDriveOAuthController extends Controller
{
    public function __construct(private readonly GoogleDriveClientFactory $clients) {}

    public function connect(Request $request, Survey $survey): RedirectResponse
    {
        Gate::authorize('update', $survey);

        $return = $this->safeReturnUrl($request, (string) $request->query('return', ''));

        if (! $this->clients->isConfigured()) {
            return redirect($this->withFlag($return, 'unconfigured'));
        }

        $state = Crypt::encryptString(json_encode([
            'survey_id' => $survey->id,
            'user_id' => Auth::id(),
            'return' => $return,
            'popup' => $request->boolean('popup'),
            'expires_at' => now()->addMinutes(15)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return redirect()->away($this->clients->authorizationUrl($state));
    }

    public function status(Survey $survey): JsonResponse
    {
        Gate::authorize('update', $survey);

        return response()->json([
            'connected' => $survey->google_drive_account_id !== null,
            'email' => $survey->googleDriveAccount?->email,
            'configured' => $this->clients->isConfigured(),
        ]);
    }

    public function disconnect(Survey $survey): JsonResponse
    {
        Gate::authorize('update', $survey);

        $survey->forceFill(['google_drive_account_id' => null, 'google_drive_folder_id' => null])->save();

        return response()->json(['connected' => false, 'email' => null]);
    }

    public function callback(Request $request): RedirectResponse|Response
    {
        $payload = $this->decodeState($request->query('state'));
        $return = is_string($payload['return'] ?? null) ? $payload['return'] : url('/');
        $popup = (bool) ($payload['popup'] ?? false);

        $survey = $payload !== null ? Survey::query()->find($payload['survey_id'] ?? null) : null;

        if (! $survey instanceof Survey) {
            return $this->finish($popup, $return, 'expired');
        }

        Gate::authorize('update', $survey);

        if ($request->query('error') !== null || $request->query('code') === null) {
            return $this->finish($popup, $return, 'cancelled');
        }

        try {
            $result = $this->clients->exchangeAuthCode((string) $request->query('code'));

            $account = GoogleDriveAccount::updateOrCreate(
                ['google_user_id' => $result['google_user_id']],
                [
                    'user_id' => $payload['user_id'] ?? Auth::id(),
                    'email' => $result['email'],
                    'name' => $result['name'],
                    'scopes' => $this->clients->baseClient()->getScopes(),
                ],
            );
            $this->clients->storeToken($account, $result['token']);

            $rootFolderId = $this->clients->ensureFolder($account, $this->rootFolderName());
            $folderId = $this->clients->ensureFolder($account, $this->folderName($survey), $survey->google_drive_folder_id, $rootFolderId);

            $survey->forceFill([
                'google_drive_account_id' => $account->id,
                'google_drive_folder_id' => $folderId,
            ])->save();
        } catch (Throwable $exception) {
            report($exception);

            return $this->finish($popup, $return, 'error');
        }

        return $this->finish($popup, $return, 'connected', $account->email);
    }

    /**
     * 彈窗模式回傳自關閉頁面（postMessage 通知 Builder）；否則導回 return URL。
     */
    private function finish(bool $popup, string $return, string $flag, ?string $email = null): RedirectResponse|Response
    {
        if (! $popup) {
            return redirect($this->withFlag($return, $flag));
        }

        $message = json_encode(['source' => 'survey-google-drive', 'status' => $flag, 'email' => $email], JSON_THROW_ON_ERROR);
        $origin = json_encode(url('/'), JSON_THROW_ON_ERROR);

        $html = <<<HTML
            <!DOCTYPE html><html lang="zh-Hant"><head><meta charset="utf-8"><title>Google Drive</title></head>
            <body style="font-family:system-ui;padding:24px;text-align:center;color:#374151">
            <p>處理完成，視窗即將關閉…</p>
            <script>
                try { if (window.opener) { window.opener.postMessage({$message}, {$origin}); } } catch (e) {}
                window.close();
            </script>
            </body></html>
            HTML;

        return response($html);
    }

    /**
     * @return array{survey_id?: int, user_id?: int, return?: string, popup?: bool, expires_at?: int}|null
     */
    private function decodeState(mixed $state): ?array
    {
        if (! is_string($state) || $state === '') {
            return null;
        }

        try {
            /** @var array{survey_id?: int, user_id?: int, return?: string, popup?: bool, expires_at?: int} $payload */
            $payload = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (($payload['expires_at'] ?? 0) < now()->timestamp) {
            return null;
        }

        return $payload;
    }

    /**
     * Only allow returning to a same-host (or relative) URL to avoid open redirects.
     */
    private function safeReturnUrl(Request $request, string $return): string
    {
        if ($return === '') {
            return url('/');
        }

        $host = parse_url($return, PHP_URL_HOST);

        if ($host === null || $host === $request->getHost()) {
            return $return;
        }

        return url('/');
    }

    private function withFlag(string $url, string $flag): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'google_drive='.$flag;
    }

    private function folderName(Survey $survey): string
    {
        return '問卷 #'.$survey->id.' - '.$survey->title;
    }

    private function rootFolderName(): string
    {
        return 'Survey File Upload';
    }
}
