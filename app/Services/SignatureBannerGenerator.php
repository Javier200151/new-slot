<?php

namespace App\Services;

use App\Models\User;
use Exception;

class SignatureBannerGenerator
{
    public function generate(User $user): string
    {
        $basePath = resource_path('images/signatures/base_banner.png');
        $texturePath = resource_path('images/signatures/banner_textura.png');
        $fontPath = resource_path('fonts/boston.ttf');

        if (! file_exists($basePath)) {
            throw new Exception("No existe el banner base: {$basePath}");
        }

        if (! file_exists($texturePath)) {
            throw new Exception("No existe la textura: {$texturePath}");
        }

        if (! file_exists($fontPath)) {
            throw new Exception("No existe la fuente: {$fontPath}");
        }

        $image = imagecreatefrompng($basePath);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $text = strtoupper($user->nick);

        $fontSize = 48;
        $offsetX = 0;
        $offsetY = -2;

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        $box = imagettfbbox($fontSize, 0, $fontPath, $text);

        $textWidth = abs($box[4] - $box[0]);
        $textHeight = abs($box[5] - $box[1]);

        $x = (int) (($imageWidth - $textWidth) / 2) + $offsetX;
        $y = (int) (($imageHeight + $textHeight) / 2) + $offsetY;

        /*
         * 1. Capa de texto transparente
         */
        $textLayer = imagecreatetruecolor($imageWidth, $imageHeight);
        imagealphablending($textLayer, false);
        imagesavealpha($textLayer, true);

        $transparent = imagecolorallocatealpha($textLayer, 0, 0, 0, 127);
        imagefill($textLayer, 0, 0, $transparent);

        $white = imagecolorallocate($textLayer, 230, 230, 230);

        imagettftext(
            $textLayer,
            $fontSize,
            0,
            $x,
            $y,
            $white,
            $fontPath,
            $text
        );

        /*
         * 2. Poner el texto blanco sobre el banner
         */
        imagecopy($image, $textLayer, 0, 0, 0, 0, $imageWidth, $imageHeight);

        /*
         * 3. Cargar textura y ajustarla al tamaño del banner
         */
        $texture = imagecreatefrompng($texturePath);
        imagealphablending($texture, true);
        imagesavealpha($texture, true);

        if (imagesx($texture) !== $imageWidth || imagesy($texture) !== $imageHeight) {
            $resizedTexture = imagecreatetruecolor($imageWidth, $imageHeight);
            imagealphablending($resizedTexture, false);
            imagesavealpha($resizedTexture, true);

            imagefill($resizedTexture, 0, 0, imagecolorallocatealpha($resizedTexture, 0, 0, 0, 127));

            imagecopyresampled(
                $resizedTexture,
                $texture,
                0,
                0,
                0,
                0,
                $imageWidth,
                $imageHeight,
                imagesx($texture),
                imagesy($texture)
            );

            imagedestroy($texture);
            $texture = $resizedTexture;
        }

        /*
         * 4. Aplicar textura SOLO donde existe texto
         */
        for ($px = 0; $px < $imageWidth; $px++) {
            for ($py = 0; $py < $imageHeight; $py++) {
                $textPixel = imagecolorat($textLayer, $px, $py);
                $textAlpha = ($textPixel & 0x7F000000) >> 24;

                // Solo aplicar textura donde la capa de texto NO es transparente
                if ($textAlpha < 127) {
                    $texturePixel = imagecolorat($texture, $px, $py);

                    $r = ($texturePixel >> 16) & 0xFF;
                    $g = ($texturePixel >> 8) & 0xFF;
                    $b = $texturePixel & 0xFF;
                    $a = ($texturePixel & 0x7F000000) >> 24;

                    // Si la textura tiene píxeles visibles, sustituye el color del texto
                    if ($a < 127) {
                        $newColor = imagecolorallocatealpha($image, $r, $g, $b, $a);
                        imagesetpixel($image, $px, $py, $newColor);
                    }
                }
            }
        }

        $fileName = 'firmas/' . $user->nick . '.png';
        $outputPath = storage_path('app/public/' . $fileName);

        imagepng($image, $outputPath);

        //imagedestroy($image);
        //imagedestroy($textLayer);
        //imagedestroy($texture);

        $user->forceFill([
            'firma' => $user->getSignatureUrl(),
        ])->saveQuietly();
        
        return $fileName;
    }
}