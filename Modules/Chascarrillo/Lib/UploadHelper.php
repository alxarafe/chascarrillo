<?php

namespace Modules\Chascarrillo\Lib;

use Alxarafe\Lib\Trans;

class UploadHelper
{
    /**
     * Handles file upload to a specific subdirectory of public/uploads.
     *
     * @param string $fileKey Key in $_FILES
     * @param string $targetDir Subdirectory name (e.g. 'posts')
     * @return string|null The public URL of the uploaded file, or null on failure.
     */
    public static function upload(string $fileKey, string $targetDir = 'general'): ?string
    {
        if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $file = $_FILES[$fileKey];
        $publicPath = 'uploads/' . $targetDir;
        $absolutePath = APP_PATH . '/public/' . $publicPath;

        if (!is_dir($absolutePath)) {
            if (!mkdir($absolutePath, 0777, true)) {
                return null;
            }
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = bin2hex(random_bytes(8)) . '.' . $extension;
        $targetFile = $absolutePath . '/' . $safeName;

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return '/' . $publicPath . '/' . $safeName;
        }

        return null;
    }
}
