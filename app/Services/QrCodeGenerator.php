<?php

declare(strict_types=1);

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * SVG QR codes: pure PHP (no GD/Imagick), and they print sharp at any label size.
 */
final class QrCodeGenerator
{
    public function svg(string $content, int $size = 240): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size, margin: 2), new SvgImageBackEnd));

        // Drop the XML prolog so the SVG can be inlined in HTML.
        return trim((string) preg_replace('/^<\?xml[^>]*\?>/', '', $writer->writeString($content)));
    }
}
