<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3;

use Bic\Image\Converter;
use Bic\Image\ConverterInterface;
use Bic\Image\Exception\CompressionException;
use Bic\UI\EventInterface;
use Bic\UI\FactoryInterface;
use Bic\UI\GLFW3\Internal\CursorLoader;
use Bic\UI\GLFW3\Internal\ImageLoader;
use Bic\UI\GLFW3\Internal\GLFW3Window;
use Bic\UI\ManagerInterface;
use Bic\UI\Window\CreateInfo;
use Bic\UI\Window\Event\WindowCreateEvent;
use Bic\UI\Window\Handle\AppleHandle;
use Bic\UI\Window\Handle\WaylandHandle;
use Bic\UI\Window\Handle\Win32Handle;
use Bic\UI\Window\Handle\XLibHandle;
use Bic\UI\Window\HandleInterface;
use Bic\UI\Window\Mode;
use Bic\UI\Window\WindowInterface;
use FFI\CData;
use FFI\Env\Runtime;
use Psr\EventDispatcher\EventDispatcherInterface;

final class Factory implements FactoryInterface, ManagerInterface, \IteratorAggregate
{
    /**
     * @var \FFI
     */
    public readonly \FFI $ffi;

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
     * @var Platform
     */
    private readonly Platform $platform;

    /**
     * @psalm-taint-sink file $library
     *
     * @param non-empty-string $library
     * @param Platform|null $platform
     * @param ConverterInterface $converter
     */
    public function __construct(
        private readonly string $library,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        Platform $platform = null,
        ConverterInterface $converter = new Converter(),
    ) {
        Runtime::assertAvailable();

        $this->platform = $platform ?? Platform::current();

        $this->ffi = \FFI::cdef($this->getHeaders(), $this->library);

        $this->events = new \SplQueue();
        $this->windows = new \SplObjectStorage();

        $this->imageLoader = new ImageLoader($this->ffi, $converter);
        $this->cursorLoader = new CursorLoader($this->ffi, $this->imageLoader);

        $this->assertVersion();

        $this->ffi->glfwInit();
    }

    /**
     * @param CData $window
     *
     * @return HandleInterface
     */
    private function getHandle(CData $window): HandleInterface
    {
        return match ($this->platform) {
            Platform::WIN32 => new Win32Handle($this->ffi->glfwGetWin32Window($window)),
            Platform::COCOA => new AppleHandle($this->ffi->glfwGetCocoaWindow($window)),
            Platform::WAYLAND => new WaylandHandle($this->ffi->glfwGetWaylandWindow($window)),
            Platform::X11 => new XLibHandle($this->ffi->glfwGetX11Window($window)),
        };
    }

    /**
     * @return non-empty-string
     */
    private function getHeaders(): string
    {
        $headers = \file_get_contents(__DIR__ . '/../resources/glfw-3.2.min.h');

        return $headers . "\n" . match ($this->platform) {
            Platform::WIN32 => <<<'CDATA'
                void* glfwGetWin32Window(GLFWwindow* window);
                void* glfwGetWGLContext(GLFWwindow* window);
            CDATA,
            Platform::COCOA => <<<'CDATA'
                struct id* glfwGetCocoaWindow(GLFWwindow* window);
            CDATA,
            Platform::WAYLAND => <<<'CDATA'
                struct wl_surface* glfwGetWaylandWindow(GLFWwindow* window);
            CDATA,
            Platform::X11 => <<<'CDATA'
                typedef unsigned long XID;
                typedef XID Window;
                Window glfwGetX11Window(GLFWwindow* window);
            CDATA,
        };
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

        $this->dispatcher?->dispatch(new WindowCreateEvent($window));

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

        // #define GLFW_CONTEXT_VERSION_MAJOR 0x00022002
        $this->ffi->glfwWindowHint(0x00022002, 4);
        // #define GLFW_CONTEXT_VERSION_MINOR 0x00022003
        $this->ffi->glfwWindowHint(0x00022003, 6);

        // #define GLFW_OPENGL_PROFILE 0x00022008
        // #define GLFW_OPENGL_CORE_PROFILE 0x00032001
        $this->ffi->glfwWindowHint(0x00022008, 0x00032001);

        // #define GLFW_OPENGL_FORWARD_COMPAT 0x00022006
        $this->ffi->glfwWindowHint(0x00022006, 1);

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
            $this->getHandle($window),
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
        while ($this->windows->count() > 0) {
            $this->ffi->glfwPollEvents();

            while ($this->events->count() > 0) {
                $event = $this->events->shift();
                \Fiber::getCurrent() && \Fiber::suspend($event);
                $this->dispatcher?->dispatch($event);
            }

            \Fiber::getCurrent() && \Fiber::suspend(); // NOOP
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
