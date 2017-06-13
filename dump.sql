
DROP TABLE IF EXISTS `mychat_users`;
CREATE TABLE `mychat_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(45) CHARACTER SET utf8 DEFAULT NULL,
  `mycookid` int(11) DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `login` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `password` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `role` varchar(45) CHARACTER SET utf8 DEFAULT NULL,
  `created` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `mychat_dialogs`;
CREATE TABLE `mychat_dialogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created` int(11) DEFAULT NULL,
  `closed` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `mychat_messages`;
CREATE TABLE `mychat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dialog_id` int(11) DEFAULT NULL,
  `from_user_id` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `type` varchar(45) CHARACTER SET utf8 DEFAULT NULL,
  `body` longtext CHARACTER SET utf8,
  `created` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
