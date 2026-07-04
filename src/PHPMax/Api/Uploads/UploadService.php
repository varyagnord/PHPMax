<?php

declare(strict_types=1);

namespace PHPMax\Api\Uploads;

use PHPMax\Domain\Events\FileUploadSignal;
use PHPMax\Domain\Events\VideoUploadSignal;
use PHPMax\Dispatch\EventType;
use PHPMax\Exception\ProtocolException;
use PHPMax\Exception\UploadException;
use PHPMax\Files\File;
use PHPMax\Files\Photo;
use PHPMax\Files\Video;
use PHPMax\Protocol\Opcode;
use PHPMax\Runtime\App;
use Throwable;

class UploadService
{
    /** @var App */
    private $app;
    /** @var HttpUploaderInterface */
    private $uploader;
    /** @var array<int, bool> */
    private $readyVideos = [];
    /** @var array<int, bool> */
    private $readyFiles = [];
    /** @var array<int, bool> */
    private $expectedVideos = [];
    /** @var array<int, bool> */
    private $expectedFiles = [];

    public function __construct(App $app, ?HttpUploaderInterface $uploader = null)
    {
        $this->app = $app;
        $this->uploader = $uploader ?: ($app->options()->httpUploader ?: new NativeHttpUploader(
            $app->options()->uploadHttpTimeout,
            $app->options()->proxy
        ));
        $this->app->onInternal(EventType::VIDEO_READY, [$this, 'onVideoAttach']);
        $this->app->onInternal(EventType::FILE_READY, [$this, 'onFileAttach']);
    }

    public function uploadPhoto(Photo $photo, bool $profile = false): AttachPhotoPayload
    {
        try {
            $response = $this->app->invoke(Opcode::PHOTO_UPLOAD, (new UploadPayload(['profile' => $profile]))->toArray());
        } catch (Throwable $e) {
            throw new UploadException('Failed to request photo upload URL', 0, $e);
        }

        $url = $response->payload['url'] ?? null;
        if (!is_string($url) || $url === '') {
            throw new UploadException('No photo upload URL received');
        }

        $photoId = $this->parsePhotoId($url);
        [$extension, $mime] = $photo->validatePhoto();

        try {
            $httpResponse = $this->uploader->uploadMultipart(
                $url,
                'file',
                $photo->read(),
                'image.' . rawurlencode($extension),
                $mime
            );
        } catch (Throwable $e) {
            if ($e instanceof UploadException) {
                throw $e;
            }
            throw new UploadException('HTTP error during photo upload', 0, $e);
        }

        $this->assertStatusOk($httpResponse, 'Photo upload failed');
        try {
            $uploadResponse = PhotoUploadResponse::fromArray($httpResponse->json());
            if (!is_array($uploadResponse->photos)) {
                throw new UploadException('Invalid photo upload response model');
            }
        } catch (UploadException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new UploadException('Invalid photo upload response model', 0, $e);
        }
        $photoResult = $uploadResponse->photos[$photoId] ?? null;
        if (!$photoResult instanceof PhotoPayloadResponse || $photoResult->token === null || $photoResult->token === '') {
            throw new UploadException('Photo upload response does not contain token for photo_id=' . $photoId);
        }

        return new AttachPhotoPayload(['photoToken' => $photoResult->token]);
    }

    public function uploadVideo(Video $video): VideoAttachPayload
    {
        try {
            $response = $this->app->invoke(Opcode::VIDEO_UPLOAD, (new UploadPayload())->toArray());
        } catch (Throwable $e) {
            throw new UploadException('Failed to request video upload URL', 0, $e);
        }

        try {
            $uploadResponse = VideoUploadResponse::fromArray($this->requireUploadResponsePayload(
                $response->payload,
                'Invalid video upload response model'
            ));
        } catch (UploadException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new UploadException('Invalid video upload response model', 0, $e);
        }
        $uploadInfo = $uploadResponse->info[0] ?? null;
        if (!$uploadInfo instanceof VideoPayloadResponse) {
            throw new UploadException('Video upload response info is empty');
        }
        $this->assertVideoUploadInfo($uploadInfo);

        $size = $this->nonEmptySize($video, 'video');
        $headers = [
            'Content-Disposition' => 'attachment; filename=' . rawurlencode($video->name()),
            'Content-Range' => '0-' . ($size - 1) . '/' . $size,
            'Content-Length' => (string) $size,
            'Connection' => 'keep-alive',
        ];

        $videoId = (int) $uploadInfo->videoId;
        $this->expectedVideos[$videoId] = true;

        try {
            try {
                $httpResponse = $this->uploader->uploadStream(
                    (string) $uploadInfo->url,
                    $headers,
                    $video->iterChunks($this->app->options()->uploadChunkSize),
                    $size
                );
            } catch (Throwable $e) {
                if ($e instanceof UploadException) {
                    throw $e;
                }
                throw new UploadException('HTTP error during video upload video_id=' . $uploadInfo->videoId, 0, $e);
            }

            $this->assertStatusOk($httpResponse, 'Video upload failed with status');
            $this->waitForAttach('video', $videoId);

            return new VideoAttachPayload([
                'videoId' => $videoId,
                'token' => (string) $uploadInfo->token,
            ]);
        } finally {
            unset($this->expectedVideos[$videoId], $this->readyVideos[$videoId]);
        }
    }

