<?php

declare(strict_types=1);

use PHPMax\Domain\AttachmentType;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\Lz4BlockCompression;
use PHPMax\Protocol\Tcp\MsgpackPayloadCodec;
use PHPMax\Protocol\Tcp\TcpPacketFramer;
use PHPMax\Protocol\Tcp\TcpPayloadDecoder;
use PHPMax\Protocol\Tcp\TcpProtocol;

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $framer = new TcpPacketFramer();
    $packet = $framer->pack(10, Command::RESPONSE, 0x0100, Opcode::PING, 2, 'abc');

    $assertSame(10, TcpPacketFramer::HEADER_SIZE, 'TCP header size must match PyMax');
    $assertSame(
        hex2bin('0a010100000102000003'),
        substr($packet, 0, TcpPacketFramer::HEADER_SIZE),
        'TCP header layout must match PyMax'
    );
    $assertSame(3, $framer->unpackHeader(substr($packet, 0, TcpPacketFramer::HEADER_SIZE)));
    $unpacked = $framer->unpack($packet);
    $assert($unpacked !== null, 'Framer must unpack complete packet');
    $assertSame(Command::RESPONSE, $unpacked->header->cmd);
    $assertSame(2, $unpacked->header->flags);
    $assertSame('abc', $unpacked->payloadBytes);

    $protocol = new TcpProtocol();
    $frame = new OutboundFrame(
        TcpProtocol::VERSION,
        Opcode::CHAT_HISTORY,
        3,
        ['chatId' => 100, 'itemType' => 'REGULAR'],
        Command::REQUEST
    );
    $decoded = $protocol->decode($protocol->encode($frame));
    $assert($decoded instanceof InboundFrame);
    $assertSame(Opcode::CHAT_HISTORY, $decoded->opcode);
    $assertSame(Command::REQUEST, $decoded->cmd);
    $assertSame(3, $decoded->seq);
    $assertSame(['chatId' => 100, 'itemType' => 'REGULAR'], $decoded->payload);

    $codec = new MsgpackPayloadCodec();
    $payload = [1 => ['name' => 'DELAYED'], 'list' => ['REGULAR']];
    $normalized = (new TcpPayloadDecoder($codec))->decode($codec->encode($payload));
    $assertSame(['1' => ['name' => 'DELAYED'], 'list' => ['REGULAR']], $normalized);

    $largePositive = 1783069696612;
    $largeNegative = -5000000000;
    $largeDecoded = $codec->decode($codec->encode([
        'expiresAt' => $largePositive,
        'offset' => $largeNegative,
    ]));
    $assertSame($largePositive, $largeDecoded['expiresAt'], 'MessagePack fallback must preserve uint64 timestamps');
    $assertSame($largeNegative, $largeDecoded['offset'], 'MessagePack fallback must preserve int64 values');

    $compressed = hex2bin(
        'f40a84a6707265666978a27878a464617461b0664a73436c4b437508008f' .
        'a47461696cd92a79010016dfa6726570656174d9684142434404004c5044' .
        '41424344'
    );
    $decodedLz4 = (new TcpPayloadDecoder($codec, new Lz4BlockCompression()))->decode($compressed, 4);
    $assertSame('xx', $decodedLz4['prefix']);
    $assertSame(str_repeat('ABCD', 26), $decodedLz4['repeat']);

    $assertThrows(\PHPMax\Exception\ProtocolException::class, static function (): void {
        (new Lz4BlockCompression())->decompress(chr(0x01) . chr(0x00) . chr(0x00));
    }, 'Invalid LZ4 zero offset must be rejected');

    $attachmentPayload = ['_type' => AttachmentType::PHOTO, 'photoId' => 'p1', 'token' => 't1'];
    $encodedAttachment = $codec->encode(['attaches' => [$attachmentPayload]]);
    $assertSame(['attaches' => [$attachmentPayload]], (new TcpPayloadDecoder($codec))->decode($encodedAttachment));
};
