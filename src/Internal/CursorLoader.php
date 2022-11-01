<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3\Internal;

use Bic\UI\Window\CursorInterface;
use FFI\CData;

/**
 * @internal This is an internal library class, please do not use it in your code.
 * @psalm-internal Bic\UI\GLFW3
 */
final class CursorLoader
{
    /**
     * @var \WeakMap<CursorInterface, object{pointer: CData}>
     */
    private readonly \WeakMap $cursors;

    /**
     * @param object $ffi
     * @param ImageLoader $loader
     */
    public function __construct(
        private readonly object $ffi,
        private readonly ImageLoader $loader,
    ) {
        $this->cursors = new \WeakMap();
    }

    /**
     * @param CursorInterface $cursor
     *
     * @return CData
     */
    private function create(CursorInterface $cursor): CData
    {
        $image = $this->loader->load($cursor->getIcon());

        return $this->ffi->glfwCreateCursor(\FFI::addr($image), $cursor->getX(), $cursor->getY());
    }

    /**
     * @param CursorInterface $cursor
     *
     * @return CData
     */
    public function load(CursorInterface $cursor): CData
    {
        if (!isset($this->cursors[$cursor])) {
            $cdata = $this->create($cursor);

            $this->cursors[$cursor] = new class($this->ffi, $cdata)
            {
                public function __construct(
                    private readonly object $ffi,
                    public readonly CData $pointer,
                ) {
                }

                public function __destruct()
                {
                    $this->ffi->glfwDestroyCursor($this->pointer);
                }
            };
        }

        return $this->cursors[$cursor]->pointer;
    }
}
