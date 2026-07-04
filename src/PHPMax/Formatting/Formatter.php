<?php

declare(strict_types=1);

namespace PHPMax\Formatting;

use PHPMax\Domain\Element;
use PHPMax\Domain\ElementAttributes;

final class Formatter
{
    private const MARKERS = [
        '```' => 'CODE',
        '**' => 'STRONG',
        '__' => 'UNDERLINE',
        '~~' => 'STRIKETHROUGH',
        '`' => 'MONOSPACED',
        '_' => 'EMPHASIZED',
        '*' => 'EMPHASIZED',
    ];

    private const MARKER_ORDER = ['```', '**', '__', '~~', '`', '_', '*'];

    private function __construct()
    {
    }

    /**
     * @return array{0: string, 1: list<Element>}
     */
    public static function formatMarkdown(string $text): array
    {
        $cleanText = '';
        $entities = [];
        $active = [];
        $i = 0;
        $cleanPos = 0;
        $lineStart = true;
        $length = strlen($text);

        while ($i < $length) {
            $parsedLink = self::parseLink($text, $i);
            if ($parsedLink !== null) {
                [$label, $url, $nextI] = $parsedLink;
                $start = $cleanPos;
                $labelLen = self::codeUnitsLen($label);
                $cleanText .= $label;
                $cleanPos += $labelLen;
                $entities[] = new Element([
                    'type' => 'LINK',
                    'from' => $start,
                    'length' => $labelLen,
                    'attributes' => new ElementAttributes(['url' => $url]),
                ]);
                $i = $nextI;
                $lineStart = false;
                continue;
            }

            if ($lineStart && substr($text, $i, 1) === '#') {
                $startI = $i;
                while ($i < $length && substr($text, $i, 1) === '#') {
                    $i++;
                }
                if ($i < $length && substr($text, $i, 1) === ' ') {
                    $i++;
                    $start = $cleanPos;
                    while ($i < $length && substr($text, $i, 1) !== "\n") {
                        [$ch, $next] = self::nextChar($text, $i);
                        $cleanText .= $ch;
                        $cleanPos += self::codeUnitsLen($ch);
                        $i = $next;
                    }
                    $entityLength = $cleanPos - $start;
                    if ($entityLength > 0) {
                        $entities[] = new Element(['type' => 'HEADING', 'from' => $start, 'length' => $entityLength]);
                    }
                    $lineStart = false;
                    continue;
                }
                $i = $startI;
            }

            if ($lineStart && substr($text, $i, 1) === '>') {
                $i++;
                if ($i < $length && substr($text, $i, 1) === ' ') {
                    $i++;
                }
                $start = $cleanPos;
                while ($i < $length && substr($text, $i, 1) !== "\n") {
                    [$ch, $next] = self::nextChar($text, $i);
                    $cleanText .= $ch;
                    $cleanPos += self::codeUnitsLen($ch);
                    $i = $next;
                }
                $entityLength = $cleanPos - $start;
                if ($entityLength > 0) {
                    $entities[] = new Element(['type' => 'QUOTE', 'from' => $start, 'length' => $entityLength]);
                }
                $lineStart = false;
                continue;
            }

            $handled = false;
            foreach (self::MARKER_ORDER as $marker) {
                if (substr($text, $i, strlen($marker)) !== $marker) {
                    continue;
                }
                $markerLen = strlen($marker);
                if (!array_key_exists($marker, $active)) {
                    if ($marker === '```') {
                        $closing = strpos($text, $marker, $i + $markerLen);
                        if ($closing === false || $closing === $i + $markerLen) {
                            $cleanText .= $marker;
                            $cleanPos += $markerLen;
                            $i += $markerLen;
                            $handled = true;
                            break;
                        }
                        $active[$marker] = $cleanPos;
                        $i += $markerLen;
                        $lineEnd = strpos($text, "\n", $i);
                        if ($lineEnd !== false && $lineEnd < $closing) {
                            $i = $lineEnd + 1;
                        }
                        $handled = true;
                        break;
                    }

                    $lineEnd = strpos($text, "\n", $i + $markerLen);
                    $closing = strpos($text, $marker, $i + $markerLen);
                    if ($lineEnd !== false && $closing !== false && $closing > $lineEnd) {
                        $closing = false;
                    }
                    if ($closing === false || $closing === $i + $markerLen) {
                        $cleanText .= $marker;
                        $cleanPos += $markerLen;
                        $i += $markerLen;
                        $handled = true;
                        break;
                    }
                    $active[$marker] = $cleanPos;
                    $i += $markerLen;
                    $handled = true;
                    break;
                }

                $start = $active[$marker];
                $entityLength = $cleanPos - $start;
                if ($entityLength > 0) {
                    $entities[] = new Element(['type' => self::MARKERS[$marker], 'from' => $start, 'length' => $entityLength]);
                }
                unset($active[$marker]);
                $i += $markerLen;
                $handled = true;
                break;
            }

            if ($handled) {
                $lineStart = false;
                continue;
            }

            [$ch, $next] = self::nextChar($text, $i);
            $cleanText .= $ch;
            $lineStart = $ch === "\n";
            $cleanPos += self::codeUnitsLen($ch);
            $i = $next;
        }

        return [$cleanText, $entities];
    }

    /**
     * @return array{0: string, 1: string, 2: int}|null
     */
    private static function parseLink(string $text, int $offset): ?array
    {
        if (substr($text, $offset, 1) !== '[') {
            return null;
        }
        $labelEnd = strpos($text, ']', $offset + 1);
        if ($labelEnd === false || substr($text, $labelEnd + 1, 1) !== '(') {
            return null;
        }
        $urlStart = $labelEnd + 2;
        $urlEnd = strpos($text, ')', $urlStart);
        if ($urlEnd === false) {
            return null;
        }
        $label = substr($text, $offset + 1, $labelEnd - $offset - 1);
        $url = substr($text, $urlStart, $urlEnd - $urlStart);
        if ($label === '' || $url === '') {
            return null;
        }

        return [$label, $url, $urlEnd + 1];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function nextChar(string $text, int $offset): array
    {
        if (preg_match('/./us', substr($text, $offset), $match) !== 1) {
            return [substr($text, $offset, 1), $offset + 1];
        }
        $ch = $match[0];

        return [$ch, $offset + strlen($ch)];
    }

    private static function codeUnitsLen(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        preg_match_all('/./us', $text, $matches);
        $length = 0;
        foreach ($matches[0] as $char) {
            $length += self::codePoint($char) > 0xFFFF ? 2 : 1;
        }

        return $length;
    }

    private static function codePoint(string $char): int
    {
        $bytes = array_map('ord', str_split($char));
        $count = count($bytes);
        if ($count === 1) {
            return $bytes[0];
        }
        if ($count === 2) {
            return (($bytes[0] & 0x1F) << 6) | ($bytes[1] & 0x3F);
        }
        if ($count === 3) {
            return (($bytes[0] & 0x0F) << 12) | (($bytes[1] & 0x3F) << 6) | ($bytes[2] & 0x3F);
        }
        if ($count === 4) {
            return (($bytes[0] & 0x07) << 18) | (($bytes[1] & 0x3F) << 12) | (($bytes[2] & 0x3F) << 6) | ($bytes[3] & 0x3F);
        }

        return 0;
    }
}

