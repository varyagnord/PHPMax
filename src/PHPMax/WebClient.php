<?php

declare(strict_types=1);

namespace PHPMax;

use PHPMax\Api\Session\DeviceType;
use PHPMax\Api\Session\MobileUserAgentPayload;
use PHPMax\Auth\ConsoleQrHandler;
use PHPMax\Auth\QrAuthFlow;
use PHPMax\Auth\QrHandlerInterface;
use PHPMax\Config\ClientOptions;
use PHPMax\Dispatch\Router;
use PHPMax\Protocol\Ws\WsProtocol;
use PHPMax\Runtime\ConnectionManager;
use PHPMax\Runtime\WebSocketFrameReader;
use PHPMax\Transport\WebSocketTransport;

class WebClient extends Client
{
    public function __construct(
        ?ClientOptions $options = null,
        ?QrHandlerInterface $qrHandler = null,
        ?ConnectionManager $connection = null,
        ?Router $router = null
    ) {
        $options = $options ?: new ClientOptions();
        if ($options->userAgent->deviceType !== DeviceType::WEB) {
            $options->userAgent = MobileUserAgentPayload::defaultWeb();
        }
        if ($options->authFlow === null) {
            $options->authFlow = new QrAuthFlow($qrHandler ?: new ConsoleQrHandler());
        }

        $connection = $connection ?: new ConnectionManager(
            new WebSocketTransport($options->wsUrl, $options->connectTimeout, 'https://web.max.ru', $options->proxy),
            new WsProtocol(),
            new WebSocketFrameReader()
        );

        parent::__construct($options, $connection, $router);
    }
}
