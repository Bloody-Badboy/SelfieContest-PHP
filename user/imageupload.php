<?php

error_reporting(E_ALL);

require_once dirname(__DIR__) . "/include/DB_Functions.php";
$db = new DB_Functions();

$largeOutDir = dirname(__DIR__) . "/images/user/original/";
$smallOutDir = dirname(__DIR__) . "/images/user/";

$stampFile = dirname(__DIR__) . "/assets/images/watermark.png";


define('MAX_UPLOAD_SIZE', 1024 * 1024 * 10);
define('WHITE_LIST_EXT', array('jpeg', 'jpg', 'png', 'gif'));
define('WHITE_LIST_MIME_TYPE', array('image/jpeg', 'image/gif', 'image/png', 'image/pjpeg'));


if (!Util::hasAuthorizationHeader()) {
    http_response_code(401);
    doSendResponse(true, null, "You are not authorized");
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(405);
    doSendResponse(true, null, "Request method GET is not supported.");
}

$accessToken = trim($_SERVER["HTTP_AUTHORIZATION"]);

if (($uniqueId = $db->decodeAccessToken($accessToken)) == null) {
    http_response_code(401);
    doSendResponse(true, null, "There was a problem in verifying access token or a invalid access token provided.");
}

if (!$db->isUserExists($uniqueId)) {
    doSendResponse(true, null, "No user found.");
}

if ((!empty($_FILES["image"])) && ($_FILES["image"]['error'] == UPLOAD_ERR_OK)) {
    $fileInfo = pathinfo($_FILES["image"]['name']);
    $fileName = $fileInfo['filename'];
    $fileExt = strtolower($fileInfo['extension']);

    if (!in_array($fileExt, WHITE_LIST_EXT)) {
        doSendResponse(true, null, "Upload failed, invalid file extension.");
    }

    if (!in_array($_FILES["image"]["type"], WHITE_LIST_MIME_TYPE)) {
        doSendResponse(true, null, "Upload failed, invalid file type.");
    }

    if ($_FILES["image"]["size"] > MAX_UPLOAD_SIZE) {
        doSendResponse(true, null, "Upload failed, uploaded file size must be < 10MB.");
    }

    if (!getimagesize($_FILES["image"]['tmp_name'])) {
        doSendResponse(true, null, "Upload failed, uploaded file is not a valid image");
    }

    $largeFileSavePath = $largeOutDir . $uniqueId . "/";
    $smallFileSavePath = $smallOutDir . $uniqueId . "/400x400/";
    $mediumFileSavePath = $smallOutDir . $uniqueId . "/640x640/";

    if (!file_exists($largeFileSavePath) || !is_dir($largeFileSavePath) || !is_readable($largeFileSavePath)) {
        if (!mkdir($largeFileSavePath, 0777, true)) {
            doSendResponse(true, null, "Upload failed, Server Error!");
        }
    }

    if (!file_exists($smallFileSavePath) || !is_dir($smallFileSavePath) || !is_readable($smallFileSavePath)) {
        if (!mkdir($smallFileSavePath, 0777, true)) {
            doSendResponse(true, null, "Upload failed, Server Error!");
        }
    }

    if (!file_exists($mediumFileSavePath) || !is_dir($mediumFileSavePath) || !is_readable($mediumFileSavePath)) {
        if (!mkdir($mediumFileSavePath, 0777, true)) {
            doSendResponse(true, null, "Upload failed, Server Error!");
        }
    }

    $photoName = Util::generateUniqueID() . "." . $fileExt;
    $uploadTime = round(microtime(true) * 1000);

    $largeFileSavePath .= $photoName;
    $smallFileSavePath .= $photoName;
    $mediumFileSavePath .= $photoName;

    if (file_exists($largeFileSavePath) || file_exists($smallFileSavePath)) {
        doSendResponse(true, null, "Upload failed, a file with same name already exists");
    }

    if (move_uploaded_file($_FILES["image"]['tmp_name'], $largeFileSavePath)) {
        if ($db->updateUserUploadDetails($uniqueId, $photoName) &&
            Util::resizeImageAndStamp($largeFileSavePath, $smallFileSavePath, null, 400, 400) &&
            Util::resizeImageAndStamp($largeFileSavePath, $mediumFileSavePath, $stampFile, 640, 640) &&
            $db->addNewPhoto($uniqueId, $photoName, $uploadTime)
        ) {
            $photoDetails = array(
                "url" => array(
                    "small" => $db->baseUrl . "/selfiecontest/images/user/" . $uniqueId . "/400x400/" . $photoName,
                    "medium" => $db->baseUrl . "/selfiecontest/images/user/" . $uniqueId . "/640x640/" . $photoName
                ),
                "photo_id" => $db->calculatePhotoID($uniqueId, $photoName),
                "user_liked" => false,
                "uploaded_by" => $db->getUserName($uniqueId),
                "upload_time" => $uploadTime,
                "like_count" => 0);

            doSendResponse(false, $photoDetails, "Image uploaded successfully.");
        } else {
            http_response_code(500);
            doSendResponse(true, null, "Upload failed, Server Error!");
        }
    } else {
        http_response_code(500);
        doSendResponse(true, null, "Upload failed, Server Error!");
    }

} else {
    doSendResponse(true, null, "No file uploaded.");
}


function doSendResponse($error, $photoDetails, $msg)
{
    header('Content-Type: application/json');
    echo json_encode(array("error" => $error, "photo_details" => $photoDetails, "msg" => $msg));
    exit();
}