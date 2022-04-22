CREATE DATABASE malldemodb;
use malldemodb;
CREATE TABLE `mall_sessions` (
  `room_name` varchar(20) NOT NULL,
  `meetingPW` varchar(50) DEFAULT NULL,
  `attendeePW` varchar(50) DEFAULT NULL,
  `moderatorPW` varchar(50) DEFAULT NULL,
  `createTime` bigint DEFAULT NULL,
  `num_of_participants` int DEFAULT NULL,
  `meetingID` varchar(50) NOT NULL,
  PRIMARY KEY (`room_name`),
  UNIQUE KEY `meetingID_UNIQUE` (`meetingID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `malldemodb`.`bbb_secrets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `url` VARCHAR(255) NOT NULL,
  `secret_key` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `url_UNIQUE` (`url` ASC) VISIBLE)
COMMENT = 'Table to store the secret codes for BBB servers.';

-- Insert a records for test and production servers
INSERT INTO `malldemodb`.`bbb_secrets` (`url`, `secret_key`) VALUES ('https:/mybigbluebutton.net/test/api/', 'BBB Secret Key for test server');
INSERT INTO `malldemodb`.`bbb_secrets` (`url`, `secret_key`) VALUES ('https://mybigbluebutton.net/prod/api/', 'BBB Secret Key for production server');


