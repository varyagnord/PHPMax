<?php

declare(strict_types=1);

namespace PHPMax\Domain;

use PHPMax\Api\Users\UserService;
use PHPMax\Support\Model;
use RuntimeException;

class User extends Model
{
    /** @var int|null */
    public $id;
    /** @var int|null */
    public $accountStatus;
    /** @var int|null */
    public $registrationTime;
    /** @var string|null */
    public $country;
    /** @var string|null */
    public $baseRawUrl;
    /** @var string|null */
    public $baseUrl;
    /** @var list<Name> */
    public $names = [];
    /** @var array<int|string, mixed> */
    public $options = [];
    /** @var int|null */
    public $photoId;
    /** @var int|null */
    public $updateTime;
    /** @var mixed */
    public $phone;
    /** @var string|null */
    public $status;
    /** @var string|null */
    public $description;
    /** @var mixed */
    public $gender;
    /** @var string|null */
    public $link;
    /** @var mixed */
    public $webApp;
    /** @var array<int|string, mixed>|null */
    public $menuButton;
    /** @var UserService|null */
    private $actions;

    public function bind(UserService $actions): self
    {
        $this->actions = $actions;

        return $this;
    }

    public function addContact(): User
    {
        return $this->bound()->addContact($this->requireUserId());
    }

    public function removeContact(): bool
    {
        return $this->bound()->removeContact($this->requireUserId());
    }

    public function getChatId(int $userId): int
    {
        return $this->bound()->getChatId($userId, $this->requireUserId());
    }

    protected static function schema(): array
    {
        return [
            'id' => ['type' => 'int', 'required' => true],
            'accountStatus' => ['type' => 'int'],
            'registrationTime' => ['type' => 'int'],
            'country' => ['type' => 'string'],
            'baseRawUrl' => ['type' => 'string'],
            'baseUrl' => ['type' => 'string'],
            'names' => ['type' => 'list<' . Name::class . '>', 'default' => static function (): array {
                return [];
            }],
            'options' => ['type' => 'list<string>', 'default' => static function (): array {
                return [];
            }],
            'photoId' => ['type' => 'int'],
            'updateTime' => ['type' => 'int'],
            'phone' => ['type' => 'mixed'],
            'status' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'gender' => ['type' => 'mixed'],
            'link' => ['type' => 'string'],
            'webApp' => ['type' => 'mixed'],
            'menuButton' => ['type' => 'array'],
        ];
    }

    private function bound(): UserService
    {
        if ($this->actions === null) {
            throw new RuntimeException('User is not bound to a client.');
        }

        return $this->actions;
    }

    private function requireUserId(): int
    {
        if ($this->id === null) {
            throw new RuntimeException('User does not contain id.');
        }

        return $this->id;
    }
}
