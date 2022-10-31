<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3\Internal;

use Bic\UI\Keyboard\Key;
use Bic\UI\Keyboard\KeyInterface;
use Bic\UI\Keyboard\Modifier;
use Bic\UI\Keyboard\UserKey;

/**
 * @internal This is an internal library class, please do not use it in your code.
 * @psalm-internal Bic\UI\GLFW3
 */
final class Keyboard
{
    private const KEY_MOD_MAPPINGS = [
        // GLFW_MOD_SHIFT
        0x0001 => Modifier::SHIFT,
        // GLFW_MOD_CONTROL
        0x0002 => Modifier::CONTROL,
        // GLFW_MOD_ALT
        0x0004 => Modifier::ALT,
        // GLFW_MOD_SUPER
        0x0008 => Modifier::SUPER,
        // GLFW_MOD_CAPS_LOCK
        0x0010 => Modifier::CAPS_LOCK,
        // GLFW_MOD_NUM_LOCK
        0x0020 => Modifier::NUM_LOCK,
    ];

    /**
     * @param int $flags
     *
     * @return int
     */
    public static function getModifiers(int $flags): int
    {
        $result = 0;

        foreach (self::KEY_MOD_MAPPINGS as $glfw => $internal) {
            if (($flags & $glfw) === $glfw) {
                $result |= $internal;
            }
        }

        return $result;
    }

