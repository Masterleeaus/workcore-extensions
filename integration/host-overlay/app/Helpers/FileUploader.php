<?php

namespace App\Helpers;


use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class FileUploader
{

    public function uploadThumbnail($file, $path, $width = 150, $height = 150)
    {
        return $this->uploadFile($file, $path, $width, $height, 85);
    }

    public function uploadFile($file, $path, $width = null, $height = null, $old = null)
    {
        try {

            if ($old) {
                $this->deleteFile($old);
            }

            $filename = time() . '_' . Str::random(10) . '.webp';

            $fullPath = $path . '/' . $filename;

            $manager = new ImageManager(new Driver());

            $image = $manager->read($file->getRealPath());

            if ($width || $height) {
                $image->scale(width: $width, height: $height);
            }

            $encodedImage = $image->encodeByExtension('webp', quality: 90);

            Storage::disk('public')->put($fullPath, (string)$encodedImage);

            return $fullPath;
        } catch (Exception $e) {
            Log::error('File upload error: ' . $e->getMessage());
            return false;
        }
    }

    public function deleteFile($filePath)
    {
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }

        return false;
    }

    public function deleteThumbnail($filePath)
    {
        return $this->deleteFile($filePath);
    }
}
