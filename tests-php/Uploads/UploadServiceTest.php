<?php

declare(strict_types=1);

use PHPMax\Api\Uploads\HttpUploaderInterface;
use PHPMax\Api\Uploads\HttpUploadResponse;
use PHPMax\Api\Uploads\StreamBody;
use PHPMax\Api\Uploads\AttachFilePayload;
use PHPMax\Api\Uploads\AttachPhotoPayload;
use PHPMax\Api\Uploads\VideoAttachPayload;
use PHPMax\Client;
use PHPMax\Config\ClientOptions;
use PHPMax\Exception\UploadException;
use PHPMax\Files\File;
use PHPMax\Files\Photo;
use PHPMax\Files\Video;
use PHPMax\Protocol\Command;
use PHPMax\Protocol\InboundFrame;
use PHPMax\Protocol\Opcode;
use PHPMax\Protocol\OutboundFrame;
use PHPMax\Protocol\Tcp\TcpProtocol;
use PHPMax\Runtime\App;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Transport\TransportInterface;

final class UploadServiceTestTransport implements TransportInterface
{
    /** @var list<string> */
    private $chunks;
    /** @var list<string> */
    public $sent = [];
    /** @var bool */
    private $connected = false;

    /**
     * @param list<string> $chunks
     */
    public function __construct(array $chunks)
    {
        $this->chunks = $chunks;
    }

    public function connect(): void
    {
        $this->connected = true;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    public function send(string $data): void
    {
        $this->sent[] = $data;
    }

    public function recv(int $length, float $timeout): string
    {
        $chunk = array_shift($this->chunks);
        if ($chunk === null) {
            throw new RuntimeException('No fake upload chunks left');
        }
        if (strlen($chunk) !== $length) {
            throw new RuntimeException('Expected chunk length ' . $length . ', got ' . strlen($chunk));
        }

        return $chunk;
    }

    public function connected(): bool
    {
        return $this->connected;
    }
}

final class UploadServiceFakeUploader implements HttpUploaderInterface
{
    /** @var list<array<string, mixed>> */
    public $multipart = [];
    /** @var list<array<string, mixed>> */
    public $streams = [];
    /** @var callable|null */
    public $onStreamUpload;
    /** @var HttpUploadResponse|null */
    public $multipartResponse;
    /** @var HttpUploadResponse|null */
    public $streamResponse;

    public function uploadMultipart(
        string $url,
        string $fieldName,
        string $contents,
        string $filename,
        string $contentType
    ): HttpUploadResponse {
        $this->multipart[] = [
            'url' => $url,
            'fieldName' => $fieldName,
            'contents' => $contents,
            'filename' => $filename,
            'contentType' => $contentType,
        ];

        if ($this->multipartResponse !== null) {
            return $this->multipartResponse;
        }

        return new HttpUploadResponse(200, json_encode([
            'photos' => [
                'photo-1' => ['token' => 'photo-token'],
                'profile-1' => ['token' => 'profile-token'],
            ],
        ]));
    }

    public function uploadStream(
        string $url,
        array $headers,
        iterable $chunks,
        int $contentLength
    ): HttpUploadResponse {
        $this->streams[] = [
            'url' => $url,
            'headers' => $headers,
            'chunks' => is_array($chunks) ? $chunks : iterator_to_array($chunks),
            'contentLength' => $contentLength,
        ];
        if ($this->onStreamUpload !== null) {
            call_user_func($this->onStreamUpload, $url, $headers, $contentLength);
        }
        if ($this->streamResponse !== null) {
            return $this->streamResponse;
        }

        return new HttpUploadResponse(200, '');
    }
}

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $body = new StreamBody(['abc', '', 'defg'], 7);
    $assertSame('ab', $body->read(2));
    $assertSame('cde', $body->read(3));
    $assertSame('fg', $body->read(99));
    $assertSame('', $body->read(1));
    $assertSame(7, $body->bytesRead());
    $body->assertComplete();

