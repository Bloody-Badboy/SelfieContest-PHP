<?php
require_once dirname(__DIR__) . "/include/DB_Functions.php";

$db = new DB_Functions();

if (!Util::hasAuthorizationHeader()) {
    http_response_code(401);
    doSendResponse(true, false, "You are not authorized");
}

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(405);
    doSendResponse(true, false, "Request method GET is not supported.");
}
$jsonStr = trim(file_get_contents("php://input"));

$payLoadArray = json_decode($jsonStr, true);

if (json_last_error() != JSON_ERROR_NONE) {
    http_response_code(400);
    doSendResponse(true, false, "Invalid or malformed JSON.");
}

if (!isset($payLoadArray["photo_id"])) {
    http_response_code(400);
    doSendResponse(true, false, "There was a missing or invalid parameter.");
}

if (trim($payLoadArray["photo_id"]) == "") {
    http_response_code(400);
    doSendResponse(true, false, "There was a missing or invalid parameter value.");
}

$photoId = $payLoadArray["photo_id"];

$accessToken = trim($_SERVER["HTTP_AUTHORIZATION"]);

if (($uniqueId = $db->decodeAccessToken($accessToken)) == null) {
    http_response_code(401);
    doSendResponse(true, false, "There was a problem in verifying access token or a invalid access token provided.");
}

if (!$db->isUserExists($uniqueId)) {
    doSendResponse(true, false, "No user found.");
}

if ($db->isPhotoReportedByUser($photoId, $uniqueId)) {
    doSendResponse(false, true, "You already reported the photo.");
} else {
    if ($db->reportPhoto($photoId, $uniqueId)) {
        doSendResponse(false, false, "You reported the photo. ");
    } else {
        doSendResponse(true, false, "Server Error!");
    }
}

function doSendResponse($error, $alreadyReported, $msg)
{
    header('Content-Type: application/json');
    echo json_encode(array("error" => $error, "already_reported" => $alreadyReported, "msg" => $msg));
    exit();
}