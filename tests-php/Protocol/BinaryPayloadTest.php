<?php

declare(strict_types=1);

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $payload = \PHPMax\Api\Auth\SyncPayload::fromSyncState(
        \PHPMax\Api\Session\MobileUserAgentPayload::defaultAndroid(),
        'token',
        new \PHPMax\Domain\SyncState()
    )->toArray();

    $assert(
        $payload['exp']['chatsCountGroups'] instanceof \PHPMax\Protocol\Tcp\BinaryString,
        'SyncPayload exp.chatsCountGroups must stay a MessagePack binary value.'
    );

    $encoded = (new \PHPMax\Protocol\Tcp\MsgpackPayloadCodec())->encode($payload);
    $assert(
        strpos($encoded, "\xC4\x02\x0A\x32") !== false,
        'MessagePack LOGIN payload must encode exp.chatsCountGroups as bin8 bytes.'
    );
};
