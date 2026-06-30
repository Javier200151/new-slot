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
        $color = imagecolorallocate($image, 255, 255, 255);

        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);

        $textWidth = abs($bbox[2] - $bbox[0]);

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        // Ajuste para colocar el número en la esquina inferior derecha
        $marginRight = 12;
        $marginBottom = 8;

        $x = $imageWidth - $textWidth - $marginRight;
        $y = $imageHeight - $marginBottom;

        imagettftext(
            $image,
            $fontSize,
            0,
            (int) round($x),
            (int) round($y),
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
        imagedestroy($image);

        return Promo::updateOrCreate(
            ['id' => $promoId],
            ['image' => $fileName]
        );
    }
}