<?php


use App\Helpers\FileUploader;
use App\Models\Setting;


if (!function_exists('apiResponse')) {
    function apiResponse(
        bool   $success,
        string $message,
               $data,
        int    $statusCode = 200
    )
    {
        $response = [
            'success' => $success,
            'message' => $message,
        ];

        if ($success) {
            $response['data'] = $data;
        } else {
            $response['errors'] = $data;
        }

        return response()->json($response, $statusCode);
    }
}

if (!function_exists('responseSuccess')) {
    function responseSuccess(string $message = 'Success', $data = [], int $statusCode = 200)
    {
        return apiResponse(true, $message, $data, $statusCode);
    }
}

if (!function_exists('responseError')) {
    function responseError(string $message = 'Error', $errors = [], int $statusCode = 400)
    {
        return apiResponse(false, $message, $errors, $statusCode);
    }
}


if (!function_exists('uploadFile')) {
    function uploadFile($file, $path, $width = null, $height = null, $old = null)
    {
        return (new FileUploader)->uploadFile($file, $path, $width, $height, $old);
    }
}

if (!function_exists('deleteFile')) {
    function deleteFile($filePath)
    {
        return (new FileUploader)->deleteFile($filePath);
    }
}

if (!function_exists('uploadThumbnail')) {
    function uploadThumbnail($file, $path)
    {
        return (new FileUploader)->uploadThumbnail($file, $path);
    }
}

if (!function_exists('deleteThumbnail')) {
    function deleteThumbnail($filePath)
    {
        return (new FileUploader)->deleteThumbnail($filePath);
    }
}

if (!function_exists('getImageUrl')) {
    function getImageUrl($filePath, $default = null)
    {
        $default = $default ?? asset('images/default.png');
        if (!$filePath) {
            return $default;
        }
        return asset('storage/' . $filePath) ?? $default;
    }
}


// date format
if (!function_exists('dateFormat')) {
    function dateFormat($date)
    {
        return date('d-m-Y', strtotime($date));
    }
}


if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        $setting = Setting::where('name', $key)->first();
        if ($setting) {
            return $setting->value;
        }
        return $default;
    }
}


if (!function_exists('avatar')) {
    function avatar($name = 'o', $imageUrl = null): string
    {
        if (!blank($imageUrl)) {
            return getImageUrl($imageUrl);
        }

        $themeColor = setting('theme_secondary_color', '#724ff9');
        $colorCode = str_replace('#', '', $themeColor);
        return "https://ui-avatars.com/api/?name=$name&background=$colorCode&color=fff";
    }
}


