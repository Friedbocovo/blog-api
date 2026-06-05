<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class CloudinaryService
{
    private string $cloudName;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->cloudName = config('cloudinary.cloud_name');
        $this->apiKey    = config('cloudinary.api_key');
        $this->apiSecret = config('cloudinary.api_secret');
    }

    /**
     * Upload a file to Cloudinary and return the secure URL.
     */
    public function upload(UploadedFile $file, string $folder = 'covers'): string
    {
        $timestamp = time();

        // Signature Cloudinary : paramètres triés alphabétiquement + secret
        $paramsToSign = [
            'folder'    => $folder,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);
        $paramString = http_build_query($paramsToSign, '', '&');
        $signature   = sha1($paramString . $this->apiSecret);

        $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";

        $response = Http::attach(
            'file',
            fopen($file->getRealPath(), 'r'),
            $file->getClientOriginalName()
        )->post($url, [
            'api_key'   => $this->apiKey,
            'timestamp' => $timestamp,
            'folder'    => $folder,
            'signature' => $signature,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Cloudinary upload failed: ' . $response->body());
        }

        return $response->json('secure_url');
    }

    /**
     * Delete an image from Cloudinary by its URL.
     */
    public function delete(string $url): void
    {
        // Extract public_id from the URL
        // e.g. https://res.cloudinary.com/cloud/image/upload/v123/covers/filename.jpg
        preg_match('/upload\/(?:v\d+\/)?(.+)\.\w+$/', $url, $matches);
        if (!isset($matches[1])) return;

        $publicId  = $matches[1];
        $timestamp = time();
        $signature = sha1("public_id={$publicId}&timestamp={$timestamp}" . $this->apiSecret);

        Http::post("https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy", [
            'public_id' => $publicId,
            'api_key'   => $this->apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
        ]);
    }
}
