<?php

namespace App\Services\AWS;

use Exception;
use Illuminate\Http\File;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class AWSService
{
    /**
     * Store a file in Amazon S3
     *
     * @param string $folderName
     * @param File|UploadedFile $file
     * @return string
     * @throws Exception
     */
    public static function store($folderName, $file)
    {
        if ($file instanceof UploadedFile) {
            $filePath = self::createTempFile($file);

            self::optimizeImage($filePath);

            $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
            $s3Path = $folderName . '/' . $fileName;

            Storage::disk('s3')->put($s3Path, file_get_contents($filePath));

            unlink($filePath);

            return self::pathToUrl($s3Path);
        }

        if ($file instanceof \Illuminate\Support\HtmlString) {
            return self::storeHtmlString($folderName, $file);
        }

        throw new \InvalidArgumentException('Invalid file type');
    }

    /**
     * Create a temporary file for processing
     *
     * @param UploadedFile $file
     * @return string
     * @throws Exception
     */
    private static function createTempFile(UploadedFile $file)
    {
        $tempDir = storage_path('app/temp/');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $fileName = Str::random(40) . '.' . $file->getClientOriginalExtension();
        $filePath = $tempDir . $fileName;

        copy($file->getRealPath(), $filePath);

        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new Exception("File could not be found or is not readable: $filePath");
        }

        return $filePath;
    }

    /**
     * Optimize an image file before uploading to S3
     *
     * @param string $filePath
     * @throws Exception
     */
    private static function optimizeImage($filePath)
    {
        $mimeType = mime_content_type($filePath);
        $originalSize = filesize($filePath);

        $commands = [
            'jpeg' => "jpegoptim --strip-all --all-progressive --max=80 " . escapeshellarg($filePath),
            'png'  => "pngquant --force --quality=60-80 --output " . escapeshellarg($filePath) . " " . escapeshellarg($filePath),
            'gif'  => "gifsicle --optimize=3 --colors=128 -o " . escapeshellarg($filePath) . " " . escapeshellarg($filePath),
        ];

        foreach ($commands as $key => $command) {

            if (str_contains($mimeType, $key)) {

                exec("$command 2>&1", $output, $returnVar);

                if ($returnVar !== 0) {
                    throw new Exception("Image optimization failed: " . implode("\n", $output));
                }

                clearstatcache();

                if (filesize($filePath) >= $originalSize) {
                    //  throw new Exception("Optimization had no effect, file size unchanged.");
                }

                return;

            }
        }

        //  throw new Exception("Unsupported image format: $mimeType");
    }

    /**
     * Store an HTML string as an image in S3
     *
     * @param string $folderName
     * @param \Illuminate\Support\HtmlString $file
     * @return string
     */
    private static function storeHtmlString($folderName, $file)
    {
        $fileName = Str::uuid() . '.png';
        $s3Path = $folderName . '/' . $fileName;

        Storage::disk('s3')->put($s3Path, (string) $file);

        return self::pathToUrl($s3Path);
    }

    /**
     *  Delete the specified file in Amazon S3 using the specified URL
     *
     * @param string $url
     * @return bool
     */
    public static function delete($url)
    {
        if(self::exists($url)) {

            return Storage::disk('s3')->delete(AWSService::urlToPath($url));

        }else{

            return true;

        }
    }

    /**
     *  Check if the specified file in Amazon S3 exists using the specified URL
     *
     * @param string $url
     * @return bool
     */
    public static function exists($url)
    {
        return Storage::disk('s3')->exists(AWSService::urlToPath($url));
    }

    /**
     *  Generate the Amazon file URL using the specified path
     *
     *  @param array $path The path to the file e.g "logos/somelogo.png"
     *  @return string
     */
    public static function pathToUrl($path)
    {
        if( empty(config('app.AWS_DEFAULT_REGION')) ) {

            //  Throw an exception
            throw new Exception('The AWS default region must be provided');

        }else if ( empty(config('app.AWS_BUCKET')) ) {

            //  Throw an exception
            throw new Exception('The AWS bucket must be provided');

        }else if ( empty($path) ) {

            //  Throw an exception
            throw new Exception('The file path must be provided');

        }else{

            /**
             *  Return the Amazon file URL e.g
             *
             *  "logos/somelogo.png" to "https://s3.eu-west-2.amazonaws.com/bonako/logos/somelogo.png"
             *
             *  In this example, the AWS_DEFAULT_REGION is "eu-west-2" and the AWS_BUCKET is "bonako"
             */

            //  Return the Amazon file URL
            return 'https://s3.'.config('app.AWS_DEFAULT_REGION').'.amazonaws.com/'.config('app.AWS_BUCKET').'/'.$path;

        }
    }

    /**
     *  Generate the Amazon file path using the specified URL
     *
     *  @param string $url The url to the file e.g "https://s3.eu-west-2.amazonaws.com/bonako/logos/somelogo.png"
     *  @return string
     */
    public static function urlToPath($url)
    {
        if ( empty(config('app.AWS_BUCKET')) ) {

            //  Throw an exception
            throw new Exception('The AWS bucket must be provided');

        }else if ( empty($url) ) {

            //  Throw an exception
            throw new Exception('The url must be provided');

        }else{

            /**
             *  Return the Amazon file path e.g
             *
             *  "https://s3.eu-west-2.amazonaws.com/bonako/logos/somelogo.png" to "logos/somelogo.png"
             *
             *  In this example, the AWS_BUCKET is "bonako"
             */
            return Arr::last(explode(config('app.AWS_BUCKET').'/', $url));

        }
    }
}
