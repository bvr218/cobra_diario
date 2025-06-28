<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; // <--- Importar Facade de Storage
use Exception;
use GdImage; // <--- Import GdImage class para type hinting (PHP 8+)

class ImageConverter extends Controller
{
    // Directorio dentro de storage/app donde se guardarán las imágenes generadas
    const STORAGE_PATH_PREFIX = 'image-converter-cache';

    /**
     * Convierte una imagen de una URL a formato redondo con borde de color.
     * Guarda la imagen resultante en storage y la sirve desde allí en futuras peticiones idénticas.
     *
     * @param Request $request
     * @return Response|JsonResponse
     */
    public function convert(Request $request): Response|JsonResponse
    {
        // --- 1. Validar Entradas ---
        $validator = Validator::make($request->query(), [
            'url' => 'required|url',
            'color' => [
                'required',
                'regex:/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'
            ],
            'border' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $imageUrl = $request->query('url');
        $borderColorHex = $request->query('color');
        $borderWidth = (int) $request->query('border', 5);

        // --- 2. Generar Nombre de Archivo Único Basado en Parámetros ---
        $paramsIdentifier = http_build_query([
            'url' => $imageUrl,
            'color' => $borderColorHex,
            'border' => $borderWidth
        ]);
        $filenameHash = md5($paramsIdentifier);
        $storageFilename = self::STORAGE_PATH_PREFIX . '/' . $filenameHash . '.png'; // Ej: image-converter-cache/abcdef123456.png

        // --- 3. Comprobar si la Imagen Procesada Ya Existe en Storage ---
        // Usamos el disco 'local' por defecto (storage/app)
        if (Storage::disk('local')->exists($storageFilename)) {
            try {
                // Obtener el contenido del archivo almacenado
                $storedImageData = Storage::disk('local')->get($storageFilename);

                // Devolver la respuesta desde el archivo almacenado
                return new Response($storedImageData, 200, [
                    'Content-Type' => 'image/png',
                    'Content-Disposition' => 'inline; filename="round_image.png"',
                    'X-Image-Source' => 'STORAGE' // Header para depuración
                ]);
            } catch (Exception $e) {
                // Si hay un error al leer del storage (raro, pero posible),
                // proceder a generar la imagen de nuevo.
                // Podrías loggear este error específico: Log::warning("Failed to read image from storage: {$storageFilename}", ['exception' => $e]);
            }
        }

        // --- 4. Verificar Extensión GD (Solo si no se sirvió desde storage) ---
        if (!extension_loaded('gd')) {
             return response()->json(['error' => 'GD extension is not loaded.'], 500);
        }

        // Inicializar variables de recursos GD para el bloque finally
        /** @var GdImage|resource|false $sourceImage */
        $sourceImage = false;
        /** @var GdImage|resource|false $mask */
        $mask = false;
        /** @var GdImage|resource|false $maskedImage */
        $maskedImage = false;
        /** @var GdImage|resource|false $finalImage */
        $finalImage = false;

        try {
            // --- 5. Obtener Contenido de la Imagen Original ---
            $imageData = @file_get_contents($imageUrl);
            if ($imageData === false) {
                return response()->json(['error' => 'Could not retrieve image from URL.'], 400);
            }

            // --- 6. Cargar Imagen Original ---
            $sourceImage = @imagecreatefromstring($imageData);
            if (!$sourceImage) {
                 return response()->json(['error' => 'Could not process image data. Invalid format?'], 400);
            }

            $originalWidth = imagesx($sourceImage);
            $originalHeight = imagesy($sourceImage);
            $diameter = min($originalWidth, $originalHeight);
            $cropX = ($originalWidth - $diameter) / 2;
            $cropY = ($originalHeight - $diameter) / 2;

            // --- 7. Preparar Lienzo Final ---
            $finalDiameter = $diameter + ($borderWidth * 2);
            $finalImage = imagecreatetruecolor($finalDiameter, $finalDiameter);
            if (!$finalImage) throw new Exception("Could not create final image canvas.");

            imagesavealpha($finalImage, true);
            $transparentColor = imagecolorallocatealpha($finalImage, 0, 0, 0, 127);
            imagefill($finalImage, 0, 0, $transparentColor);

            // --- 8. Convertir Color Hex a RGB ---
            $borderColorRGB = $this->hex2rgb($borderColorHex);
            if (!$borderColorRGB) {
                 return response()->json(['error' => 'Invalid border color format.'], 400);
            }
            $borderGdColor = imagecolorallocate($finalImage, $borderColorRGB['r'], $borderColorRGB['g'], $borderColorRGB['b']);
             if ($borderGdColor === false) throw new Exception("Could not allocate border color.");

            // --- 9. Dibujar Círculo de Borde ---
            imagefilledellipse(
                $finalImage,
                $finalDiameter / 2,
                $finalDiameter / 2,
                $finalDiameter,
                $finalDiameter,
                $borderGdColor
            );

            // --- 10. Crear Lienzo Temporal para la Imagen Enmascarada ---
             $maskedImage = imagecreatetruecolor($diameter, $diameter);
             if (!$maskedImage) throw new Exception("Could not create masked image canvas.");
             imagesavealpha($maskedImage, true);
             $transparentMasked = imagecolorallocatealpha($maskedImage, 0, 0, 0, 127);
             imagefill($maskedImage, 0, 0, $transparentMasked);

            // --- 11. Crear Máscara Circular ---
            $mask = imagecreatetruecolor($diameter, $diameter);
            if (!$mask) throw new Exception("Could not create mask image.");
            $maskBlack = imagecolorallocate($mask, 0, 0, 0);
            $maskWhite = imagecolorallocate($mask, 255, 255, 255);
            imagefill($mask, 0, 0, $maskBlack);
            imagefilledellipse($mask, $diameter / 2, $diameter / 2, $diameter, $diameter, $maskWhite);

            // --- 12. Aplicar Máscara a la Imagen Original ---
            /** @var GdImage|resource|false $tempSourceCropped */
            $tempSourceCropped = imagecreatetruecolor($diameter, $diameter);
            if (!$tempSourceCropped) throw new Exception("Could not create temporary cropped source.");
            imagesavealpha($tempSourceCropped, true);
            $transparentTemp = imagecolorallocatealpha($tempSourceCropped, 0, 0, 0, 127);
            imagefill($tempSourceCropped, 0, 0, $transparentTemp);
            imagecopy($tempSourceCropped, $sourceImage, 0, 0, $cropX, $cropY, $diameter, $diameter);

            for ($x = 0; $x < $diameter; $x++) {
                for ($y = 0; $y < $diameter; $y++) {
                    $maskPixelColor = imagecolorat($mask, $x, $y);
                    $isWhite = ($maskPixelColor & 0xFFFFFF) == 0xFFFFFF;

                    if ($isWhite) {
                        $sourcePixelColor = imagecolorat($tempSourceCropped, $x, $y);
                        imagesetpixel($maskedImage, $x, $y, $sourcePixelColor);
                    }
                }
            }
            imagedestroy($tempSourceCropped);
            imagedestroy($mask);
            $mask = false; // Asegurar que no se destruya de nuevo

            // --- 13. Copiar Imagen Enmascarada sobre el Borde ---
            imagecopy(
                $finalImage,
                $maskedImage,
                $borderWidth,
                $borderWidth,
                0,
                0,
                $diameter,
                $diameter
            );

            // --- 14. Generar Salida PNG (en memoria) ---
            ob_start();
            imagepng($finalImage); // Capturar la salida PNG
            $imageDataOutput = ob_get_clean(); // Obtener los datos binarios

            // --- 15. Guardar la Imagen Generada en Storage ---
            try {
                // Guardar los datos binarios en el archivo correspondiente usando el disco 'local'
                Storage::disk('local')->put($storageFilename, $imageDataOutput);
                // Opcional: Establecer permisos si es necesario (normalmente no hace falta con 'local')
                // Storage::disk('local')->setVisibility($storageFilename, 'private');
            } catch (Exception $e) {
                // Error al guardar en storage. Loggear y continuar (la imagen se servirá, pero no se guardará para la próxima vez).
                // Log::error("Failed to save image to storage: {$storageFilename}", ['exception' => $e]);
            }

            // --- 16. Devolver Respuesta con la Imagen Recién Generada ---
            return new Response($imageDataOutput, 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'inline; filename="round_image.png"',
                'X-Image-Source' => 'GENERATED' // Header para depuración
            ]);

        } catch (Exception $e) {
            // Loggear el error: Log::error("Image conversion failed: " . $e->getMessage());
            return response()->json(['error' => 'An error occurred during image processing: ' . $e->getMessage()], 500);
        } finally {
            // --- 17. Limpiar Memoria GD (SIEMPRE) ---
             if ($sourceImage instanceof GdImage || is_resource($sourceImage)) @imagedestroy($sourceImage);
             if ($mask instanceof GdImage || is_resource($mask)) @imagedestroy($mask);
             if ($maskedImage instanceof GdImage || is_resource($maskedImage)) @imagedestroy($maskedImage);
             if ($finalImage instanceof GdImage || is_resource($finalImage)) @imagedestroy($finalImage);
        }
    }

    /**
     * Convierte un color hexadecimal a un array RGB.
     *
     * @param string $hex
     * @return array{r: int, g: int, b: int}|null
     */
    private function hex2rgb(string $hex): ?array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) == 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } elseif (strlen($hex) == 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            return null;
        }

        return ['r' => $r, 'g' => $g, 'b' => $b];
    }
}