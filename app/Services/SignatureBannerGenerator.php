<?php

namespace App\Services;

use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SignatureBannerGenerator
{
    public function needsRepair(User $user): bool
    {
        $path = storage_path('app/public/firmas/' . $user->nick . '.png');

        if (! is_file($path) || ! is_readable($path)) {
            return true;
        }

        // Las firmas normales son PNG de 500 x 100. También detectamos archivos
        // vacíos o incompletos que el navegador no puede mostrar.
        $dimensions = @getimagesize($path);

        return $dimensions === false
            || $dimensions[0] !== 500
            || $dimensions[1] !== 100
            || $dimensions[2] !== IMAGETYPE_PNG;
    }

    public function repairIfMissing(User $user): void
    {
        if (! $this->needsRepair($user)) {
            return;
        }

        // La vista es pública: si hay un problema de permisos, no debemos intentar
        // generar la misma firma en cada visita y saturar el servidor.
        try {
            if (! Cache::add('signature:repair:' . $user->getKey(), true, now()->addMinutes(15))) {
                return;
            }

            $this->generate($user);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

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
        $outputDirectory = dirname($outputPath);

        // La carpeta de firmas puede no existir todavía en una instalación nueva
        // o en un entorno local. La creación de una firma nunca debe depender de
        // que esa carpeta se haya creado manualmente previamente.
        if (! is_dir($outputDirectory)
            && ! @mkdir($outputDirectory, 0775, true)
            && ! is_dir($outputDirectory)) {
            throw new Exception("No se pudo crear el directorio de firmas: {$outputDirectory}");
        }

        // Escribimos primero en un archivo temporal. Así, una regeneración
        // interrumpida no deja la firma anterior corrupta ni a medio escribir.
        $temporaryPath = tempnam($outputDirectory, '.firma-');
        if ($temporaryPath === false) {
            throw new Exception("No se pudo preparar el archivo de firma en: {$outputDirectory}");
        }

        try {
            if (! @imagepng($image, $temporaryPath)
                || ! @chmod($temporaryPath, 0644)
                || ! @rename($temporaryPath, $outputPath)) {
                throw new Exception("No se pudo guardar la firma generada: {$outputPath}");
            }
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
            imagedestroy($image);
            imagedestroy($textLayer);
            imagedestroy($texture);
        }

        $user->forceFill([
            'firma' => $user->getSignatureUrl(),
        ])->saveQuietly();
        
        return $fileName;
    }
}