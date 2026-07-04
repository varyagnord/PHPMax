<?php

declare(strict_types=1);

namespace PHPMax\Api\Users;

use PHPMax\Domain\ContactInfo;
use PHPMax\Domain\Session;
use PHPMax\Domain\User;
use PHPMax\Exception\PHPMaxException;
use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;

class UserService
{
    /** @var App */
    private $app;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function getCachedUser(int $userId): ?User
    {
        return $this->app->cachedUser($userId);
    }

    public function cacheExternalUser(User $user): User
    {
        return $this->cacheUser($user);
    }

    /**
     * @param list<int> $userIds
     * @return list<User>
     */
    public function getUsers(array $userIds): array
    {
        $cached = [];
        $missing = [];
        foreach ($userIds as $userId) {
            $user = $this->getCachedUser($userId);
            if ($user !== null) {
                $cached[$userId] = $user;
            } else {
                $missing[] = $userId;
            }
        }

        if ($missing !== []) {
            foreach ($this->fetchUsers($missing) as $user) {
                if ($user->id !== null) {
                    $cached[$user->id] = $user;
                }
            }
        }

        $result = [];
        foreach ($userIds as $userId) {
            if (isset($cached[$userId])) {
                $result[] = $cached[$userId];
            }
        }

        return $result;
    }

    public function getUser(int $userId): ?User
    {
        $user = $this->getCachedUser($userId);
        if ($user !== null) {
            return $user;
        }

        $users = $this->fetchUsers([$userId]);

        return $users[0] ?? null;
    }

    /**
     * @param list<int> $userIds
     * @return list<User>
     */
    public function fetchUsers(array $userIds): array
    {
        $payload = new FetchContactsPayload(['contactIds' => $userIds]);
        $response = $this->app->invoke(Opcode::CONTACT_INFO, $payload->toArray());

        $users = [];
        foreach ($this->parsePayloadList($response->payload, UserPayloadKey::CONTACTS, 'contacts') as $item) {
            $users[] = $this->cacheUser(User::fromArray($item));
        }

        return $users;
    }

    public function searchByPhone(string $phone): User
    {
        $payload = new SearchByPhonePayload(['phone' => $phone]);
        $response = $this->app->invoke(Opcode::CONTACT_INFO_BY_PHONE, $payload->toArray());
        $contact = $response->payload[UserPayloadKey::CONTACT] ?? null;
        if (!is_array($contact)) {
            throw new PHPMaxException('Contact not found in response');
        }

        return $this->cacheUser(User::fromArray($contact));
    }

    /**
     * @return list<Session>
     */
    public function getSessions(): array
    {
        $response = $this->app->invoke(Opcode::SESSIONS_INFO, []);

        $sessions = [];
        foreach ($this->parsePayloadList($response->payload, UserPayloadKey::SESSIONS, 'sessions') as $item) {
            $sessions[] = Session::fromArray($item);
        }

        return $sessions;
    }

    public function addContact(int $contactId): User
    {
        $payload = new ContactActionPayload([
            'contactId' => $contactId,
            'action' => ContactAction::ADD,
        ]);
        $response = $this->app->invoke(Opcode::CONTACT_UPDATE, $payload->toArray());
        $responsePayload = $this->requireResponseDict($response->payload);
        $contact = $responsePayload[UserPayloadKey::CONTACT] ?? null;
        if (!is_array($contact)) {
            throw new PHPMaxException('Contact not found in response');
        }

        return $this->cacheUser(User::fromArray($contact));
    }

    public function removeContact(int $contactId): bool
    {
        $payload = new ContactActionPayload([
            'contactId' => $contactId,
            'action' => ContactAction::REMOVE,
        ]);
        $response = $this->app->invoke(Opcode::CONTACT_UPDATE, $payload->toArray());
        $this->requireResponseDict($response->payload);
        $this->app->removeCachedUser($contactId);

        return true;
    }

    /**
     * @param list<ContactInfo> $contacts
     * @return list<User>
     */
    public function importContacts(array $contacts): array
    {
        $payload = ImportContactsPayload::fromContacts($contacts);
        $response = $this->app->invoke(Opcode::SYNC, $payload->toArray());

        $users = [];
        foreach ($this->parsePayloadList($response->payload, UserPayloadKey::CONTACTS, 'contacts') as $item) {
            $users[] = $this->cacheUser(User::fromArray($item));
        }

        return $users;
    }

    public function getChatId(int $firstUserId, int $secondUserId): int
    {
        return $firstUserId ^ $secondUserId;
    }

    private function cacheUser(User $user): User
    {
        return $this->app->cacheUser($user);
    }

    /**
     * @param array<mixed>|null $payload
     * @return list<array<mixed>>
     */
    private function parsePayloadList(?array $payload, string $key, string $label): array
    {
        $items = $payload[$key] ?? null;
        if ($items === null || $items === []) {
            return [];
        }
        if (!is_array($items) || !$this->isList($items)) {
            throw new PHPMaxException('Invalid ' . $label . ' list in response');
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new PHPMaxException('Invalid ' . $label . ' item in response');
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param array<mixed>|null $payload
     * @return array<mixed>
     */
    private function requireResponseDict(?array $payload): array
    {
        if ($payload === null || ($payload !== [] && $this->isList($payload))) {
            throw new PHPMaxException('Invalid response payload');
        }

        return $payload;
    }

    /**
     * @param array<mixed> $payload
     */
    private function isList(array $payload): bool
    {
        $expected = 0;
        foreach (array_keys($payload) as $key) {
            if ($key !== $expected) {
                return false;
            }
            $expected++;
        }

        return true;
    }
}
