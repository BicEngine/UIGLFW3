<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3\Internal;

use Bic\UI\EventInterface;
use Bic\UI\Keyboard\Event\KeyDownEvent;
use Bic\UI\Keyboard\Event\KeyUpEvent;
use Bic\UI\Mouse\Button;
use Bic\UI\Mouse\Event\MouseDownEvent;
use Bic\UI\Mouse\Event\MouseMoveEvent;
use Bic\UI\Mouse\Event\MouseUpEvent;
use Bic\UI\Mouse\Event\MouseWheelEvent;
use Bic\UI\Mouse\UserButton;
use Bic\UI\Mouse\Wheel;
use Bic\UI\Window\CursorInterface;
use Bic\UI\Window\IconInterface;
use Bic\UI\Window\CreateInfo;
use Bic\UI\Window\Event\WindowBlurEvent;
use Bic\UI\Window\Event\WindowCloseEvent;
use Bic\UI\Window\Event\WindowFocusEvent;
use Bic\UI\Window\Event\WindowHideEvent;
use Bic\UI\Window\Event\WindowMoveEvent;
use Bic\UI\Window\Event\WindowResizeEvent;
use Bic\UI\Window\Event\WindowShowEvent;
use Bic\UI\Window\HandleInterface;
use Bic\UI\Window\ProvidesPositionInterface;
use Bic\UI\Window\ProvidesSizeInterface;
use Bic\UI\Window\SupportsOpenGLInterface;
use Bic\UI\Window\Window;
use FFI\CData;

/**
 * @internal This is an internal library class, please do not use it in your code.
 * @psalm-internal Bic\UI\GLFW3
 */
final class GLFW3Window extends Window implements SupportsOpenGLInterface
{
    /**
     * @var bool
     */
    private bool $closed = false;

    /**
     * @var int
     */
    private int $mouseX = 0;

    /**
     * @var int
     */
    private int $mouseY = 0;

    /**
     * @param object $ffi
     * @param CData $window
     * @param CreateInfo $info
     * @param ImageLoader $imageLoader
     * @param CursorLoader $cursorLoader
     * @param \SplQueue<EventInterface> $events
     * @param \Closure(static):void $detach
     */
    public function __construct(
        private readonly object $ffi,
        private readonly CData $window,
        HandleInterface $handle,
        private readonly CreateInfo $info,
        private readonly ImageLoader $imageLoader,
        private readonly CursorLoader $cursorLoader,
        private readonly \SplQueue $events,
        private readonly \Closure $detach,
    ) {
        $this->ffi->glfwGetWindowPos($this->window, \FFI::addr(
            $x = \FFI::new('int')
        ), \FFI::addr(
            $y = \FFI::new('int')
        ));

        $this->title = $info->title;

        parent::__construct(
            size: new GLFW3Size(
                ffi: $this->ffi,
                window: $this->window,
                width: $info->size->width,
                height: $info->size->height
            ),
            position: new GLFW3Position(
                ffi: $this->ffi,
                window: $this->window,
                x: $this->info->position?->x ?? $x->cdata,
                y: $this->info->position?->y ?? $y->cdata
            ),
            handle: $handle,
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

        $this->ffi->glfwSetCursorPosCallback($this->window, function (CData $_, float $x, float $y) {
            $this->mouseX = (int)$x;
            $this->mouseY = (int)$y;

            $this->events->push(new MouseMoveEvent($this, (int)$x, (int)$y));
        });

        $this->ffi->glfwSetWindowPosCallback($this->window, function (CData $_, int $x, int $y) {
            if ($this->events->count() && $this->events->bottom() instanceof WindowMoveEvent) {
                $this->events->shift();
            }

            $this->events->push(new WindowMoveEvent($this, $x, $y));
        });

        $this->ffi->glfwSetWindowSizeCallback($this->window, function (CData $_, int $w, int $h) {
            if ($this->events->count() && $this->events->bottom() instanceof WindowResizeEvent) {
                $this->events->shift();
            }

            $this->events->push(new WindowResizeEvent($this, $w, $h));
        });
    }

    /**
     * {@inheritDoc}
     */
    public function setTitle(string $title): void
    {
        parent::setTitle($title);

        $this->ffi->glfwSetWindowTitle($this->window, $title);
    }

    public function setIcon(?IconInterface $icon): void
    {
        parent::setIcon($icon);

        if ($icon === null || $icon->count() === 0) {
            $this->ffi->glfwSetWindowIcon($this->window, 0, null);

            return;
        }

        $cdata = $this->ffi->new('GLFWimage[' . $icon->count() . ']');

        foreach ($icon as $i => $image) {
            $cdata[$i] = $this->imageLoader->load($image);
        }

        $this->ffi->glfwSetWindowIcon($this->window, $icon->count(), $cdata);
    }

    /**
     * {@inheritDoc}
     */
    public function setCursor(?CursorInterface $cursor): void
    {
        parent::setCursor($cursor);

        if ($cursor === null) {
            $this->ffi->glfwSetCursor($this->window, null);
        } else {
            $internal = $this->cursorLoader->load($cursor);
            $this->ffi->glfwSetCursor($this->window, $internal);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setSize(ProvidesSizeInterface $size): void
    {
        parent::setSize($size);

        $this->ffi->glfwSetWindowSize($this->window, $size->getWidth(), $size->getHeight());
    }

    /**
     * {@inheritDoc}
     */
    public function setPosition(ProvidesPositionInterface $position): void
    {
        parent::setPosition($position);

        $this->ffi->glfwSetWindowPos($this->window, $position->x, $position->y);
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

            $this->ffi->glfwSetWindowCloseCallback($this->window, null);
            $this->ffi->glfwSetWindowIconifyCallback($this->window, null);
            $this->ffi->glfwSetWindowFocusCallback($this->window, null);
            $this->ffi->glfwSetMouseButtonCallback($this->window, null);
            $this->ffi->glfwSetScrollCallback($this->window, null);
            $this->ffi->glfwSetKeyCallback($this->window, null);
            $this->ffi->glfwSetCursorPosCallback($this->window, null);
            $this->ffi->glfwSetWindowPosCallback($this->window, null);
            $this->ffi->glfwSetWindowSizeCallback($this->window, null);

            $this->ffi->glfwDestroyWindow($this->window);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function glMakeCurrent(): void
    {
        $this->ffi->glfwMakeContextCurrent($this->window);
    }

    /**
     * {@inheritDoc}
     */
    public function glSwapBuffers(): void
    {
        $this->ffi->glfwSwapBuffers($this->window);
    }

    /**
     * @return CData
     */
    public function getCData(): CData
    {
        return $this->window;
    }

    /**
     * {@inheritDoc}
     */
    public function isClosed(): bool
    {
        return $this->closed;
    }
}
