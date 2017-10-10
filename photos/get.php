<?php
require_once dirname(__DIR__) . "/include/DB_Functions.php";

$db = new DB_Functions();

if (!Util::hasAuthorizationHeader()) {
    http_response_code(401);
    doSendResponse(true, null, "You are not authorized");
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(405);
    doSendResponse(true, null, "Request method GET is not supported.");
}
$jsonStr = trim(file_get_contents("php://input"));

$payLoadArray = json_decode($jsonStr, true);

if (json_last_error() != JSON_ERROR_NONE) {
    http_response_code(400);
    doSendResponse(true, null, "Invalid or malformed JSON.");
}

if (!isset($payLoadArray["list_limit"]) || !isset($payLoadArray["list_offset"])) {
    http_response_code(400);
    doSendResponse(true, null, "There was a missing or invalid parameter.");
}

$listLimit = $payLoadArray["list_limit"];
$listOffset = $payLoadArray["list_offset"];

if (($listLimit < 0 && $listLimit > 50) || ($listOffset < 0 && $listLimit > 50)) {
    http_response_code(400);
    doSendResponse(true, null, "There was a invalid parameter value.");
}

$accessToken = trim($_SERVER["HTTP_AUTHORIZATION"]);

if (($uniqueId = $db->decodeAccessToken($accessToken)) == null) {
    http_response_code(401);
    doSendResponse(true, null, "There was a problem in verifying access token or a invalid access token provided.");
}

if (!$db->isUserExists($uniqueId)) {
    doSendResponse(true, null, "No user found.");
}

$uploads = array();

$uploadedPhotos = $db->getUploadedPhotos($listLimit, $listOffset);
foreach ($uploadedPhotos as $value) {
    list($photoName, $photoId, $uploadBy, $uploadTime, $photoLikeCount) = $value;
    if (($uploadedBy = $db->getUserName($uploadBy)) != null) {

        array_push($uploads,
            array(
                "url" => array(
                    "s" => $db->baseUrl . "/selfiecontest/images/user/" . $uploadBy . "/400x400/" . $photoName,
                    "m" => $db->baseUrl . "/selfiecontest/images/user/" . $uploadBy . "/640x640/" . $photoName
                ),
                "id" => $photoId,
                "liked" => $db->isPhotoLikedByUser($photoId, $uniqueId),
                "uploaded_by" => $uploadedBy,
                "upload_time" => $uploadTime,
                "like_count" => $photoLikeCount)
        );
    }
}
doSendResponse(false, $uploads, "Photos fetched successfully.");

function doSendResponse($error, $user_uploads, $msg)
{
    header('Content-Type: application/json');
    echo json_encode(array("error" => $error, "uploads" => $user_uploads, "msg" => $msg));
    exit();
}