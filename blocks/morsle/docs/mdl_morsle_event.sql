-- phpMyAdmin SQL Dump
-- version 3.3.9
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jul 01, 2011 at 07:20 AM
-- Server version: 5.1.54
-- PHP Version: 5.3.5

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `lae113dev`
--

-- --------------------------------------------------------

--
-- Table structure for table `mdl_morsle_event`
--

CREATE TABLE IF NOT EXISTS `mdl_morsle_event` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `eventid` int(11) DEFAULT NULL,
  `googleid` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `description` text CHARACTER SET utf8 NOT NULL,
  `format` smallint(4) unsigned NOT NULL DEFAULT '0',
  `courseid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `shortname` varchar(255) CHARACTER SET utf8 NOT NULL,
  `groupid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `userid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `repeatid` bigint(10) unsigned NOT NULL DEFAULT '0',
  `modulename` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `instance` bigint(10) unsigned NOT NULL DEFAULT '0',
  `eventtype` varchar(20) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `timestart` bigint(10) unsigned NOT NULL DEFAULT '0',
  `timeduration` bigint(10) unsigned NOT NULL DEFAULT '0',
  `visible` smallint(4) NOT NULL DEFAULT '1',
  `uuid` varchar(36) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `sequence` bigint(10) unsigned NOT NULL DEFAULT '1',
  `timemodified` bigint(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `mdl_even_grocouvisuse_ix` (`groupid`,`courseid`,`visible`,`userid`),
  KEY `mdl_even_cou_ix` (`courseid`),
  KEY `mdl_even_use_ix` (`userid`),
  KEY `mdl_even_tim_ix` (`timestart`),
  KEY `mdl_even_tim2_ix` (`timeduration`),
  KEY `shortname` (`shortname`),
  KEY `googleid` (`googleid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='For everything with a time associated to it' AUTO_INCREMENT=65 ;