    $shortBody = new StreamBody(['abc'], 4);
    $assertSame('abc', $shortBody->read(10));
    $assertThrows(UploadException::class, static function () use ($shortBody): void {
        $shortBody->assertComplete();
    });
    $assertThrows(UploadException::class, static function (): void {
        (new StreamBody(['abcd'], 3))->read(4);
    });
    $assertThrows(UploadException::class, static function (): void {
        (new StreamBody([123], 3))->read(3);
    });

    $assertThrows(UploadException::class, static function (): void {
        Photo::fromRaw('bytes', 'image.txt')->validatePhoto();
    });
    $assertThrows(UploadException::class, static function (): void {
        new File('raw-a', null, 'https://example.test/a.txt', 'a.txt');
    });

    $rawFile = File::fromRaw('abcdef', 'report.pdf');
    $assertSame(6, $rawFile->size());
    $assertSame(['ab', 'cd', 'ef'], iterator_to_array($rawFile->iterChunks(2)));
    $emptyRawFile = File::fromRaw('', 'empty.pdf');
    $assertThrows(UploadException::class, static function () use ($emptyRawFile): void {
        $emptyRawFile->read();
    }, 'Empty raw file read must fail like PyMax falsey raw handling');
    $assertThrows(UploadException::class, static function () use ($emptyRawFile): void {
        $emptyRawFile->size();
    }, 'Empty raw file size must fail like PyMax falsey raw handling');
    $assertSame([], iterator_to_array($emptyRawFile->iterChunks(2)), 'Empty raw chunk iterator stays empty');

    $tmpPath = sys_get_temp_dir() . '/phpmax-upload-fixture-' . getmypid() . '-' . mt_rand() . '.pdf';
    file_put_contents($tmpPath, 'path-bytes');
    try {
        $pathFile = File::fromPath($tmpPath);
        $assertSame(basename($tmpPath), $pathFile->name());
        $assertSame(10, $pathFile->size());
        $assertSame('path-bytes', $pathFile->read());
        $assertSame(['path', '-byt', 'es'], iterator_to_array($pathFile->iterChunks(4)));
    } finally {
        if (is_file($tmpPath)) {
            unlink($tmpPath);
        }
    }

    $pathVideo = Video::fromPath('/tmp/phpmax-clip.mp4');
    $assertSame('phpmax-clip.mp4', $pathVideo->name());

    $urlFile = File::fromUrl('https://cdn.example.test/files/report.pdf');
    $assertSame('report.pdf', $urlFile->name());

    $assertSame(['jpg', 'image/jpg'], Photo::fromRaw('photo-bytes', 'avatar.jpg')->validatePhoto());
    $assertSame(['jpg', 'image/jpg'], Photo::fromPath('/tmp/phpmax-avatar.jpg')->validatePhoto());
    $assertSame(['jpg', 'image/jpeg'], Photo::fromUrl('https://cdn.example.test/img/avatar.jpg?token=1')->validatePhoto());
    $assertSame(['webp', 'image/webp'], Photo::fromUrl('https://cdn.example.test/img/avatar.webp?token=1')->validatePhoto());
    $assertThrows(UploadException::class, static function (): void {
        Photo::fromUrl('https://cdn.example.test/img/avatar.txt')->validatePhoto();
    });

