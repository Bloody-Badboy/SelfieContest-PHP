<?php
require_once dirname(__DIR__) . "/include/DB_Functions.php";
$db = new DB_Functions();

define('PHOTO_LOAD_LIMIT', 20);
define('CONTEST_ENDED', false);
define('CONTEST_END_TIME_MILLIS', 1505241000000);

if (!Util::hasAuthorizationHeader()) {
    http_response_code(401);
    doSendResponse(true, null, -1, -1, "You are not authorized");
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(405);
    doSendResponse(true, null, -1, -1, "Request method GET is not supported.");
}

$accessToken = trim($_SERVER["HTTP_AUTHORIZATION"]);

if (($uniqueId = $db->decodeAccessToken($accessToken)) == null) {
    http_response_code(401);
    doSendResponse(true, null, -1, -1, "There was a problem in verifying access token or a invalid access token provided.");
}

if (!$db->isUserExists($uniqueId)) {
    doSendResponse(true, null, -1, -1, "No user found.");
}

if ((list($userMobile, $userUploads, $userUploadCount, $userLikeCount) = $db->getUserDetails($uniqueId)) == null) {
    doSendResponse(true, null, -1, -1, "An unexpected error occurred while fetching user uploads details.");
}

$uploadedPhotos = json_decode($userUploads, true);

if (json_last_error() != JSON_ERROR_NONE) {
    doSendResponse(true, null, -1, -1,  "Invalid or malformed JSON.");
}

for ($i = 0; $i < count($uploadedPhotos); $i++) {
    $photoName = $uploadedPhotos[$i];
    $photoId = $db->calculatePhotoID($uniqueId, $photoName);
    if ((list($uploadBy, $uploadTime, $photoLikeCount) = $db->getPhotoDetails($photoId)) != null && ($uploadedBy = $db->getUserName($uniqueId)) != null) {
        $uploadedPhotos[$i] = array(
            "url" => array(
                "s" => $db->baseUrl . "/selfiecontest/images/user/" . $uniqueId . "/400x400/" . $uploadedPhotos[$i],
                "m" => $db->baseUrl . "/selfiecontest/images/user/" . $uniqueId . "/640x640/" . $uploadedPhotos[$i]
            ),
            "id" => $photoId,
            "liked" => $db->isPhotoLikedByUser($photoId, $uniqueId),
            "uploaded_by" => $uploadedBy,
            "upload_time" => $uploadTime,
            "like_count" => $photoLikeCount);
    }
}

doSendResponse(false, $uploadedPhotos, $userUploadCount, $userLikeCount, "Login successfully.");

function doSendResponse($error, $user_uploads, $user_upload_count, $user_like_count, $msg)
{
    header('Content-Type: application/json');
    echo json_encode(array("error" => $error, "contest_ended" => CONTEST_ENDED, "contest_end_time_millis" => CONTEST_END_TIME_MILLIS, "photo_load_limit" => PHOTO_LOAD_LIMIT, "uploads" => $user_uploads, "upload_count" => $user_upload_count, "like_count" => $user_like_count, "msg" => $msg));
    exit();
}