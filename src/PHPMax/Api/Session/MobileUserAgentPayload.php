<?php

declare(strict_types=1);

namespace PHPMax\Api\Session;

use PHPMax\Support\Model;

class MobileUserAgentPayload extends Model
{
    public const DEFAULT_WEB_HEADER_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36';
    private const WEB_APP_VERSION = '26.5.5';
    private const WEB_SCREEN = '1080x1920 1.0x';
    private const APP_VERSIONS = [
        ['26.14.1', 6686],
        ['26.14.0', 6685],
        ['26.13.0', 6683],
        ['26.12.2', 6681],
        ['26.12.1', 6679],
        ['26.12.0', 6678],
        ['26.11.3', 6680],
        ['26.11.2', 6669],
        ['26.11.1', 6665],
        ['26.11.0', 6661],
    ];
    private const ANDROID_DEVICES = [
        ['Samsung SM-A525F', 'Android 13', '405dpi 405dpi 1080x2400', 'arm64-v8a'],
        ['Samsung SM-A536B', 'Android 14', '405dpi 405dpi 1080x2400', 'arm64-v8a'],
        ['Samsung SM-A546E', 'Android 14', '405dpi 405dpi 1080x2340', 'arm64-v8a'],
        ['Samsung SM-G991B', 'Android 14', '421dpi 421dpi 1080x2400', 'arm64-v8a'],
        ['Samsung SM-G998B', 'Android 13', '515dpi 515dpi 1440x3200', 'arm64-v8a'],
        ['Samsung SM-S901B', 'Android 14', '425dpi 425dpi 1080x2340', 'arm64-v8a'],
        ['Samsung SM-S911B', 'Android 14', '425dpi 425dpi 1080x2340', 'arm64-v8a'],
        ['Xiaomi 2109119DG', 'Android 13', '395dpi 395dpi 1080x2400', 'arm64-v8a'],
        ['Xiaomi 2201117TG', 'Android 13', '395dpi 395dpi 1080x2400', 'arm64-v8a'],
        ['Xiaomi 2201123G', 'Android 14', '526dpi 526dpi 1440x3200', 'arm64-v8a'],
        ['Xiaomi 2210132G', 'Android 14', '446dpi 446dpi 1220x2712', 'arm64-v8a'],
        ['Xiaomi 23049PCD8G', 'Android 14', '446dpi 446dpi 1220x2712', 'arm64-v8a'],
        ['Redmi 2201116TG', 'Android 13', '395dpi 395dpi 1080x2400', 'arm64-v8a'],
        ['Redmi 22101316G', 'Android 13', '395dpi 395dpi 1080x2400', 'arm64-v8a'],
        ['Redmi 23021RAA2Y', 'Android 14', '395dpi 395dpi 1080x2400', 'arm64-v8a'],
        ['POCO 22011211G', 'Android 13', '395dpi 395dpi 1080x2400', 'arm64-v8a'],
        ['POCO 23049PCD8G', 'Android 14', '446dpi 446dpi 1220x2712', 'arm64-v8a'],
        ['Pixel 6', 'Android 14', '411dpi 411dpi 1080x2400', 'arm64-v8a'],
        ['Pixel 6a', 'Android 14', '429dpi 429dpi 1080x2400', 'arm64-v8a'],
        ['Pixel 7', 'Android 14', '416dpi 416dpi 1080x2400', 'arm64-v8a'],
        ['Pixel 7 Pro', 'Android 14', '512dpi 512dpi 1440x3120', 'arm64-v8a'],
        ['Pixel 8', 'Android 14', '428dpi 428dpi 1080x2400', 'arm64-v8a'],
        ['OnePlus NE2213', 'Android 14', '525dpi 525dpi 1440x3216', 'arm64-v8a'],
        ['OnePlus CPH2449', 'Android 14', '451dpi 451dpi 1240x2772', 'arm64-v8a'],
        ['realme RMX3085', 'Android 13', '409dpi 409dpi 1080x2400', 'arm64-v8a'],
        ['realme RMX3370', 'Android 13', '409dpi 409dpi 1080x2400', 'arm64-v8a'],
        ['realme RMX3630', 'Android 13', '400dpi 400dpi 1080x2412', 'arm64-v8a'],
        ['HUAWEI ELS-NX9', 'Android 12', '441dpi 441dpi 1080x2340', 'arm64-v8a'],
        ['HUAWEI VOG-L29', 'Android 12', '398dpi 398dpi 1080x2340', 'arm64-v8a'],
        ['HONOR RMO-NX1', 'Android 13', '391dpi 391dpi 1080x2388', 'arm64-v8a'],
        ['HONOR REA-NX9', 'Android 13', '435dpi 435dpi 1200x2664', 'arm64-v8a'],
    ];
    private const LOCALE_TIMEZONES = [
        ['ru', 'Europe/Moscow'],
        ['ru', 'Europe/Kaliningrad'],
        ['ru', 'Europe/Samara'],
        ['ru', 'Asia/Yekaterinburg'],
        ['ru', 'Asia/Omsk'],
        ['ru', 'Asia/Novosibirsk'],
        ['ru', 'Asia/Krasnoyarsk'],
        ['ru', 'Asia/Irkutsk'],
        ['ru', 'Asia/Yakutsk'],
        ['ru', 'Asia/Vladivostok'],
    ];

