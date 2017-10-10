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

if (!isset($payLoadArray["old_number"]) || !isset($payLoadArray["new_number"])) {
    http_response_code(400);
    doSendResponse(true, "There was a missing or invalid parameter.");
}

if (trim($payLoadArray["old_number"]) == "" || trim($payLoadArray["old_number"]) == "") {
    http_response_code(400);
    doSendResponse(true, "There was a missing or invalid parameter value.");
}

$oldNumber = $payLoadArray["old_number"];
$newNumber = $payLoadArray["new_number"];

$accessToken = trim($_SERVER["HTTP_AUTHORIZATION"]);

if (($uniqueId = $db->decodeAccessToken($accessToken)) == null) {
    http_response_code(401);
    doSendResponse(true, "There was a problem in verifying access token or a invalid access token provided.");
}

if (!$db->isUserExists($uniqueId)) {
    doSendResponse(true, "No user found.");
}

if (!preg_match("~^[789]\d{9}$~", $newNumber)) {
    doSendResponse(false, "Invalid phone number.");
}

if ($db->updateUserPhoneNumber($uniqueId, $oldNumber, $newNumber)) {
    doSendResponse(false, "Phone number updated successfully.");
} else {
    doSendResponse(true, "An error occurred while trying to updating your existing number.");
}

function doSendResponse($error, $msg)
{
    header('Content-Type: application/json');
    echo json_encode(array("error" => $error, "msg" => $msg));
    exit();
}