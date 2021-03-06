<?php
namespace Destiny\Common\Utils;

use Destiny\Common\Config;
use Destiny\Common\Log;
use GuzzleHttp;

class ImageDownload {

    private static $ALLOWED_EXT = ["jpg", "jpeg", "png", "gif"];
    private static $PATH_SEPARATOR = "/";

    /**
     * Downloads a remote image. File is named using a hash of the URL.
     * Files are stored in sub folders using the first 0-9A-z of the hash.
     *
     * @param $url string
     *  url to image
     * @param $path string
     *  Full directory, must not begin with slash, must end with slash
     *  Must not include filename, must be an existing path, must include a filename and ext
     * @param $overwrite boolean
     *  If false, will not request a new image if one is found
     *  If true, filename will be derived from the md5 of the file contents, instead of the URL
     *   This is to make sure new images are not cached by the http server.
     *   @TODO Adds complexity where there shouldn't - refactor
     *
     * @return string a RELATIVE path to the file, or an empty string if something went wrong
     */
    public static function download($url, $overwrite = false, $path = null){
        $response = '';
        if (empty($url))
            return $response;
        if (empty($path))
            $path = Config::$a['images']['path'];
        $uri = parse_url($url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
        $name = md5($url);
        $shard = (preg_match("/[a-z0-9]/i", $name, $match)) ? $match[0] : "0";
        $final = $shard . self::$PATH_SEPARATOR . $name . "." . $ext;
        Log::debug("Downloading ..." . $uri);
        if (strlen($shard) <= 0) {
            Log::error("Invalid shard." . $shard);
        } else if (!file_exists($path . $shard) && !mkdir($path . $shard)) {
            Log::error("Could not make shard sub-folder. " . $path . $shard);
        } else if (empty($ext) || !in_array($ext, self::$ALLOWED_EXT)) {
            Log::error("File type not supported or invalid extension." . $uri);
        } else if ($overwrite === false && file_exists($path . $final)) {
            Log::notice("Not downloading image, one already exists.");
            $response = $final;
        } else {
            try {
                $client = new GuzzleHttp\Client(['timeout' => 15, 'connect_timeout' => 5]);
                $r = $client->request('GET', $url, [
                    'headers' => ['User-Agent' => Config::userAgent()],
                    'sink' => $path . $final
                ]);
                $code = $r->getStatusCode();
                if ($code != Http::STATUS_OK) {
                    Log::error("Invalid http response code. [" . $code . "] " . $url);
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
        }
        return $response;
    }

}