    /** @var string|null */
    public $deviceType;
    /** @var string|null */
    public $appVersion;
    /** @var string|null */
    public $osVersion;
    /** @var string|null */
    public $timezone;
    /** @var string|null */
    public $screen;
    /** @var string|null */
    public $pushDeviceType;
    /** @var string|null */
    public $arch;
    /** @var string|null */
    public $locale;
    /** @var int|null */
    public $buildNumber;
    /** @var string|null */
    public $deviceName;
    /** @var string|null */
    public $deviceLocale;
    /** @var int|null */
    public $release;
    /** @var string|null */
    public $headerUserAgent;

    protected static function schema(): array
    {
        return [
            'deviceType' => ['type' => 'string', 'required' => true],
            'appVersion' => ['type' => 'string', 'required' => true],
            'osVersion' => ['type' => 'string', 'required' => true],
            'timezone' => ['type' => 'string', 'required' => true],
            'screen' => ['type' => 'string', 'required' => true],
            'pushDeviceType' => ['type' => 'string'],
            'arch' => ['type' => 'string'],
            'locale' => ['type' => 'string', 'required' => true],
            'buildNumber' => ['type' => 'int'],
            'deviceName' => ['type' => 'string', 'required' => true],
            'deviceLocale' => ['type' => 'string', 'required' => true],
            'release' => ['type' => 'int'],
            'headerUserAgent' => ['type' => 'string'],
        ];
    }

    public static function defaultAndroid(): self
    {
        return self::randomAndroid();
    }

    public static function randomAndroid(): self
    {
        $app = self::pick(self::APP_VERSIONS);
        $device = self::pick(self::ANDROID_DEVICES);
        $locale = self::pick(self::LOCALE_TIMEZONES);

        return new self([
            'deviceType' => DeviceType::ANDROID,
            'appVersion' => $app[0],
            'osVersion' => $device[1],
            'timezone' => $locale[1],
            'screen' => $device[2],
            'pushDeviceType' => 'GCM',
            'arch' => $device[3],
            'locale' => $locale[0],
            'buildNumber' => $app[1],
            'deviceName' => $device[0],
            'deviceLocale' => $locale[0],
        ]);
    }

    public static function defaultWeb(): self
    {
        return self::randomWeb();
    }

    public static function randomWeb(): self
    {
        $locale = self::pick(self::LOCALE_TIMEZONES);

        return new self([
            'deviceType' => DeviceType::WEB,
            'appVersion' => self::WEB_APP_VERSION,
            'osVersion' => 'Linux',
            'timezone' => $locale[1],
            'screen' => self::WEB_SCREEN,
            'locale' => $locale[0],
            'deviceName' => 'Chrome',
            'deviceLocale' => $locale[0],
            'headerUserAgent' => self::DEFAULT_WEB_HEADER_USER_AGENT,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebPayload(): array
    {
        $payload = $this->toArray(true);
        if ($this->deviceType === DeviceType::WEB && !isset($payload['headerUserAgent'])) {
            $payload['headerUserAgent'] = self::DEFAULT_WEB_HEADER_USER_AGENT;
        }

        $aliases = [
            'deviceType',
            'locale',
            'deviceLocale',
            'osVersion',
            'deviceName',
            'headerUserAgent',
            'appVersion',
            'screen',
            'timezone',
        ];
        $result = [];
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $payload)) {
                $result[$alias] = $payload[$alias];
            }
        }

        return $result;
    }

    /**
     * @param non-empty-list<array<int, string|int>> $items
     * @return array<int, string|int>
     */
    private static function pick(array $items): array
    {
        return $items[random_int(0, count($items) - 1)];
    }
}
