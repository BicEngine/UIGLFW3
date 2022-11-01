<?php

declare(strict_types=1);

namespace Bic\UI\GLFW3\Internal;

use Bic\Image\Compression;
use Bic\Image\ConverterInterface;
use Bic\Image\ImageInterface;
use Bic\Image\PixelFormat;
use FFI\CData;

/**
 * @internal This is an internal library class, please do not use it in your code.
 * @psalm-internal Bic\UI\GLFW3
 */
final class ImageLoader
{
    /**
     * @var \WeakMap<ImageInterface, CData>
     */
    private readonly \WeakMap $images;

    /**
     * @param object $ffi
     * @param ConverterInterface $converter
     */
    public function __construct(
        private readonly object $ffi,
        private readonly ConverterInterface $converter,
    ) {
        $this->images = new \WeakMap();
    }

    /**
     * @param ImageInterface $image
     *
     * @return CData
     */
    public function load(ImageInterface $image): CData
    {
        if (isset($this->images[$image])) {
            return $this->images[$image];
        }

        return $this->images[$image] = $this->create($image);
    }

    /**
     * @param ImageInterface $image
     *
     * @return CData
     */
    private function create(ImageInterface $image): CData
    {
        if ($image->getCompression() !== Compression::NONE) {
            $compression = $image->getCompression();
            $message = \sprintf('Icons cannot be compressed, but %s given', $compression->getName());

            throw new \InvalidArgumentException($message);
        }

        $converted = $this->converter->convert($image, PixelFormat::R8G8B8A8);

        $struct = $this->ffi->new('GLFWimage');
        $struct->width = $converted->getWidth();
        $struct->height = $converted->getHeight();

        $size = $converted->getBytes();
        $struct->pixels = \FFI::new("uint8_t[$size]", false);
        \FFI::memcpy($struct->pixels, $converted->getContents(), $size);

        return $struct;
    }
}
