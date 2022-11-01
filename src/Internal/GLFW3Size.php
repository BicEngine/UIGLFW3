<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3\Internal;

use Bic\UI\Window\Size;
use FFI\CData;

/**
 * @internal This is an internal library class, please do not use it in your code.
 * @psalm-internal Bic\UI\GLFW3
 */
final class GLFW3Size extends Size
{
    public function __construct(
        private readonly object $ffi,
        private readonly CData $window,
        int $width,
        int $height
    ) {
        parent::__construct($width, $height);
    }

    /**
     * @return void
     */
    private function resize(): void
    {
        $this->ffi->glfwSetWindowSize($this->window, $this->width, $this->height);
    }

    /**
     * {@inheritDoc}
     */
    public function setWidth(int $width): void
    {
        parent::setWidth($width);

        $this->resize();
    }

    /**
     * {@inheritDoc}
     */
    public function setHeight(int $height): void
    {
        parent::setHeight($height);

        $this->resize();
    }
}
