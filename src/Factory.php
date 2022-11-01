<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3;

use Bic\Image\Converter;
use Bic\Image\ConverterInterface;
use Bic\Image\Exception\CompressionException;
use Bic\Image\ImageInterface;
use Bic\UI\EventInterface;
use Bic\UI\FactoryInterface;
use Bic\UI\GLFW3\Internal\CursorLoader;
use Bic\UI\GLFW3\Internal\ImageLoader;
use Bic\UI\GLFW3\GLFW3Window;
use Bic\UI\ManagerInterface;
use Bic\UI\Window\CreateInfo;
use Bic\UI\Window\Mode;
use Bic\UI\Window\WindowInterface;
use FFI\CData;
use FFI\Env\Runtime;

final class Factory implements FactoryInterface, ManagerInterface, \IteratorAggregate
{
    /**
     * @var \FFI
     */
    private readonly \FFI $ffi;

    /**
     * @var \SplObjectStorage<WindowInterface>
     */
    private readonly \SplObjectStorage $windows;

    /**
     * @var \SplQueue<EventInterface>
     */
    private readonly \SplQueue $events;

    /**
     * @var ImageLoader
     */
    private readonly ImageLoader $imageLoader;

    /**
     * @var CursorLoader
     */
    private readonly CursorLoader $cursorLoader;

    /**
     * @psalm-taint-sink file $library
     * @param non-empty-string $library
     * @param ConverterInterface $converter
     */
    public function __construct(
        private readonly string $library,
        ConverterInterface $converter = new Converter(),
    ) {
        Runtime::assertAvailable();

        $headers = \file_get_contents(__DIR__ . '/../resources/glfw-3.0.min.h');
        $this->ffi = \FFI::cdef($headers, $this->library);

        $this->events = new \SplQueue();
        $this->windows = new \SplObjectStorage();

        $this->imageLoader = new ImageLoader($this->ffi, $converter);
        $this->cursorLoader = new CursorLoader($this->ffi, $this->imageLoader);

        $this->assertVersion();

        $this->ffi->glfwInit();
    }

    /**
     * @return void
     */
    private function assertVersion(): void
    {
        $this->ffi->glfwGetVersion(
            \FFI::addr($major = \FFI::new('int')),
            \FFI::addr($minor = \FFI::new('int')),
            \FFI::addr($patch = \FFI::new('int')),
        );

        $version = \sprintf('%d.%d.%d', $major->cdata, $minor->cdata, $patch->cdata);

        if (\version_compare($version, '3.2.0') < 0) {
            throw new \RuntimeException(\sprintf('GLFW >= 3.2.0 required, but %s loaded', $version));
        }
    }

    /**
     * {@inheritDoc}
     */
    public function create(CreateInfo $info = new CreateInfo()): GLFW3Window
    {
        $window = $this->instance($info);

        $window->setIcon($info->icon);
        $window->setCursor($info->cursor);

        $this->windows->attach($window);

        return $window;
    }

    /**
     * @param CreateInfo $info
     *
     * @return GLFW3Window
     * @throws CompressionException
     */
    private function instance(CreateInfo $info): GLFW3Window
    {
        // #define GLFW_RESIZABLE 0x00020003
        $this->ffi->glfwWindowHint(0x00020003, (int)$info->resizable);
        // #define GLFW_VISIBLE 0x00020004
        $this->ffi->glfwWindowHint(0x00020004, 0);

        $window = $this->ffi->glfwCreateWindow(
            $info->size->width,
            $info->size->height,
            $info->title,
            $this->getMonitor($info),
            null,
        );

        if ($info->position === null) {
            $this->toCenter($window);
        }

        if ($info->mode !== Mode::HIDDEN) {
            $this->ffi->glfwShowWindow($window);
        }

        return new GLFW3Window(
            $this->ffi,
            $window,
            $info,
            $this->imageLoader,
            $this->cursorLoader,
            $this->events,
            $this->detach(...),
        );
    }

    /**
     * @param CData $window
     *
     * @return void
     */
    private function toCenter(CData $window): void
    {
        $display = $this->ffi->glfwGetPrimaryMonitor();

        // Display Mode
        $mode = $this->ffi->glfwGetVideoMode($display);

        // Display Position
        [$left, $top] = [\FFI::new('int'), \FFI::new('int')];
        $this->ffi->glfwGetMonitorPos($display, \FFI::addr($left), \FFI::addr($top));

        // Window Size
        [$width, $height] = [\FFI::new('int'), \FFI::new('int')];
        $this->ffi->glfwGetWindowSize($window, \FFI::addr($width), \FFI::addr($height));

        $this->ffi->glfwSetWindowPos(
            $window,
            (int)(($mode->width - $width->cdata) / 2) + $left->cdata,
            (int)(($mode->height - $height->cdata) / 2) + $top->cdata,
        );
    }

    /**
     * @param CreateInfo $info
     *
     * @return CData|null
     */
    private function getMonitor(CreateInfo $info): ?CData
    {
        if ($info->mode === Mode::FULLSCREEN) {
            return $this->ffi->glfwGetPrimaryMonitor();
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function detach(WindowInterface $window): void
    {
        $this->windows->detach($window);
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
        if (\Fiber::getCurrent()) {
            while ($this->windows->count() > 0) {
                $this->ffi->glfwPollEvents();

                while ($this->events->count() > 0) {
                    \Fiber::suspend($this->events->shift());
                }

                \Fiber::suspend(); // NOOP
            }
        }

        while ($this->windows->count() > 0) {
            $this->ffi->glfwPollEvents();
            while ($this->events->count() > 0) {
                $this->events->shift();
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): \Traversable
    {
        yield from $this->windows;
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return $this->windows->count();
    }
}
