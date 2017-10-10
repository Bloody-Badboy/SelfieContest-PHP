<?php

class DB_Functions
{
    public $baseUrl = "http://rtss-prod.esy.es";
    private $conn;
    private $hash_secret_key = "07A991BB96B249E5";

    function __construct()
    {
        require_once 'DB_Connect.php';
        require_once 'Util.php';
        $db = new DB_Connect();
        $this->conn = $db->connect();
    }

    function __destruct()
    {

    }

    public function isPhotoLikedByUser($photoId, $uniqueId)
    {
        $isLiked = false;

        $photoId = $this->doSanitizeContents($photoId);

        $stmt = $this->conn->prepare("SELECT `photo_liked_by` FROM `user_photos` WHERE `photo_id` = ?");
        $stmt->bind_param("s", $photoId);
        $stmt->bind_result($photoLikedBy);
        $result = $stmt->execute();
        if ($result) {
            if ($stmt->fetch()) {
                $stmt->close();
                if (trim($photoLikedBy) == "") {
                    $photoLikedBy = "[]";
                }
                $likedByArray = json_decode($photoLikedBy, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $isLiked = in_array(trim($uniqueId), $likedByArray);
                }
            }
        }

        return $isLiked;
    }

    public function isPhotoReportedByUser($photoId, $uniqueId)
    {
        $isReported = false;

        $photoId = $this->doSanitizeContents($photoId);

        $stmt = $this->conn->prepare("SELECT `photo_reported_by` FROM `user_photos` WHERE `photo_id` = ?");
        $stmt->bind_param("s", $photoId);
        $stmt->bind_result($photoReportedBy);
        $result = $stmt->execute();
        if ($result) {
            if ($stmt->fetch()) {
                $stmt->close();
                if (trim($photoReportedBy) == "") {
                    $photoReportedBy = "[]";
                }
                $reportedByArray = json_decode($photoReportedBy, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    $isReported = in_array(trim($uniqueId), $reportedByArray);
                }
            }
        }

        return $isReported;
    }

    private function doSanitizeContents($contents)
    {
        if ($contents == null)
            return $contents;
        $contents = preg_replace(array("~<script[^>]*?>.*?</script>~si", "~<[/!]*?[^<>]*?>~si", "~<style[^>]*?>.*?</style>~", "~<![\s\S]*?--[\t\n\r]*>~"), "", trim($contents));
        return mysqli_real_escape_string($this->conn, $contents);
    }

    public function addNewPhoto($uniqueUserId, $photoName, $uploadTime)
    {
        $uniqueUserId = $this->doSanitizeContents($uniqueUserId);
        $photoName = $this->doSanitizeContents($photoName);
        $photoId = $this->calculatePhotoID($uniqueUserId, $photoName);

        $stmt = $this->conn->prepare("INSERT INTO `user_photos` (`photo_id`, `photo_name`, `uploaded_by`, `upload_time`) VALUES (?, ?, ?, ?);");
        $stmt->bind_param("sssd", $photoId, $photoName, $uniqueUserId, $uploadTime);
        $result = $stmt->execute();
        $stmt->close();

        return $result;
    }

    function calculatePhotoID($uniqueId, $photoName)
    {
        return hash_hmac("md5", $uniqueId . $photoName, $this->hash_secret_key);
    }

    public function getUserName($uniqueUserId)
    {
        $uniqueUserId = $this->doSanitizeContents($uniqueUserId);

        $stmt = $this->conn->prepare("SELECT `user_name` FROM `users` WHERE `user_unique_id` = ?");
        $stmt->bind_param("s", $uniqueUserId);
        $stmt->bind_result($userName);
        $result = $stmt->execute();
        if ($result) {
            if ($stmt->fetch()) {
                $stmt->close();
                return $userName;
            }
        }
        return null;
    }

