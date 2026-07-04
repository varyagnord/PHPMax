<?php

declare(strict_types=1);

use PHPMax\Formatting\Formatter;

return static function (callable $assert, callable $assertSame, callable $assertThrows): void {
    $normalize = static function (array $entities): array {
        $result = [];
        foreach ($entities as $entity) {
            $result[] = [
                $entity->type,
                $entity->from,
                $entity->length,
                $entity->attributes !== null ? $entity->attributes->url : null,
            ];
        }

        return $result;
    };

    $cases = [
        'all inline markers' => [
            '*em* _em2_ **strong** __under__ ~~strike~~ `mono`',
            'em em2 strong under strike mono',
            [
                ['EMPHASIZED', 0, 2, null],
                ['EMPHASIZED', 3, 3, null],
                ['STRONG', 7, 6, null],
                ['UNDERLINE', 14, 5, null],
                ['STRIKETHROUGH', 20, 6, null],
                ['MONOSPACED', 27, 4, null],
            ],
        ],
        'multiline code skips language line' => [
            "A ```php\necho \"x\";\n``` Z",
            "A echo \"x\";\n Z",
            [
                ['CODE', 2, 10, null],
            ],
        ],
        'invalid and multiline inline markers stay mostly literal' => [
            "bad ** ** and **line\nbreak** and [x]()",
            "bad   and **line\nbreak** and [x]()",
            [
                ['STRONG', 4, 1, null],
            ],
        ],
        'nested markers preserve PyMax close order' => [
            '**bold _still bold_**',
            'bold still bold',
            [
                ['EMPHASIZED', 5, 10, null],
                ['STRONG', 0, 15, null],
            ],
        ],
        'emoji offsets use UTF-16 code units' => [
            '😀 *ok* [😀x](https://e.test)',
            '😀 ok 😀x',
            [
                ['EMPHASIZED', 3, 2, null],
                ['LINK', 6, 3, 'https://e.test'],
            ],
        ],
    ];

    foreach ($cases as $label => $case) {
        [$input, $expectedText, $expectedEntities] = $case;
        [$cleanText, $entities] = Formatter::formatMarkdown($input);

        $assertSame($expectedText, $cleanText, $label . ' clean text must match PyMax');
        $assertSame($expectedEntities, $normalize($entities), $label . ' entities must match PyMax');
    }
};
