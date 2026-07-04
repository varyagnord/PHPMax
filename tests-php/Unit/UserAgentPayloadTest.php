<?php

declare(strict_types=1);

use PHPMax\Api\Session\DeviceType;
use PHPMax\Api\Session\MobileUserAgentPayload;
use PHPMax\Config\ClientOptions;

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $appVersions = [
        '26.14.1' => 6686,
        '26.14.0' => 6685,
        '26.13.0' => 6683,
        '26.12.2' => 6681,
        '26.12.1' => 6679,
        '26.12.0' => 6678,
        '26.11.3' => 6680,
        '26.11.2' => 6669,
        '26.11.1' => 6665,
        '26.11.0' => 6661,
    ];
    $timezones = [
        'Europe/Moscow',
        'Europe/Kaliningrad',
        'Europe/Samara',
        'Asia/Yekaterinburg',
        'Asia/Omsk',
        'Asia/Novosibirsk',
        'Asia/Krasnoyarsk',
        'Asia/Irkutsk',
        'Asia/Yakutsk',
        'Asia/Vladivostok',
    ];

    for ($i = 0; $i < 8; $i++) {
        $android = MobileUserAgentPayload::randomAndroid();
        $assertSame(DeviceType::ANDROID, $android->deviceType);
        $assert(array_key_exists((string) $android->appVersion, $appVersions), 'Android app version must come from PyMax anchors');
        $assertSame($appVersions[(string) $android->appVersion], $android->buildNumber);
        $assert(in_array($android->timezone, $timezones, true), 'Android timezone must come from PyMax anchors');
        $assert(in_array($android->osVersion, ['Android 12', 'Android 13', 'Android 14'], true), 'Android OS version must come from PyMax device anchors');
        $assertSame('GCM', $android->pushDeviceType);
        $assertSame('arm64-v8a', $android->arch);
        $assertSame('ru', $android->locale);
        $assertSame('ru', $android->deviceLocale);
        $assert(strpos((string) $android->screen, 'dpi') !== false, 'Android screen must include PyMax dpi descriptor');
        $assert($android->deviceName !== null && $android->deviceName !== '', 'Android device name must be present');
    }

    $options = new ClientOptions();
    $assertSame(DeviceType::ANDROID, $options->userAgent->deviceType);
    $assert(array_key_exists((string) $options->userAgent->appVersion, $appVersions), 'ClientOptions default user-agent must use PyMax app anchors');

    $web = MobileUserAgentPayload::randomWeb();
    $assertSame(DeviceType::WEB, $web->deviceType);
    $assertSame('26.5.5', $web->appVersion);
    $assertSame('Linux', $web->osVersion);
    $assertSame('1080x1920 1.0x', $web->screen);
    $assertSame('Chrome', $web->deviceName);
    $assertSame('ru', $web->locale);
    $assert(in_array($web->timezone, $timezones, true), 'Web timezone must come from PyMax anchors');
    $assertSame(MobileUserAgentPayload::DEFAULT_WEB_HEADER_USER_AGENT, $web->headerUserAgent);

    $webPayload = $web->toWebPayload();
    $assertSame(DeviceType::WEB, $webPayload['deviceType']);
    $assertSame(MobileUserAgentPayload::DEFAULT_WEB_HEADER_USER_AGENT, $webPayload['headerUserAgent']);
    $assert(!array_key_exists('pushDeviceType', $webPayload), 'Web payload must keep PyMax web aliases only');
    $assert(!array_key_exists('arch', $webPayload), 'Web payload must keep PyMax web aliases only');

    $webWithoutHeader = new MobileUserAgentPayload([
        'deviceType' => DeviceType::WEB,
        'appVersion' => '26.5.5',
        'osVersion' => 'Linux',
        'timezone' => 'Europe/Moscow',
        'screen' => '1080x1920 1.0x',
        'locale' => 'ru',
        'deviceName' => 'Chrome',
        'deviceLocale' => 'ru',
    ]);
    $assertSame(MobileUserAgentPayload::DEFAULT_WEB_HEADER_USER_AGENT, $webWithoutHeader->toWebPayload()['headerUserAgent']);
};
