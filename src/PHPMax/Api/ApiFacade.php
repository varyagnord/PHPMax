<?php

declare(strict_types=1);

namespace PHPMax\Api;

use PHPMax\Api\Account\AccountService;
use PHPMax\Api\Auth\AuthService;
use PHPMax\Api\Bots\BotsService;
use PHPMax\Api\Chats\ChatService;
use PHPMax\Api\Messages\MessageService;
use PHPMax\Api\Session\SessionService;
use PHPMax\Api\Telemetry\TelemetryService;
use PHPMax\Api\Uploads\UploadService;
use PHPMax\Api\Users\UserService;
use PHPMax\Runtime\App;

class ApiFacade
{
    /** @var AuthService */
    public $auth;
    /** @var BotsService */
    public $bots;
    /** @var SessionService */
    public $session;
    /** @var TelemetryService */
    public $telemetry;
    /** @var MessageService */
    public $messages;
    /** @var UploadService */
    public $uploads;
    /** @var ChatService */
    public $chats;
    /** @var UserService */
    public $users;
    /** @var AccountService */
    public $account;

    public function __construct(App $app)
    {
        $this->auth = new AuthService($app);
        $this->bots = new BotsService($app);
        $this->session = new SessionService($app);
        $this->telemetry = new TelemetryService($app);
        $this->uploads = new UploadService($app);
        $this->messages = new MessageService($app);
        $this->chats = new ChatService($app);
        $this->users = new UserService($app);
        $this->account = new AccountService($app);
    }
}
