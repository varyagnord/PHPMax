<?php

declare(strict_types=1);

namespace PHPMax\Dispatch;

final class EventType
{
    public const MESSAGE_NEW = 'message_new';
    public const MESSAGE_EDIT = 'message_edit';
    public const MESSAGE_DELETE = 'message_delete';
    public const MESSAGE_READ = 'message_read';
    public const TYPING = 'typing';
    public const PRESENCE = 'presence';
    public const REACTION_UPDATE = 'reaction_update';
    public const CHAT_UPDATE = 'chat_update';
    public const USER_UPDATE = 'user_update';
    public const VIDEO_READY = 'video_ready';
    public const FILE_READY = 'file_ready';
    public const RAW = 'raw';
    public const ON_START = 'on_start';

    private function __construct()
    {
    }
}

