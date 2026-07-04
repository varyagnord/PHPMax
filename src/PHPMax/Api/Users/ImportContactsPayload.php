<?php

declare(strict_types=1);

namespace PHPMax\Api\Users;

use PHPMax\Domain\ContactInfo;
use PHPMax\Support\Model;

class ImportContactsPayload extends Model
{
    /** @var array<string, ContactPayload> */
    public $contactList = [];

    protected static function schema(): array
    {
        return [
            'contactList' => ['type' => 'mixed', 'default' => static function (): array {
                return [];
            }],
        ];
    }

    /**
     * @param iterable<ContactInfo> $contacts
     */
    public static function fromContacts(iterable $contacts): self
    {
        $items = [];
        foreach ($contacts as $contact) {
            $items[(string) $contact->phone] = new ContactPayload(['firstName' => $contact->firstName]);
        }

        return new self(['contactList' => $items]);
    }
}
