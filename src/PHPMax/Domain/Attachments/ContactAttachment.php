<?php

declare(strict_types=1);

namespace PHPMax\Domain\Attachments;

class ContactAttachment extends BaseAttachment
{
    /** @var int|null */
    public $contactId;
    /** @var string|null */
    public $firstName;
    /** @var string|null */
    public $lastName;
    /** @var string|null */
    public $name;
    /** @var string|null */
    public $photoUrl;

    protected static function schema(): array
    {
        return parent::schema() + [
            'contactId' => ['type' => 'int'],
            'firstName' => ['type' => 'string'],
            'lastName' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'photoUrl' => ['type' => 'string'],
        ];
    }
}
