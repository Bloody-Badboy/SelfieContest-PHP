<?php

require_once dirname(__DIR__) . "/include/DB_Functions.php";

$db = new DB_Functions();

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    http_response_code(405);
    doSendResponse(true, false, null, null, "Request method GET is not supported.");
}

$jsonData = trim(file_get_contents("php://input"));

$dataArray = json_decode($jsonData, true);


if (json_last_error() != JSON_ERROR_NONE) {
    http_response_code(400);
    doSendResponse(true, false, null, null, "Invalid or malformed JSON.");
}

if (!array_key_exists("fb_id", $dataArray) ||
    !array_key_exists("fb_token", $dataArray) ||
    !array_key_exists("device_id", $dataArray) ||
    !array_key_exists("device_name", $dataArray) ||
    !array_key_exists("device_version", $dataArray) ||
    !array_key_exists("user_email", $dataArray) ||
    !array_key_exists("user_name", $dataArray) ||
    !array_key_exists("user_gender", $dataArray) ||
    !array_key_exists("user_hometown", $dataArray) ||
    !array_key_exists("user_birthday", $dataArray) ||
    !array_key_exists("user_mobile", $dataArray)
) {
    http_response_code(400);
    doSendResponse(true, false, null, null, "There was a missing or invalid parameter.");
}

$facebookId = $dataArray["fb_id"];
$facebookToken = $dataArray["fb_token"];
$deviceId = $dataArray["device_id"];
$deviceName = $dataArray["device_name"];
$deviceVersion = $dataArray["device_version"];
$userEmail = $dataArray["user_email"];
$userName = $dataArray["user_name"];
$userGender = $dataArray["user_gender"];
$userHometown = $dataArray["user_hometown"];
$userBirthday = $dataArray["user_birthday"];
$userMobile = $dataArray["user_mobile"];

if (trim($facebookId) == "" ||
    trim($facebookToken) == "" ||
    trim($deviceId) == "" ||
    trim($deviceName) == "" ||
    trim($deviceVersion) == "" ||
    trim($userEmail) == "" ||
    trim($userName) == "" ||
    trim($userGender) == "" ||
    trim($userHometown) == "" ||
    trim($userBirthday) == "" ||
    trim($userMobile) == ""
) {
    http_response_code(400);
    doSendResponse(true, false, null, null, "There was a missing or invalid parameter value.");
}


$uniqueId = $db->calculateUniqueID($facebookId, $userEmail);
$accessToken = $db->encodeAccessToken($facebookId, $userEmail);
$oldUser = $db->isUserExists($uniqueId);

if ($db->registerNewUser($facebookId, $facebookToken, $deviceId, $deviceName, $deviceVersion, $userEmail, $userGender, $userHometown, $userName, $userBirthday, $userMobile)) {
    doSendResponse(false, $oldUser, $uniqueId, $accessToken, "User registered successfully.");
} else {
    doSendResponse(true, false, null, null, "Failed to register new user.");
}

function doSendResponse($error, $already_registered, $unique_id, $access_token, $msg)
{
    header('Content-Type: application/json');
    echo json_encode(array("error" => $error, "old_user" => $already_registered, "unique_id" => $unique_id, "access_token" => $access_token, "msg" => $msg));
    exit();
}