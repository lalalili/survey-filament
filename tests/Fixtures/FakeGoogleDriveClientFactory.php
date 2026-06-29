<?php

namespace Lalalili\SurveyFilament\Tests\Fixtures;

use Google\Client;
use Lalalili\SurveyCore\Models\GoogleDriveAccount;
use Lalalili\SurveyCore\Support\GoogleDriveClientFactory;

/**
 * Fake factory: overrides only the methods that would call Google.
 */
class FakeGoogleDriveClientFactory extends GoogleDriveClientFactory
{
    /** @var list<array{id: string, name: string, parent: ?string, existing: ?string}> */
    public array $folders = [];

    public function isConfigured(): bool
    {
        return true;
    }

    public function baseClient(): Client
    {
        $client = new Client;
        $client->setScopes(['https://www.googleapis.com/auth/drive.file']);

        return $client;
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?fake=1&state='.urlencode($state);
    }

    /**
     * @return array{
     *     token: array{access_token: string, refresh_token: string, expires_in: int},
     *     google_user_id: string,
     *     email: string,
     *     name: string
     * }
     */
    public function exchangeAuthCode(string $code): array
    {
        return [
            'token' => ['access_token' => 'at-'.$code, 'refresh_token' => 'rt-'.$code, 'expires_in' => 3600],
            'google_user_id' => 'sub-123',
            'email' => 'creator@example.com',
            'name' => 'Creator',
        ];
    }

    public function ensureFolder(GoogleDriveAccount $account, string $name, ?string $existingFolderId = null, ?string $parentFolderId = null): string
    {
        foreach ($this->folders as $folder) {
            if ($folder['name'] === $name && $folder['parent'] === $parentFolderId) {
                return $folder['id'];
            }
        }

        $id = 'folder-'.(count($this->folders) + 1);
        $this->folders[] = ['id' => $id, 'name' => $name, 'parent' => $parentFolderId, 'existing' => $existingFolderId];

        return $id;
    }
}
