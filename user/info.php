<?php

require_once dirname(__DIR__) . "/include/DB_Functions.php";
$db = new DB_Functions();

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

$uploadedImages = json_decode($userUploads, true);

if (json_last_error() != JSON_ERROR_NONE) {
    doSendResponse(true, null, -1, -1, "Server Error.");
}


doSendResponse(false, $userMobile, $userUploadCount, $userLikeCount, "User details fetched successfully.");

function doSendResponse($error, $userMobile, $user_upload_count, $user_like_count, $msg)
{
    header('Content-Type: application/json');
    echo json_encode(array("error" => $error, "mobile" => $userMobile, "upload_count" => $user_upload_count, "like_count" => $user_like_count, "msg" => $msg));
    exit();
}