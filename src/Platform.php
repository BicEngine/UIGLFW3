<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3;

/**
 * @internal This is an internal library class, please do not use it in your code.
 * @psalm-internal Bic\UI\GLFW3
 */
enum Platform
{
    case WIN32;
    case COCOA;
    case X11;
    case WAYLAND;

    /**
     * @return static
     */
    public static function current(): self
    {
        return match (true) {
            \PHP_OS_FAMILY === 'Windows' => self::WIN32,
            \PHP_OS_FAMILY === 'Darwin' => self::COCOA,
            isset($_SERVER['DISPLAY']) => self::X11,
            isset($_SERVER['WAYLAND_DISPLAY']) => self::WAYLAND,
            default => throw new \RuntimeException('Cannot detect current display server'),
        };
    }
}