    /**
     * @param int $keycode
     *
     * @return KeyInterface
     */
    public static function getKey(int $keycode): KeyInterface
    {
        return match ($keycode) {
            // GLFW_KEY_SPACE
            32 => Key::SPACE,
            // GLFW_KEY_APOSTROPHE
            39 => Key::APOSTROPHE,
            // GLFW_KEY_COMMA
            44 => Key::COMMA,
            // GLFW_KEY_MINUS
            45 => Key::MINUS,
            // GLFW_KEY_PERIOD
            46 => Key::PERIOD,
            // GLFW_KEY_SLASH
            47 => Key::SLASH,
            // GLFW_KEY_0
            48 => Key::KEY_0,
            // GLFW_KEY_1
            49 => Key::KEY_1,
            // GLFW_KEY_2
            50 => Key::KEY_2,
            // GLFW_KEY_3
            51 => Key::KEY_3,
            // GLFW_KEY_4
            52 => Key::KEY_4,
            // GLFW_KEY_5
            53 => Key::KEY_5,
            // GLFW_KEY_6
            54 => Key::KEY_6,
            // GLFW_KEY_7
            55 => Key::KEY_7,
            // GLFW_KEY_8
            56 => Key::KEY_8,
            // GLFW_KEY_9
            57 => Key::KEY_9,
            // GLFW_KEY_SEMICOLON
            59 => Key::SEMICOLON,
            // GLFW_KEY_EQUAL
            61 => Key::EQUAL,
            // GLFW_KEY_A
            65 => Key::A,
            // GLFW_KEY_B
            66 => Key::B,
            // GLFW_KEY_C
            67 => Key::C,
            // GLFW_KEY_D
            68 => Key::D,
            // GLFW_KEY_E
            69 => Key::E,
            // GLFW_KEY_F
            70 => Key::F,
            // GLFW_KEY_G
            71 => Key::G,
            // GLFW_KEY_H
            72 => Key::H,
            // GLFW_KEY_I
            73 => Key::I,
            // GLFW_KEY_J
            74 => Key::J,
            // GLFW_KEY_K
            75 => Key::K,
            // GLFW_KEY_L
            76 => Key::L,
            // GLFW_KEY_M
            77 => Key::M,
            // GLFW_KEY_N
            78 => Key::N,
            // GLFW_KEY_O
            79 => Key::O,
            // GLFW_KEY_P
            80 => Key::P,
            // GLFW_KEY_Q
            81 => Key::Q,
            // GLFW_KEY_R
            82 => Key::R,
            // GLFW_KEY_S
            83 => Key::S,
            // GLFW_KEY_T
            84 => Key::T,
            // GLFW_KEY_U
            85 => Key::U,
            // GLFW_KEY_V
            86 => Key::V,
            // GLFW_KEY_W
            87 => Key::W,
            // GLFW_KEY_X
            88 => Key::X,
            // GLFW_KEY_Y
            89 => Key::Y,
            // GLFW_KEY_Z
            90 => Key::Z,
            // GLFW_KEY_LEFT_BRACKET
            91 => Key::LEFT_BRACKET,
            // GLFW_KEY_BACKSLASH
            92 => Key::BACKSLASH,
            // GLFW_KEY_RIGHT_BRACKET
            93 => Key::RIGHT_BRACKET,
            // GLFW_KEY_GRAVE_ACCENT
            96 => Key::GRAVE_ACCENT,
            // GLFW_KEY_WORLD_1 = 161
            // GLFW_KEY_WORLD_2 = 162
            // GLFW_KEY_ESCAPE
            256 => Key::ESCAPE,
            // GLFW_KEY_ENTER
            257 => Key::ENTER,
            // GLFW_KEY_TAB
            258 => Key::TAB,
            // GLFW_KEY_BACKSPACE
            259 => Key::BACKSPACE,
            // GLFW_KEY_INSERT
            260 => Key::INSERT,
            // GLFW_KEY_DELETE
            261 => Key::DELETE,
            // GLFW_KEY_RIGHT
            262 => Key::RIGHT,
            // GLFW_KEY_LEFT
            263 => Key::LEFT,
            // GLFW_KEY_DOWN
            264 => Key::DOWN,
            // GLFW_KEY_UP
            265 => Key::UP,
            // GLFW_KEY_PAGE_UP
            266 => Key::PAGE_UP,
            // GLFW_KEY_PAGE_DOWN
            267 => Key::PAGE_DOWN,
            // GLFW_KEY_HOME
            268 => Key::HOME,
            // GLFW_KEY_END
            269 => Key::END,
            // GLFW_KEY_CAPS_LOCK
            280 => Key::CAPS_LOCK,
            // GLFW_KEY_SCROLL_LOCK
            281 => Key::SCROLL_LOCK,
            // GLFW_KEY_NUM_LOCK
            282 => Key::NUM_LOCK,
            // GLFW_KEY_PRINT_SCREEN
            283 => Key::PRINT_SCREEN,
            // GLFW_KEY_PAUSE
            284 => Key::PAUSE,
            // GLFW_KEY_F1
            290 => Key::F1,
            // GLFW_KEY_F2
            291 => Key::F2,
            // GLFW_KEY_F3
            292 => Key::F3,
            // GLFW_KEY_F4
            293 => Key::F4,
            // GLFW_KEY_F5
            294 => Key::F5,
            // GLFW_KEY_F6
            295 => Key::F6,
            // GLFW_KEY_F7
            296 => Key::F7,
            // GLFW_KEY_F8
            297 => Key::F8,
            // GLFW_KEY_F9
            298 => Key::F9,
            // GLFW_KEY_F10
            299 => Key::F10,
            // GLFW_KEY_F11
            300 => Key::F11,
            // GLFW_KEY_F12
            301 => Key::F12,
            // GLFW_KEY_F13
            302 => Key::F13,
            // GLFW_KEY_F14
            303 => Key::F14,
            // GLFW_KEY_F15
            304 => Key::F15,
            // GLFW_KEY_F16 = 305,
            // GLFW_KEY_F17 = 306,
            // GLFW_KEY_F18 = 307,
            // GLFW_KEY_F19 = 308,
            // GLFW_KEY_F20 = 309,
            // GLFW_KEY_F21 = 310,
            // GLFW_KEY_F22 = 311,
            // GLFW_KEY_F23 = 312,
            // GLFW_KEY_F24 = 313,
            // GLFW_KEY_F25 = 314,
            // GLFW_KEY_KP_0
            320 => Key::KP_0,
            // GLFW_KEY_KP_1
            321 => Key::KP_1,
            // GLFW_KEY_KP_2
            322 => Key::KP_2,
            // GLFW_KEY_KP_3
            323 => Key::KP_3,
            // GLFW_KEY_KP_4
            324 => Key::KP_4,
            // GLFW_KEY_KP_5
            325 => Key::KP_5,
            // GLFW_KEY_KP_6
            326 => Key::KP_6,
            // GLFW_KEY_KP_7
            327 => Key::KP_7,
            // GLFW_KEY_KP_8
            328 => Key::KP_8,
            // GLFW_KEY_KP_9
            329 => Key::KP_9,
            // GLFW_KEY_KP_DECIMAL
            330 => Key::KP_DECIMAL,
            // GLFW_KEY_KP_DIVIDE
            331 => Key::KP_DIVIDE,
            // GLFW_KEY_KP_MULTIPLY
            332 => Key::KP_MULTIPLY,
            // GLFW_KEY_KP_SUBTRACT
            333 => Key::KP_SUBTRACT,
            // GLFW_KEY_KP_ADD
            334 => Key::KP_ADD,
            // GLFW_KEY_KP_ENTER
            335 => Key::KP_ENTER,
            // GLFW_KEY_KP_EQUAL
            336 => Key::KP_EQUAL,
            // GLFW_KEY_LEFT_SHIFT
            340 => Key::LEFT_SHIFT,
            // GLFW_KEY_LEFT_CONTROL
            341 => Key::LEFT_CONTROL,
            // GLFW_KEY_LEFT_ALT
            342 => Key::LEFT_ALT,
            // GLFW_KEY_LEFT_SUPER
            343 => Key::LEFT_SUPER,
            // GLFW_KEY_RIGHT_SHIFT
            344 => Key::RIGHT_SHIFT,
            // GLFW_KEY_RIGHT_CONTROL
            345 => Key::RIGHT_CONTROL,
            // GLFW_KEY_RIGHT_ALT
            346 => Key::RIGHT_ALT,
            // GLFW_KEY_RIGHT_SUPER
            347 => Key::RIGHT_SUPER,
            // GLFW_KEY_MENU
            348 => Key::MENU,
            //
            default => UserKey::create($keycode),
        };
    }
}