    public function getPhotoDetails($photoId)
    {
        $photoId = $this->doSanitizeContents($photoId);
        $photoDetailsArray = null;

        $stmt = $this->conn->prepare("SELECT `uploaded_by`, `upload_time` , `total_like_count` FROM `user_photos` WHERE `photo_id` = ?");
        $stmt->bind_param("s", $photoId);
        $stmt->bind_result($uploadBy, $uploadTime, $photoLikeCount);
        $result = $stmt->execute();
        if ($result) {
            if ($stmt->fetch()) {
                $stmt->close();
                $photoDetailsArray = array($uploadBy, $uploadTime, $photoLikeCount);
            }
        }

        return $photoDetailsArray;
    }

    public function reportPhoto($photoId, $uniqueId)
    {
        $photoId = $this->doSanitizeContents($photoId);
        $uniqueId = $this->doSanitizeContents($uniqueId);

        $stmt = $this->conn->prepare("SELECT `photo_reported_by` FROM `user_photos` WHERE `photo_id` = ?");
        $stmt->bind_param("s", $photoId);
        $stmt->bind_result($photoReportedBy);
        $result = $stmt->execute();

        if ($result) {
            if ($stmt->fetch()) {
                $stmt->close();
            }
            if (trim($photoReportedBy) == "") {
                $photoReportedBy = "[]";
            }
            $photoReportedByArray = json_decode($photoReportedBy, true);

            if (json_last_error() == JSON_ERROR_NONE) {
                if (!in_array($uniqueId, $photoReportedByArray)) {
                    array_push($photoReportedByArray, $uniqueId);
                    $newPhotoReportedBy = json_encode($photoReportedByArray);

                    $stmt = $this->conn->prepare("UPDATE `user_photos` SET `photo_reported_by` = ? WHERE `photo_id` = ?");
                    $stmt->bind_param("ss", $newPhotoReportedBy, $photoId);
                    $result = $stmt->execute();
                    $stmt->close();
                    return $result;
                }
            }
        }
        return false;
    }

    public function addRemovePhotoLike($photoId, $uniqueId, $addLike)
    {
        $photoId = $this->doSanitizeContents($photoId);
        $uniqueId = $this->doSanitizeContents($uniqueId);

        $likeAdded = false;
        $likeRemoved = false;

        $stmt = $this->conn->prepare("SELECT `photo_liked_by`,`uploaded_by` FROM `user_photos` WHERE `photo_id` = ?");
        $stmt->bind_param("s", $photoId);
        $stmt->bind_result($photoLikedBy, $uploadedBy);
        $result = $stmt->execute();

        if ($result) {
            if ($stmt->fetch()) {
                $stmt->close();

                if ($uploadedBy == "" || !$this->isUserExists($uploadedBy)) {
                    return false;
                }

                if (trim($photoLikedBy) == "") {
                    $photoLikedBy = "[]";
                }

                $photoLikedByArray = json_decode($photoLikedBy, true);

                if (json_last_error() == JSON_ERROR_NONE) {
                    if ($addLike) {
                        if (!in_array($uniqueId, $photoLikedByArray)) {
                            array_push($photoLikedByArray, $uniqueId);
                            $likeAdded = true;
                        }
                    } else {
                        if (($key = array_search($uniqueId, $photoLikedByArray)) !== false) {
                            unset($photoLikedByArray[$key]);
                            $likeRemoved = true;
                        }
                    }

                    $newPhotoLikedByJson = json_encode($photoLikedByArray);

                    if ($likeAdded) {
                        $queryUserPhotos = "UPDATE `user_photos` SET `photo_liked_by` = ?, `total_like_count` = `total_like_count` + 1 WHERE `photo_id` = ?";
                        $queryUsers = "UPDATE `users` SET `user_like_count` = `user_like_count` + 1 WHERE `user_unique_id` = ?";
                    } else if ($likeRemoved) {
                        $queryUserPhotos = "UPDATE `user_photos` SET `photo_liked_by` = ? , `total_like_count` = `total_like_count` - 1 WHERE `photo_id` = ?";
                        $queryUsers = "UPDATE `users` SET `user_like_count` = `user_like_count` - 1 WHERE `user_unique_id` = ?";
                    } else {
                        return false;
                    }

                    $stmt = $this->conn->prepare($queryUserPhotos);
                    $stmt->bind_param("ss", $newPhotoLikedByJson, $photoId);
                    $result = $stmt->execute();
                    $stmt->close();

                    if ($result) {
                        $stmt = $this->conn->prepare($queryUsers);
                        $stmt->bind_param("s", $uploadedBy);
                        $result = $stmt->execute();
                        $stmt->close();
                    }
                    return $result;
                }
            }
        }
        return false;
    }

