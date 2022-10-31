<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3;

use Bic\UI\EventInterface;
use Bic\UI\GLFW3\Internal\Keyboard;
use Bic\UI\Keyboard\Event\KeyDownEvent;
use Bic\UI\Keyboard\Event\KeyUpEvent;
use Bic\UI\Mouse\Button;
use Bic\UI\Mouse\Event\MouseDownEvent;
use Bic\UI\Mouse\Event\MouseMoveEvent;
use Bic\UI\Mouse\Event\MouseUpEvent;
use Bic\UI\Mouse\Event\MouseWheelEvent;
use Bic\UI\Mouse\UserButton;
use Bic\UI\Mouse\Wheel;
use Bic\UI\Window\Position;
use Bic\UI\Window\CreateInfo;
use Bic\UI\Window\Event\WindowBlurEvent;
use Bic\UI\Window\Event\WindowCloseEvent;
use Bic\UI\Window\Event\WindowFocusEvent;
use Bic\UI\Window\Event\WindowHideEvent;
use Bic\UI\Window\Event\WindowMoveEvent;
use Bic\UI\Window\Event\WindowResizeEvent;
use Bic\UI\Window\Event\WindowShowEvent;
use Bic\UI\Window\HandleInterface;
use Bic\UI\Window\Size;
use Bic\UI\Window\WindowInterface;
use FFI\CData;

final class Window implements WindowInterface
{
    /**
     * @var bool
     */
    private bool $closed = false;

    /**
     * @var string
     */
    private string $title;

    /**
     * @var Position
     */
    private readonly Position $position;

    /**
     * @var int
     */
    private int $mouseX = 0;

    /**
     * @var int
     */
    private int $mouseY = 0;

    /**
     * @var Size
     */
    private readonly Size $size;

    /**
     * @param object $ffi
     * @param CData $window
     * @param CreateInfo $info
     * @param \SplQueue<EventInterface> $events
     * @param \Closure(static):void $detach
     */
    public function __construct(
        private readonly object $ffi,
        private readonly CData $window,
        private readonly CreateInfo $info,
        private readonly \SplQueue $events,
        private readonly \Closure $detach,
    ) {
        $this->ffi->glfwGetWindowPos($this->window, \FFI::addr(
            $x = \FFI::new('int')
        ), \FFI::addr(
            $y = \FFI::new('int')
        ));

        $this->title = $info->title;

        $this->size = new Size(
            $info->size->width,
            $info->size->height,
        );

        $this->position = new Position(
            $this->info->position?->x ?? $x->cdata,
            $this->info->position?->y ?? $y->cdata
        );

        $this->createEventListeners();
    }

    /**
     * @return void
     */
    private function createEventListeners(): void
    {
        $this->ffi->glfwSetWindowCloseCallback($this->window, function (): void {
            if ($this->info->closable) {
                $this->close();
            }
        });

        $this->ffi->glfwSetWindowIconifyCallback($this->window, function (CData $_, int $hide): void {
            if ($hide) {
                $this->events->push(new WindowHideEvent($this));
            } else {
                $this->events->push(new WindowShowEvent($this));
            }
        });

        $this->ffi->glfwSetWindowFocusCallback($this->window, function (CData $_, int $focus) {
            if ($focus) {
                $this->events->push(new WindowFocusEvent($this));
            } else {
                $this->events->push(new WindowBlurEvent($this));
            }
        });

        $this->ffi->glfwSetCursorPosCallback($this->window, function (CData $_, float $x, float $y) {
            $this->mouseX = (int)$x;
            $this->mouseY = (int)$y;
            $this->events->push(new MouseMoveEvent($this, (int)$x, (int)$y));
        });

        $this->ffi->glfwSetMouseButtonCallback($this->window, function (CData $_, int $button, int $action) {
            $button = Button::tryFrom($button) ?? UserButton::create($button);

            if ($action) {
                $this->events->push(new MouseDownEvent($this, $this->mouseX, $this->mouseY, $button));
            } else {
                $this->events->push(new MouseUpEvent($this, $this->mouseX, $this->mouseY, $button));
            }
        });

        $this->ffi->glfwSetScrollCallback($this->window, function (CData $_, float $x, float $y) {
            if ($x !== 0.0) {
                $this->events->push(new MouseWheelEvent($this, $x > 0 ? Wheel::LEFT : Wheel::RIGHT));
            }

            if ($y !== 0.0) {
                $this->events->push(new MouseWheelEvent($this, $y > 0 ? Wheel::UP : Wheel::DOWN));
            }
        });

        $this->ffi->glfwSetKeyCallback($this->window, function (CData $_, int $key, int $code, int $action, int $mods) {
            if ($action === 1) {
                $this->events->push(new KeyDownEvent(
                    $this,
                    Keyboard::getKey($key),
                    Keyboard::getModifiers($mods),
                ));
            } elseif ($action === 0) {
                $this->events->push(new KeyUpEvent(
                    $this,
                    Keyboard::getKey($key),
                    Keyboard::getModifiers($mods),
                ));
            }
        });

        $this->ffi->glfwSetWindowPosCallback($this->window, function (CData $_, int $x, int $y) {
            $this->position->x = $x;
            $this->position->y = $y;

            $this->events->push(new WindowMoveEvent($this, $x, $y));
        });

        $this->ffi->glfwSetWindowSizeCallback($this->window, function (CData $_, int $w, int $h) {
            $this->size->width = $w;
            $this->size->height = $h;

            $this->events->push(new WindowResizeEvent($this, $w, $h));
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * {@inheritDoc}
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->ffi->glfwSetWindowTitle($this->window, $title);
    }

    /**
     * {@inheritDoc}
     */
    public function getSize(): Size
    {
        return $this->size;
    }

    /**
     * {@inheritDoc}
     */
    public function getPosition(): Position
    {
        return $this->position;
    }

    /**
     * {@inheritDoc}
     */
    public function getHandle(): HandleInterface
    {
        throw new \LogicException(__METHOD__ . ' not implemented yet');
    }

    /**
     * {@inheritDoc}
     */
    public function show(): void
    {
        $this->ffi->glfwShowWindow($this->window);
    }

    /**
     * {@inheritDoc}
     */
    public function hide(): void
    {
        $this->ffi->glfwHideWindow($this->window);
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        if (!$this->closed) {
            $this->events->push(new WindowCloseEvent($this));
            ($this->detach)($this);
            $this->ffi->glfwDestroyWindow($this->window);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }
}
