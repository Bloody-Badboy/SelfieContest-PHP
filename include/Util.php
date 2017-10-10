<?php

class Util
{
    public static function resizeImageAndStamp($filename, $newFilename, $stampFileName = null, $newWidth = 400, $newHeight = 400)
    {
        $result = false;
        if (file_exists($filename) && is_file($filename) && is_readable($filename)) {
            $stampImage = null;
            if ($stampFileName != null && file_exists($stampFileName) && is_file($stampFileName) && is_readable($stampFileName)) {
                $stampImage = imagecreatefrompng($stampFileName);
            }
            if (($type = pathinfo($filename, PATHINFO_EXTENSION))) {

                if ($type == "jpeg") {
                    $type = "jpg";
                }

                if (!list($width, $height) = getimagesize($filename)) {
                    return $result;
                }

                if (($newWidth / $newHeight) > ($ratio = $width / $height)) {
                    $newWidth = round($newHeight * $ratio);
                } else {
                    $newHeight = round($newWidth / $ratio);
                }

                if ($type == "bmp") {
                    $srcImage = imagecreatefromwbmp($filename);
                } elseif ($type == "gif") {
                    $srcImage = imagecreatefromgif($filename);
                } elseif ($type == "jpg") {
                    $srcImage = imagecreatefromjpeg($filename);
                } elseif ($type == "png") {
                    $srcImage = imagecreatefrompng($filename);
                } else {
                    return $result;
                }

                $newImage = imagecreatetruecolor($newWidth, $newHeight);

                if ($type == "gif" || $type == "png") {
                    $color = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                    imagecolortransparent($newImage, $color);
                    imagealphablending($newImage, false);
                    imagesavealpha($newImage, true);
                }

                imagecopyresampled($newImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                if ($stampImage != null) {
                    imagecopy($newImage, $stampImage, imagesx($newImage) - imagesx($stampImage), imagesy($newImage) - imagesy($stampImage), 0, 0, imagesx($stampImage), imagesy($stampImage));
                    imagedestroy($stampImage);
                }
                imagedestroy($srcImage);


                if ($type == "bmp") {
                    $result = imagewbmp($newImage, $newFilename);
                } elseif ($type == "gif") {
                    $result = imagegif($newImage, $newFilename);
                } elseif ($type == "jpg") {
                    $result = imagejpeg($newImage, $newFilename);
                } elseif ($type == "png") {
                    $result = imagepng($newImage, $newFilename);
                }
                imagedestroy($newImage);
            }
        }
        return $result;
    }

    public static function hasAuthorizationHeader()
    {
        if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
            if (trim($_SERVER["HTTP_AUTHORIZATION"]) != "") {
                return true;
            }
        }
        return false;
    }

    private static function isSSL()
    {
        if (isset($_SERVER["HTTPS"])) {
            if (strtolower($_SERVER["HTTPS"]) == "on")
                return true;
            if ($_SERVER["HTTPS"] == "1")
                return true;
        } elseif (isset($_SERVER["SERVER_PORT"]) && ("443" == $_SERVER["SERVER_PORT"])) {
            return true;
        }
        return false;
    }

    public static function generateUniqueID()
    {
        $s = md5(uniqid(time(), true));
        return substr($s, 0, 8) . "-" . substr($s, 9, 4) . "-" . substr($s, 13, 4) . "-" . substr($s, 17, 4) . "-" . substr($s, 12);
    }

    public static function getServerURL()
    {
        $url = self::isSSL() ? "https://" : "http://";
        $host = $_SERVER["HTTP_HOST"];
        $url = strpos($host, ":") !== false ? $url . trim(explode(":", $host)[0]) : $url . $host;
        if ($_SERVER["SERVER_PORT"] != "80")
            $url .= ":" . $_SERVER["SERVER_PORT"];
        return $url;
    }

    public static function urlSafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $input .= str_repeat('=', $padLen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    public static function urlSafeB64Encode($input)
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }
}