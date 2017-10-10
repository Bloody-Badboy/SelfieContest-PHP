CREATE TABLE users
(
  `_id`             INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  fb_id             TEXT                NOT NULL,
  fb_access_token   TEXT                NOT NULL,
  device_id         TEXT                NOT NULL,
  device_name       TEXT                NOT NULL,
  device_version    TEXT                NOT NULL,
  user_unique_id    TEXT                NOT NULL,
  user_email        TEXT                NOT NULL,
  user_name         TEXT                NOT NULL,
  user_gender       TEXT                NOT NULL,
  user_hometown     TEXT                NOT NULL,
  user_birthday     TEXT                NOT NULL,
  user_mobile       TEXT                NOT NULL,
  user_upload_count INT(11) DEFAULT '0' NOT NULL,
  user_uploads      TEXT                NOT NULL,
  user_like_count   INT(11) DEFAULT '0' NOT NULL
)
  ENGINE = InnoDB;

CREATE TABLE user_photos
(
  `_id`             INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
  photo_id          TEXT                NOT NULL,
  photo_name        TEXT                NOT NULL,
  uploaded_by       TEXT                NOT NULL,
  upload_time       DOUBLE              NOT NULL,
  photo_liked_by    TEXT                NOT NULL,
  total_like_count  INT(11) DEFAULT '0' NOT NULL,
  photo_reported_by TEXT                NOT NULL
)
  ENGINE = InnoDB;

DROP TABLE IF EXISTS `users`;

DROP TABLE IF EXISTS `user_photos`;
