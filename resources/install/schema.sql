# ************************************************************
# Sequel Pro SQL dump
# Version 4008
#
# http://www.sequelpro.com/
# http://code.google.com/p/sequel-pro/
#
# Host: 127.0.0.1 (MySQL 5.5.25)
# Database: nooku-12-3
# Generation Time: 2013-02-11 17:02:18 +0000
# ************************************************************


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


# Dump of table router_aliases
# ------------------------------------------------------------

DROP TABLE IF EXISTS `router_aliases`;

CREATE TABLE `router_aliases` (
  `alias_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(255) NOT NULL DEFAULT '',
  `target` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`alias_id`),
  UNIQUE KEY `url` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


# Dump of table router_routes
# ------------------------------------------------------------

DROP TABLE IF EXISTS `router_routes`;

CREATE TABLE `router_routes` (
  `router_route_id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `component` varchar(30) NOT NULL DEFAULT '',
  `view` varchar(30) DEFAULT NULL,
  `query` varchar(255) NOT NULL DEFAULT '',
  `route` varchar(255) DEFAULT '',
  `page_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`router_route_id`),
  UNIQUE KEY `route` (`route`),
  UNIQUE KEY `component` (`component`,`view`,`query`),
  KEY `page_id` (`page_id`),
  CONSTRAINT `router_routes.page_id` FOREIGN KEY (`page_id`) REFERENCES `pages` (`pages_page_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;




/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
