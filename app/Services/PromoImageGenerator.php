<?php

namespace App\Services;

use App\Models\Promo;
use Exception;

class PromoImageGenerator
{
    public function ensure(int $promoId): Promo
    {
        $existing = Promo::find($promoId);

        if ($existing && file_exists(storage_path('app/public/' . $existing->image))) {
            return $existing;
        }

        return $this->generate($promoId);
    }

    public function generate(int $promoId): Promo
    {
        $basePath = resource_path('images/promos/base_promo.png');
        $fontPath = resource_path('fonts/PuristaBold.otf');

        if (! file_exists($basePath)) {
            throw new Exception("No existe la plantilla de promo: {$basePath}");
        }

        if (! file_exists($fontPath)) {
            throw new Exception("No existe la fuente: {$fontPath}");
        }

        $image = imagecreatefrompng($basePath);

        imagealphablending($image, true);
        imagesavealpha($image, true);

        $text = (string) $promoId;

        $fontSize = 22.78;
        $offsetX = 15;
        $offsetY = 30;

        $color = imagecolorallocate($image, 255, 255, 255);

        $box = imagettfbbox($fontSize, 0, $fontPath, $text);

        $textWidth = abs($box[4] - $box[0]);
        $textHeight = abs($box[5] - $box[1]);

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        $x = (int) (($imageWidth - $textWidth) / 2) + $offsetX;
        $y = (int) (($imageHeight + $textHeight) / 2) + $offsetY;

        imagettftext(
            $image,
            $fontSize,
            0,
            $x,
            $y,
            $color,
            $fontPath,
            $text
        );

        $fileName = 'promos/' . $promoId . '.png';
        $outputPath = storage_path('app/public/' . $fileName);

        if (! file_exists(dirname($outputPath))) {
            mkdir(dirname($outputPath), 0775, true);
        }

        imagepng($image, $outputPath);

        return Promo::updateOrCreate(
            ['id' => $promoId],
            ['image' => $fileName]
        );
    }
}