    $protocol = new TcpProtocol();
    $frameChunks = static function (array $payload, int $opcode, int $seq, int $cmd = Command::RESPONSE) use ($protocol): array {
        $raw = $protocol->encode(new OutboundFrame(TcpProtocol::VERSION, $opcode, $seq, $payload, $cmd));
        return [substr($raw, 0, 10), substr($raw, 10)];
    };
    $decodeSent = static function (UploadServiceTestTransport $transport, int $index) use ($protocol) {
        return $protocol->decode($transport->sent[$index]);
    };
    $privateArray = static function ($object, string $property): array {
        $reflection = new ReflectionProperty(get_class($object), $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    };
    $assertUploadStateEmpty = static function ($uploadService) use ($assertSame, $privateArray): void {
        $assertSame([], $privateArray($uploadService, 'readyVideos'));
        $assertSame([], $privateArray($uploadService, 'readyFiles'));
        $assertSame([], $privateArray($uploadService, 'expectedVideos'));
        $assertSame([], $privateArray($uploadService, 'expectedFiles'));
    };

    $chunks = array_merge(
        $frameChunks(['url' => 'https://upload.test/photo?photoIds=photo-1'], Opcode::PHOTO_UPLOAD, 0),
        $frameChunks(['info' => [['url' => 'https://upload.test/video', 'videoId' => 700, 'token' => 'video-token']]], Opcode::VIDEO_UPLOAD, 1),
        $frameChunks(['videoId' => 999], Opcode::NOTIF_ATTACH, 89, Command::REQUEST),
        $frameChunks(['videoId' => 700], Opcode::NOTIF_ATTACH, 90, Command::REQUEST),
        $frameChunks(['info' => [['url' => 'https://upload.test/file', 'fileId' => 800, 'token' => 'file-token']]], Opcode::FILE_UPLOAD, 2),
        $frameChunks(['fileId' => 999], Opcode::NOTIF_ATTACH, 91, Command::REQUEST),
        $frameChunks(['fileId' => 800], Opcode::NOTIF_ATTACH, 92, Command::REQUEST)
    );

    $uploader = new UploadServiceFakeUploader();
    $transport = new UploadServiceTestTransport($chunks);
    $manager = new ConnectionManager($transport, $protocol);
    $events = [];
    $manager->setEventHandler(static function (InboundFrame $frame) use (&$events): void {
        $events[] = $frame;
    });
    $manager->open();
    $app = new App($manager, new ClientOptions([
        'httpUploader' => $uploader,
        'requestTimeout' => 1.0,
        'uploadProcessingTimeout' => 1.0,
        'uploadChunkSize' => 3,
    ]));

    $photoPayload = $app->api()->uploads->uploadPhoto(Photo::fromRaw('photo-bytes', 'image.png'));
    $assertSame(['_type' => 'PHOTO', 'photoToken' => 'photo-token'], $photoPayload->toArray());
    $assertSame(['count' => 1, 'profile' => false], $decodeSent($transport, 0)->payload);
    $assertSame('file', $uploader->multipart[0]['fieldName']);
    $assertSame('image.png', $uploader->multipart[0]['filename']);
    $assertSame('image/png', $uploader->multipart[0]['contentType']);

    $makeUploadApp = static function (array $chunks, UploadServiceFakeUploader $uploader) use ($protocol): App {
        $transport = new UploadServiceTestTransport($chunks);
        $manager = new ConnectionManager($transport, $protocol);
        $manager->open();

        return new App($manager, new ClientOptions([
            'httpUploader' => $uploader,
            'requestTimeout' => 1.0,
            'uploadProcessingTimeout' => 0.1,
        ]));
    };

    $invalidJsonUploader = new UploadServiceFakeUploader();
    $invalidJsonUploader->multipartResponse = new HttpUploadResponse(200, 'not-json');
    $invalidJsonApp = $makeUploadApp($frameChunks(['url' => 'https://upload.test/photo?photoIds=photo-1'], Opcode::PHOTO_UPLOAD, 0), $invalidJsonUploader);
    $assertThrows(UploadException::class, static function () use ($invalidJsonApp): void {
        $invalidJsonApp->api()->uploads->uploadPhoto(Photo::fromRaw('photo-bytes', 'invalid-json.png'));
    }, 'uploadPhoto must fail on invalid HTTP JSON');

    $missingPhotosUploader = new UploadServiceFakeUploader();
    $missingPhotosUploader->multipartResponse = new HttpUploadResponse(200, json_encode(['unexpected' => []]));
    $missingPhotosApp = $makeUploadApp($frameChunks(['url' => 'https://upload.test/photo?photoIds=photo-1'], Opcode::PHOTO_UPLOAD, 0), $missingPhotosUploader);
    $assertThrows(UploadException::class, static function () use ($missingPhotosApp): void {
        $missingPhotosApp->api()->uploads->uploadPhoto(Photo::fromRaw('photo-bytes', 'missing-photos.png'));
    }, 'uploadPhoto must fail when HTTP JSON lacks required photos map');

    $missingTokenUploader = new UploadServiceFakeUploader();
    $missingTokenUploader->multipartResponse = new HttpUploadResponse(200, json_encode([
        'photos' => [
            'other-photo' => ['token' => 'other-token'],
        ],
    ]));
    $missingTokenApp = $makeUploadApp($frameChunks(['url' => 'https://upload.test/photo?photoIds=photo-1'], Opcode::PHOTO_UPLOAD, 0), $missingTokenUploader);
    $assertThrows(UploadException::class, static function () use ($missingTokenApp): void {
        $missingTokenApp->api()->uploads->uploadPhoto(Photo::fromRaw('photo-bytes', 'missing-token.png'));
    }, 'uploadPhoto must fail when HTTP JSON lacks token for requested photo_id');

    $emptyRawPhotoUploader = new UploadServiceFakeUploader();
    $emptyRawPhotoApp = $makeUploadApp($frameChunks(['url' => 'https://upload.test/photo?photoIds=photo-1'], Opcode::PHOTO_UPLOAD, 0), $emptyRawPhotoUploader);
    $assertThrows(UploadException::class, static function () use ($emptyRawPhotoApp): void {
        $emptyRawPhotoApp->api()->uploads->uploadPhoto(Photo::fromRaw('', 'empty.png'));
    }, 'uploadPhoto must reject empty raw photo before HTTP multipart upload');
    $assertSame(0, count($emptyRawPhotoUploader->multipart));

    $videoPayload = $app->api()->uploads->uploadVideo(Video::fromRaw('video-bytes', 'clip.mp4'));
    $assertSame(['_type' => 'VIDEO', 'videoId' => 700, 'token' => 'video-token'], $videoPayload->toArray());
    $assertSame(['count' => 1, 'profile' => false], $decodeSent($transport, 1)->payload);
    $assertSame('https://upload.test/video', $uploader->streams[0]['url']);
    $assertSame('attachment; filename=clip.mp4', $uploader->streams[0]['headers']['Content-Disposition']);
    $assertSame('0-10/11', $uploader->streams[0]['headers']['Content-Range']);
    $assertSame(['vid', 'eo-', 'byt', 'es'], $uploader->streams[0]['chunks']);

    $filePayload = $app->api()->uploads->uploadFile(File::fromRaw('file-bytes', 'report.pdf'));
    $assertSame(['_type' => 'FILE', 'fileId' => 800], $filePayload->toArray());
    $assertSame(['count' => 1, 'profile' => false], $decodeSent($transport, 2)->payload);
    $assertSame('https://upload.test/file', $uploader->streams[1]['url']);
    $assertSame('attachment; filename=report.pdf', $uploader->streams[1]['headers']['Content-Disposition']);
    $assertSame('0-9/10', $uploader->streams[1]['headers']['Content-Range']);
    $assertSame([Opcode::NOTIF_ATTACH, Opcode::NOTIF_ATTACH, Opcode::NOTIF_ATTACH, Opcode::NOTIF_ATTACH], [
        $events[0]->opcode,
        $events[1]->opcode,
        $events[2]->opcode,
        $events[3]->opcode,
    ]);
    $assertSame([999, 700], [$events[0]->payload['videoId'], $events[1]->payload['videoId']]);
    $assertSame([999, 800], [$events[2]->payload['fileId'], $events[3]->payload['fileId']]);
    $assertUploadStateEmpty($app->api()->uploads);

    $preReadyChunks = $frameChunks(
        ['info' => [['url' => 'https://upload.test/pre-ready-video', 'videoId' => 701, 'token' => 'ready-token']]],
        Opcode::VIDEO_UPLOAD,
        0
    );
    $preReadyUploader = new UploadServiceFakeUploader();
    $preReadyTransport = new UploadServiceTestTransport($preReadyChunks);
    $preReadyManager = new ConnectionManager($preReadyTransport, $protocol);
    $preReadyManager->open();
    $preReadyApp = new App($preReadyManager, new ClientOptions([
        'httpUploader' => $preReadyUploader,
        'requestTimeout' => 1.0,
        'uploadProcessingTimeout' => 0.1,
    ]));
    $preReadyUploader->onStreamUpload = static function () use ($preReadyManager): void {
        $preReadyManager->dispatchEvent(new InboundFrame(Opcode::NOTIF_ATTACH, Command::REQUEST, 900, ['videoId' => 701]));
    };
    $preReadyVideo = $preReadyApp->api()->uploads->uploadVideo(Video::fromRaw('ready-video', 'ready.mp4'));
    $assertSame(['_type' => 'VIDEO', 'videoId' => 701, 'token' => 'ready-token'], $preReadyVideo->toArray());
    $assertSame(1, count($preReadyTransport->sent), 'Pre-ready attach must avoid an extra blocking read');
    $assertUploadStateEmpty($preReadyApp->api()->uploads);

    $failedVideoUploader = new UploadServiceFakeUploader();
    $failedVideoUploader->streamResponse = new HttpUploadResponse(500, '');
    $failedVideoApp = $makeUploadApp(
        $frameChunks(
            ['info' => [['url' => 'https://upload.test/failed-video', 'videoId' => 703, 'token' => 'failed-token']]],
            Opcode::VIDEO_UPLOAD,
            0
        ),
        $failedVideoUploader
    );
    $assertThrows(UploadException::class, static function () use ($failedVideoApp): void {
        $failedVideoApp->api()->uploads->uploadVideo(Video::fromRaw('failed-video', 'failed.mp4'));
    }, 'uploadVideo must clear expected waiter state when HTTP upload fails');
    $assertUploadStateEmpty($failedVideoApp->api()->uploads);

    $emptyVideoUploader = new UploadServiceFakeUploader();
    $emptyVideoTransport = new UploadServiceTestTransport($frameChunks([], Opcode::VIDEO_UPLOAD, 0));
    $emptyVideoManager = new ConnectionManager($emptyVideoTransport, $protocol);
    $emptyVideoManager->open();
    $emptyVideoApp = new App($emptyVideoManager, new ClientOptions([
        'httpUploader' => $emptyVideoUploader,
        'requestTimeout' => 1.0,
    ]));
    $assertThrows(UploadException::class, static function () use ($emptyVideoApp): void {
        $emptyVideoApp->api()->uploads->uploadVideo(Video::fromRaw('video-bytes', 'empty-video.mp4'));
    }, 'uploadVideo must reject an empty init response before HTTP upload');
    $assertSame(0, count($emptyVideoUploader->streams));

    $emptyFileUploader = new UploadServiceFakeUploader();
    $emptyFileTransport = new UploadServiceTestTransport($frameChunks([], Opcode::FILE_UPLOAD, 0));
    $emptyFileManager = new ConnectionManager($emptyFileTransport, $protocol);
    $emptyFileManager->open();
    $emptyFileApp = new App($emptyFileManager, new ClientOptions([
        'httpUploader' => $emptyFileUploader,
        'requestTimeout' => 1.0,
    ]));
    $assertThrows(UploadException::class, static function () use ($emptyFileApp): void {
        $emptyFileApp->api()->uploads->uploadFile(File::fromRaw('file-bytes', 'empty-file.pdf'));
    }, 'uploadFile must reject an empty init response before HTTP upload');
    $assertSame(0, count($emptyFileUploader->streams));

    $missingVideoInfoUploader = new UploadServiceFakeUploader();
    $missingVideoInfoApp = $makeUploadApp($frameChunks(['unexpected' => true], Opcode::VIDEO_UPLOAD, 0), $missingVideoInfoUploader);
    $assertThrows(UploadException::class, static function () use ($missingVideoInfoApp): void {
        $missingVideoInfoApp->api()->uploads->uploadVideo(Video::fromRaw('video-bytes', 'missing-info.mp4'));
    }, 'uploadVideo must reject an init response without required info');
    $assertSame(0, count($missingVideoInfoUploader->streams));

    $associativeVideoInfoUploader = new UploadServiceFakeUploader();
    $associativeVideoInfoApp = $makeUploadApp(
        $frameChunks(['info' => ['first' => ['url' => 'https://upload.test/video', 'videoId' => 703, 'token' => 'token']]], Opcode::VIDEO_UPLOAD, 0),
        $associativeVideoInfoUploader
    );
    $assertThrows(UploadException::class, static function () use ($associativeVideoInfoApp): void {
        $associativeVideoInfoApp->api()->uploads->uploadVideo(Video::fromRaw('video-bytes', 'associative-info.mp4'));
    }, 'uploadVideo must reject associative init info maps before HTTP upload');
    $assertSame(0, count($associativeVideoInfoUploader->streams));

    $invalidVideoIdUploader = new UploadServiceFakeUploader();
    $invalidVideoIdApp = $makeUploadApp(
        $frameChunks(['info' => [['url' => 'https://upload.test/video', 'videoId' => 0, 'token' => 'token']]], Opcode::VIDEO_UPLOAD, 0),
        $invalidVideoIdUploader
    );
    $assertThrows(UploadException::class, static function () use ($invalidVideoIdApp): void {
        $invalidVideoIdApp->api()->uploads->uploadVideo(Video::fromRaw('video-bytes', 'invalid-video-id.mp4'));
    }, 'uploadVideo must reject non-positive videoId before HTTP upload');
    $assertSame(0, count($invalidVideoIdUploader->streams));

    $emptyVideoTokenUploader = new UploadServiceFakeUploader();
    $emptyVideoTokenApp = $makeUploadApp(
        $frameChunks(['info' => [['url' => 'https://upload.test/video', 'videoId' => 704, 'token' => '']]], Opcode::VIDEO_UPLOAD, 0),
        $emptyVideoTokenUploader
    );
    $assertThrows(UploadException::class, static function () use ($emptyVideoTokenApp): void {
        $emptyVideoTokenApp->api()->uploads->uploadVideo(Video::fromRaw('video-bytes', 'empty-video-token.mp4'));
    }, 'uploadVideo must reject empty upload token before HTTP upload');
    $assertSame(0, count($emptyVideoTokenUploader->streams));

    $malformedFileInfoUploader = new UploadServiceFakeUploader();
    $malformedFileInfoApp = $makeUploadApp(
        $frameChunks(['info' => [['url' => 'https://upload.test/file', 'fileId' => 803]]], Opcode::FILE_UPLOAD, 0),
        $malformedFileInfoUploader
    );
    $assertThrows(UploadException::class, static function () use ($malformedFileInfoApp): void {
        $malformedFileInfoApp->api()->uploads->uploadFile(File::fromRaw('file-bytes', 'malformed-info.pdf'));
    }, 'uploadFile must reject malformed init info before HTTP upload');
    $assertSame(0, count($malformedFileInfoUploader->streams));

    $emptyFileUrlUploader = new UploadServiceFakeUploader();
    $emptyFileUrlApp = $makeUploadApp(
        $frameChunks(['info' => [['url' => '', 'fileId' => 804, 'token' => 'file-token']]], Opcode::FILE_UPLOAD, 0),
        $emptyFileUrlUploader
    );
    $assertThrows(UploadException::class, static function () use ($emptyFileUrlApp): void {
        $emptyFileUrlApp->api()->uploads->uploadFile(File::fromRaw('file-bytes', 'empty-file-url.pdf'));
    }, 'uploadFile must reject empty upload URL before HTTP upload');
    $assertSame(0, count($emptyFileUrlUploader->streams));

    $invalidFileIdUploader = new UploadServiceFakeUploader();
    $invalidFileIdApp = $makeUploadApp(
        $frameChunks(['info' => [['url' => 'https://upload.test/file', 'fileId' => 0, 'token' => 'file-token']]], Opcode::FILE_UPLOAD, 0),
        $invalidFileIdUploader
    );
    $assertThrows(UploadException::class, static function () use ($invalidFileIdApp): void {
        $invalidFileIdApp->api()->uploads->uploadFile(File::fromRaw('file-bytes', 'invalid-file-id.pdf'));
    }, 'uploadFile must reject non-positive fileId before HTTP upload');
    $assertSame(0, count($invalidFileIdUploader->streams));

    $clientChunks = array_merge(
        $frameChunks(['url' => 'https://upload.test/photo?photoIds=photo-1'], Opcode::PHOTO_UPLOAD, 0),
        $frameChunks(['info' => [['url' => 'https://upload.test/client-video', 'videoId' => 702, 'token' => 'client-video-token']]], Opcode::VIDEO_UPLOAD, 1),
        $frameChunks(['videoId' => 702], Opcode::NOTIF_ATTACH, 92, Command::REQUEST),
        $frameChunks(['info' => [['url' => 'https://upload.test/client-file', 'fileId' => 802, 'token' => 'client-file-token']]], Opcode::FILE_UPLOAD, 2),
        $frameChunks(['fileId' => 802], Opcode::NOTIF_ATTACH, 93, Command::REQUEST)
    );
    $clientUploader = new UploadServiceFakeUploader();
    $clientTransport = new UploadServiceTestTransport($clientChunks);
    $clientManager = new ConnectionManager($clientTransport, $protocol);
    $client = new Client(new ClientOptions([
        'httpUploader' => $clientUploader,
        'requestTimeout' => 1.0,
        'uploadProcessingTimeout' => 1.0,
    ]), $clientManager);
    $clientManager->open();

    $clientPhoto = $client->uploadPhoto(Photo::fromRaw('photo-bytes', 'client.png'));
    $assert($clientPhoto instanceof AttachPhotoPayload, 'Client::uploadPhoto must preserve typed payload');
    $assertSame(['_type' => 'PHOTO', 'photoToken' => 'photo-token'], $clientPhoto->toArray());

    $clientVideo = $client->uploadVideo(Video::fromRaw('video-bytes', 'client.mp4'));
    $assert($clientVideo instanceof VideoAttachPayload, 'Client::uploadVideo must preserve typed payload');
    $assertSame(['_type' => 'VIDEO', 'videoId' => 702, 'token' => 'client-video-token'], $clientVideo->toArray());

    $clientFile = $client->uploadFile(File::fromRaw('file-bytes', 'client.pdf'));
    $assert($clientFile instanceof AttachFilePayload, 'Client::uploadFile must preserve typed payload');
    $assertSame(['_type' => 'FILE', 'fileId' => 802], $clientFile->toArray());

    $messageChunks = array_merge(
        $frameChunks(['url' => 'https://upload.test/photo?photoIds=photo-1'], Opcode::PHOTO_UPLOAD, 0),
        $frameChunks(['id' => 42, 'chatId' => 100, 'time' => 1, 'type' => 'USER', 'text' => 'photo'], Opcode::MSG_SEND, 1)
    );
    $messageUploader = new UploadServiceFakeUploader();
    $messageTransport = new UploadServiceTestTransport($messageChunks);
    $messageManager = new ConnectionManager($messageTransport, $protocol);
    $messageManager->open();
    $messageApp = new App($messageManager, new ClientOptions(['httpUploader' => $messageUploader, 'requestTimeout' => 1.0]));
    $message = $messageApp->api()->messages->sendMessage(100, 'photo', null, [Photo::fromRaw('photo-bytes', 'image.png')]);
    $assertSame(42, $message->id);
    $sendPayload = $decodeSent($messageTransport, 1)->payload;
    $assertSame(['_type' => 'PHOTO', 'photoToken' => 'photo-token'], $sendPayload['message']['attaches'][0]);

    $accountChunks = array_merge(
        $frameChunks(['url' => 'https://upload.test/photo?photoIds=profile-1'], Opcode::PHOTO_UPLOAD, 0),
        $frameChunks(['profile' => [
            'contact' => ['id' => 77, 'names' => [['name' => 'Me', 'type' => 'NICK']]],
            'profileOptions' => ['showPhone' => false],
        ]], Opcode::PROFILE, 1)
    );
    $accountUploader = new UploadServiceFakeUploader();
    $accountTransport = new UploadServiceTestTransport($accountChunks);
    $accountManager = new ConnectionManager($accountTransport, $protocol);
    $accountManager->open();
    $accountApp = new App($accountManager, new ClientOptions(['httpUploader' => $accountUploader, 'requestTimeout' => 1.0]));
    $assertSame(true, $accountApp->api()->account->changeProfile('First', null, null, Photo::fromRaw('photo-bytes', 'avatar.png')));
    $assertSame(['count' => 1, 'profile' => true], $decodeSent($accountTransport, 0)->payload);
    $assertSame('profile-token', $decodeSent($accountTransport, 1)->payload['photoToken']);
};
