<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3\Internal;

use Bic\UI\Window\Position;
use FFI\CData;

/**
 * @internal This is an internal library class, please do not use it in your code.
 * @psalm-internal Bic\UI\GLFW3
 */
final class GLFW3Position extends Position
{
    public function __construct(
        private readonly object $ffi,
        private readonly CData $window,
        int $x = 0,
        int $y = 0,
    ) {
        parent::__construct($x, $y);
    }

    /**
     * @return void
     */
    private function move(): void
    {
        $this->ffi->glfwSetWindowPos($this->window, $this->x, $this->y);
    }

    /**
     * {@inheritDoc}
     */
    public function setX(int $x): void
    {
        parent::setX($x);

        $this->move();
    }

    /**
     * {@inheritDoc}
     */
    public function setY(int $y): void
    {
        parent::setY($y);

        $this->move();
    }
}