    public function uploadFile(File $file): AttachFilePayload
    {
        try {
            $response = $this->app->invoke(Opcode::FILE_UPLOAD, (new UploadPayload())->toArray());
        } catch (Throwable $e) {
            throw new UploadException('Failed to request file upload URL', 0, $e);
        }

        try {
            $uploadResponse = FileUploadResponse::fromArray($this->requireUploadResponsePayload(
                $response->payload,
                'Invalid file upload response model'
            ));
        } catch (UploadException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new UploadException('Invalid file upload response model', 0, $e);
        }
        $uploadInfo = $uploadResponse->info[0] ?? null;
        if (!$uploadInfo instanceof FilePayloadResponse) {
            throw new UploadException('File upload response info is empty');
        }
        $this->assertFileUploadInfo($uploadInfo);

        $size = $this->nonEmptySize($file, 'file');
        $headers = [
            'Content-Disposition' => 'attachment; filename=' . rawurlencode($file->name()),
            'Content-Length' => (string) $size,
            'Content-Range' => '0-' . ($size - 1) . '/' . $size,
        ];

        $fileId = (int) $uploadInfo->fileId;
        $this->expectedFiles[$fileId] = true;

        try {
            try {
                $httpResponse = $this->uploader->uploadStream(
                    (string) $uploadInfo->url,
                    $headers,
                    $file->iterChunks($this->app->options()->uploadChunkSize),
                    $size
                );
            } catch (Throwable $e) {
                if ($e instanceof UploadException) {
                    throw $e;
                }
                throw new UploadException('HTTP error during file upload file_id=' . $uploadInfo->fileId, 0, $e);
            }

            $this->assertStatusOk($httpResponse, 'File upload failed with status');
            $this->waitForAttach('file', $fileId);

            return new AttachFilePayload(['fileId' => $fileId]);
        } finally {
            unset($this->expectedFiles[$fileId], $this->readyFiles[$fileId]);
        }
    }

    public function onVideoAttach(VideoUploadSignal $attach): void
    {
        if ($attach->videoId !== null && isset($this->expectedVideos[(int) $attach->videoId])) {
            $this->readyVideos[(int) $attach->videoId] = true;
        }
    }

    public function onFileAttach(FileUploadSignal $attach): void
    {
        if ($attach->fileId !== null && isset($this->expectedFiles[(int) $attach->fileId])) {
            $this->readyFiles[(int) $attach->fileId] = true;
        }
    }

    private function parsePhotoId(string $url): string
    {
        $query = (string) parse_url($url, PHP_URL_QUERY);
        $params = [];
        parse_str($query, $params);
        $photoIds = $params['photoIds'] ?? null;
        if (is_array($photoIds)) {
            $photoIds = $photoIds[0] ?? null;
        }
        if ($photoIds === null || $photoIds === '') {
            throw new UploadException('Photo upload URL does not contain photoIds');
        }

        return (string) $photoIds;
    }

    private function assertStatusOk(HttpUploadResponse $response, string $message): void
    {
        if ($response->status() !== 200) {
            throw new UploadException($message . ' ' . $response->status());
        }
    }

    private function nonEmptySize($file, string $type): int
    {
        $size = $file->size();
        if ($size <= 0) {
            throw new UploadException(ucfirst($type) . ' upload source is empty');
        }

        return $size;
    }

    private function assertVideoUploadInfo(VideoPayloadResponse $uploadInfo): void
    {
        if ($uploadInfo->url === null || $uploadInfo->url === '') {
            throw new UploadException('Video upload response URL is empty');
        }
        if ($uploadInfo->videoId === null || $uploadInfo->videoId <= 0) {
            throw new UploadException('Video upload response videoId is invalid');
        }
        if ($uploadInfo->token === null || $uploadInfo->token === '') {
            throw new UploadException('Video upload response token is empty');
        }
    }

    private function assertFileUploadInfo(FilePayloadResponse $uploadInfo): void
    {
        if ($uploadInfo->url === null || $uploadInfo->url === '') {
            throw new UploadException('File upload response URL is empty');
        }
        if ($uploadInfo->fileId === null || $uploadInfo->fileId <= 0) {
            throw new UploadException('File upload response fileId is invalid');
        }
        if ($uploadInfo->token === null || $uploadInfo->token === '') {
            throw new UploadException('File upload response token is empty');
        }
    }

    /**
     * @param array<mixed>|null $payload
     * @return array<mixed>
     */
    private function requireUploadResponsePayload(?array $payload, string $message): array
    {
        if ($payload === null || $payload === []) {
            throw new UploadException($message);
        }

        return $payload;
    }

    private function waitForAttach(string $kind, int $id): void
    {
        $timeout = $this->app->options()->uploadProcessingTimeout;
        $deadline = microtime(true) + $timeout;

        while (true) {
            if ($this->consumeReadyAttach($kind, $id)) {
                return;
            }

            $remaining = $deadline - microtime(true);
            if ($remaining <= 0) {
                throw new UploadException('Timed out waiting for ' . $kind . ' processing ' . $kind . '_id=' . $id);
            }

            try {
                $frame = $this->app->connection()->readFrame(min($this->app->options()->requestTimeout, max(0.001, $remaining)));
            } catch (ProtocolException $e) {
                if ($this->isTimeout($e)) {
                    continue;
                }
                throw new UploadException('Connection error while waiting for ' . $kind . ' processing', 0, $e);
            }

            $this->app->connection()->dispatchEvent($frame);
            if ($this->consumeReadyAttach($kind, $id)) {
                return;
            }
        }
    }

    private function consumeReadyAttach(string $kind, int $id): bool
    {
        if ($kind === 'file') {
            if (!isset($this->readyFiles[$id])) {
                return false;
            }

            unset($this->readyFiles[$id]);

            return true;
        }

        if (!isset($this->readyVideos[$id])) {
            return false;
        }

        unset($this->readyVideos[$id]);

        return true;
    }

    private function isTimeout(ProtocolException $exception): bool
    {
        return stripos($exception->getMessage(), 'timed out') !== false;
    }
}