    public function isUserExists($uniqueID)
    {
        $isExists = false;
        $uniqueID = $this->doSanitizeContents($uniqueID);
        $stmt = $this->conn->prepare("SELECT * FROM `users` WHERE `user_unique_id` = ?");
        $stmt->bind_param("s", $uniqueID);
        $result = $stmt->execute();
        if ($result) {
            $stmt->store_result();
            $result_row_num = $stmt->num_rows;
            if ($result_row_num > 0) {
                $isExists = true;
            }
        }
        $stmt->close();
        return $isExists;
    }

    public function getUploadedPhotos($limit, $offset)
    {
        $uploads = array();
        $stmt = $this->conn->prepare("SELECT `photo_name`, `photo_id`, `uploaded_by`, `upload_time` , `total_like_count` FROM `user_photos` ORDER BY `upload_time` DESC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->bind_result($photoName, $photoId, $uploadBy, $uploadTime, $photoLikeCount);
        $result = $stmt->execute();
        if ($result) {
            while ($stmt->fetch()) {
                array_push($uploads, array($photoName, $photoId, $uploadBy, $uploadTime, $photoLikeCount));
            }
            $stmt->close();
        }
        return $uploads;
    }

    public function updateUserUploadDetails($uniqueID, $photoName)
    {

        $uniqueID = $this->doSanitizeContents($uniqueID);
        $photoName = $this->doSanitizeContents($photoName);

        $stmt = $this->conn->prepare("SELECT `user_uploads` FROM `users` WHERE `user_unique_id` = ?");
        $stmt->bind_param("s", $uniqueID);
        $stmt->bind_result($userUploads);
        $result = $stmt->execute();
        if ($result) {
            if ($stmt->fetch()) {
                $stmt->close();
                if (trim($userUploads) == "") {
                    $userUploads = "[]";
                }

                $uploadsArray = json_decode($userUploads, true);
                if (json_last_error() == JSON_ERROR_NONE) {
                    array_push($uploadsArray, $photoName);
                    $newUserUploadsJson = json_encode($uploadsArray);

                    $stmt = $this->conn->prepare("UPDATE `users` SET `user_uploads` = ?,`user_upload_count` = `user_upload_count` + 1 WHERE `user_unique_id` = ?");
                    $stmt->bind_param("ss", $newUserUploadsJson, $uniqueID);
                    $result = $stmt->execute();
                    $stmt->close();
                }
            }
        }
        return $result;
    }

    public function updateUserPhoneNumber($uniqueID, $oldNumber, $newNumber)
    {

        $uniqueID = $this->doSanitizeContents($uniqueID);
        $oldNumber = $this->doSanitizeContents($oldNumber);
        $newNumber = $this->doSanitizeContents($newNumber);

        $stmt = $this->conn->prepare("UPDATE `users` SET `user_mobile` = ? WHERE `user_unique_id` = ? AND `user_mobile` = ?");
        $stmt->bind_param("sss", $newNumber, $uniqueID, $oldNumber);
        $result = $stmt->execute();
        if ($result) {
            $result = $stmt->affected_rows > 0;
            $stmt->close();
            return $result;
        }

        return false;
    }

    public function registerNewUser($facebookId, $facebookToken, $deviceId, $deviceName, $deviceVersion, $userEmail, $userGender, $userHometown, $userName, $userBirthday, $userMobile)
    {
        $facebookId = $this->doSanitizeContents($facebookId);
        $deviceId = $this->doSanitizeContents($deviceId);
        $deviceName = $this->doSanitizeContents($deviceName);
        $deviceVersion = $this->doSanitizeContents($deviceVersion);
        $userGender = $this->doSanitizeContents($userGender);
        $userHometown = $this->doSanitizeContents($userHometown);
        $userEmail = $this->doSanitizeContents($userEmail);
        $userName = $this->doSanitizeContents($userName);
        $userBirthday = $this->doSanitizeContents($userBirthday);
        $userMobile = $this->doSanitizeContents($userMobile);

        $userUniqueId = $this->calculateUniqueID($facebookId, $userEmail);

        if ($this->isUserExists($userUniqueId)) {
            $stmt = $this->conn->prepare("UPDATE `users` SET `fb_access_token` = ?, `device_id` = ?, `device_name` = ?, `device_version` = ?, `user_mobile` = ? WHERE `user_unique_id` = ?");
            $stmt->bind_param("ssssss", $facebookToken, $deviceId, $deviceName, $deviceVersion, $userMobile, $userUniqueId);
            $result = $stmt->execute();
            if ($result) {
                $result = $stmt->affected_rows > 0;
            }
            $stmt->close();
        } else {
            $stmt = $this->conn->prepare("INSERT INTO `users` (`fb_id`, `fb_access_token`, `device_id`, `device_name`, `device_version`, `user_unique_id`, `user_email`, `user_name`, `user_gender`, `user_hometown`, `user_birthday`, `user_mobile`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
            $stmt->bind_param("ssssssssssss", $facebookId, $facebookToken, $deviceId, $deviceName, $deviceVersion, $userUniqueId, $userEmail, $userName, $userGender, $userHometown, $userBirthday, $userMobile);
            $result = $stmt->execute();
            $stmt->close();
        }
        return $result;
    }

    function calculateUniqueID($fb_id, $user_email)
    {
        return hash_hmac("md5", $fb_id . $user_email, $this->hash_secret_key);
    }

    public
    function getUserDetails($uniqueId)
    {
        $uniqueId = $this->doSanitizeContents($uniqueId);
        $userDetailsArray = null;

        $stmt = $this->conn->prepare("SELECT `user_mobile`,`user_uploads`,`user_upload_count`, `user_like_count` FROM `users` WHERE `user_unique_id` = ?");
        $stmt->bind_param("s", $uniqueId);
        $stmt->bind_result($userMobile, $userUploads, $userUploadCount, $userLikeCount);
        $result = $stmt->execute();
        if ($result) {
            if ($stmt->fetch()) {
                $stmt->close();
                if (trim($userUploads) == "") {
                    $userUploads = "[]";
                }
                $userDetailsArray = array($userMobile, $userUploads, $userUploadCount, $userLikeCount);
            }
        }

        return $userDetailsArray;
    }

    function encodeAccessToken($fb_id, $user_email)
    {
        $payLoad = json_encode(array("fb_id" => $fb_id, "user_email" => $user_email));
        $signature = hash_hmac("sha256", $payLoad, $this->hash_secret_key, true);
        return Util::urlSafeB64Encode($payLoad) . "." . Util::urlSafeB64Encode($signature);
    }

    function decodeAccessToken($accessToken)
    {
        $tokens = explode(".", $accessToken);
        if (count($tokens) != 2) {
            return null;
        }

        list($payLoadJson, $signature) = $tokens;

        $payLoadJson = Util::urlSafeB64Decode($payLoadJson);

        $payLoadArray = json_decode($payLoadJson, true);

        if (json_last_error() != JSON_ERROR_NONE) {
            return null;
        }

        if (count($payLoadArray) != 2 || !isset($payLoadArray["fb_id"]) || !isset($payLoadArray["user_email"])) {
            return null;
        }

        if (trim($payLoadArray["fb_id"] == "") || trim($payLoadArray["user_email"] == "")) {
            return null;
        }

        if (Util::urlSafeB64Decode($signature) != hash_hmac("sha256", $payLoadJson, $this->hash_secret_key, true)) {
            return null;
        }

        return $this->calculateUniqueID($payLoadArray["fb_id"], $payLoadArray["user_email"]);
    }
}
