<?php

declare(strict_types=1);

namespace PHPMax\Api\Account;

use PHPMax\Api\Binding;
use PHPMax\Domain\FolderList;
use PHPMax\Domain\FolderUpdate;
use PHPMax\Domain\Profile;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Files\Photo;
use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;
use PHPMax\Session\SessionInfo;

class AccountService
{
    /** @var App */
    private $app;
    /** @var Profile|null */
    private $profile;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->profile = null;
    }

    public function requestProfilePhotoUploadUrl(): string
    {
        $payload = new UploadPayload(['profile' => true]);
        $response = $this->app->invoke(Opcode::PHOTO_UPLOAD, $payload->toArray());
        $url = $response->payload[SelfPayloadKey::URL] ?? null;
        if ($url === null) {
            throw new PHPMaxException('Profile photo upload URL not found in response');
        }

        return (string) $url;
    }

    public function changeProfile(
        string $firstName,
        ?string $lastName = null,
        ?string $description = null,
        $photo = null,
        ?string $photoToken = null
    ): bool {
        if (is_string($photo) && $photoToken === null) {
            $photoToken = $photo;
            $photo = null;
        }
        if ($photo instanceof Photo) {
            $attach = $this->app->api()->uploads->uploadPhoto($photo, true);
            $photoToken = (string) $attach->photoToken;
        }
        $payload = new ChangeProfilePayload([
            'firstName' => $firstName,
            'lastName' => $lastName,
            'description' => $description,
            'photoToken' => $photoToken,
        ]);
        $response = $this->app->invoke(Opcode::PROFILE, $payload->toArray());
        $profile = $response->payload[SelfPayloadKey::PROFILE] ?? null;
        if (!is_array($profile)) {
            throw new PHPMaxException('Profile not found in response');
        }

        $this->profile = $this->app->setProfile(Profile::fromArray($profile));

        return true;
    }

    /**
     * @param list<int> $chatInclude
     * @param list<mixed>|null $filters
     */
    public function createFolder(string $title, array $chatInclude, ?array $filters = null): FolderUpdate
    {
        $payload = new CreateFolderPayload([
            'id' => $this->uuidV4(),
            'title' => $title,
            'include' => $chatInclude,
            'filters' => $filters !== null ? $filters : [],
        ]);
        $response = $this->app->invoke(Opcode::FOLDERS_UPDATE, $payload->toArray());

        return FolderUpdate::fromArray($this->requireResponsePayload($response->payload));
    }

    public function getFolders(int $folderSync = 0): FolderList
    {
        $payload = new GetFolderPayload(['folderSync' => $folderSync]);
        $response = $this->app->invoke(Opcode::FOLDERS_GET, $payload->toArray());

        return FolderList::fromArray($this->requireResponsePayload($response->payload));
    }

    /**
     * @param list<int>|null $chatInclude
     * @param list<mixed>|null $filters
     * @param list<mixed>|null $options
     */
    public function updateFolder(
        string $folderId,
        string $title,
        ?array $chatInclude = null,
        ?array $filters = null,
        ?array $options = null
    ): FolderUpdate {
        $payload = new UpdateFolderPayload([
            'id' => $folderId,
            'title' => $title,
            'include' => $chatInclude !== null ? $chatInclude : [],
            'filters' => $filters !== null ? $filters : [],
            'options' => $options !== null ? $options : [],
        ]);
        $response = $this->app->invoke(Opcode::FOLDERS_UPDATE, $payload->toArray());

        return FolderUpdate::fromArray($this->requireResponsePayload($response->payload));
    }

    public function deleteFolder(string $folderId): FolderUpdate
    {
        $payload = new DeleteFolderPayload(['folderIds' => [$folderId]]);
        $response = $this->app->invoke(Opcode::FOLDERS_DELETE, $payload->toArray());

        return FolderUpdate::fromArray($this->requireResponsePayload($response->payload));
    }

    public function closeAllSessions(): bool
    {
        $session = $this->app->session();
        if ($session === null || $session->token === null) {
            return false;
        }

        $response = $this->app->invoke(Opcode::SESSIONS_CLOSE, []);
        $token = $response->payload[SelfPayloadKey::TOKEN] ?? null;
        if ($token === null || $token === '') {
            return false;
        }

        $this->app->store()->updateToken($session->token, (string) $token);
        $this->app->setSession(new SessionInfo([
            'token' => (string) $token,
            'deviceId' => $session->deviceId,
            'phone' => $session->phone,
            'mtInstanceId' => $session->mtInstanceId,
            'sync' => $session->sync,
        ]));

        return true;
    }

    public function logout(): bool
    {
        $this->app->invoke(Opcode::LOGOUT, []);

        return true;
    }

    public function profile(): ?Profile
    {
        return $this->profile;
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * @param array<mixed>|null $payload
     * @return array<mixed>
     */
    private function requireResponsePayload(?array $payload): array
    {
        if ($payload === null || $payload === []) {
            throw new PHPMaxException('Missing payload in response');
        }

        return $payload;
    }
}
