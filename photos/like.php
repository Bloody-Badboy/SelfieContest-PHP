<?php
require_once dirname(__DIR__) . "/include/DB_Functions.php";

$db = new DB_Functions();

if (!Util::hasAuthorizationHeader()) {
    http_response_code(401);
    doSendResponse(true, "You are not authorized");
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(405);
    doSendResponse(true, "Request method GET is not supported.");
}
$jsonStr = trim(file_get_contents("php://input"));

$payLoadArray = json_decode($jsonStr, true);

if (json_last_error() != JSON_ERROR_NONE) {
    http_response_code(400);
    doSendResponse(true, "Invalid or malformed JSON.");
}

if (!isset($payLoadArray["photo_id"]) || !isset($payLoadArray["set_like"])) {
    http_response_code(400);
    doSendResponse(true, "There was a missing or invalid parameter.");
}

if (trim($payLoadArray["photo_id"]) == "" || !is_bool($payLoadArray["set_like"])) {
    http_response_code(400);
    doSendResponse(true, "There was a missing or invalid parameter value.");
}

$photoId = $payLoadArray["photo_id"];
$setLike = $payLoadArray["set_like"];

$accessToken = trim($_SERVER["HTTP_AUTHORIZATION"]);

if (($uniqueId = $db->decodeAccessToken($accessToken)) == null) {
    http_response_code(401);
    doSendResponse(true, "There was a problem in verifying access token or a invalid access token provided.");
}

if (!$db->isUserExists($uniqueId)) {
    doSendResponse(true, "No user found.");
}

if ($db->isPhotoLikedByUser($photoId, $uniqueId) && !$setLike) {
    if ($db->addRemovePhotoLike($photoId, $uniqueId, false)) {
        doSendResponse(false, "Your liked removed.");
    } else {
        doSendResponse(true, "Server Error!");
    }
} else {
    if ($db->addRemovePhotoLike($photoId, $uniqueId, true)) {
        doSendResponse(false, "Your liked added.");
    } else {
        doSendResponse(true, "Server Error!");
    }
}

function doSendResponse($error, $msg)
{
    header('Content-Type: application/json');
    echo json_encode(array("error" => $error, "msg" => $msg));
    exit();
}