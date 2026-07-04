<?php

declare(strict_types=1);

namespace PHPMax\Api\Messages;

final class MessagePayloadKey
{
    public const MESSAGE = 'message';
    public const MESSAGES = 'messages';
    public const REACTION_INFO = 'reactionInfo';
    public const MESSAGES_REACTIONS = 'messagesReactions';

    private function __construct()
    {
    }
}
