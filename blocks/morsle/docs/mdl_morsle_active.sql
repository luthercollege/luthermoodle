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
-- Table structure for table `mdl_morsle_active`
--

CREATE TABLE IF NOT EXISTS `mdl_morsle_active` (
  `id` bigint(10) unsigned NOT NULL AUTO_INCREMENT,
  `courseid` bigint(10) unsigned NOT NULL,
  `shortname` varchar(255) COLLATE utf8_unicode_ci DEFAULT 'null',
  `status` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created` bigint(12) unsigned NOT NULL DEFAULT '0',
  `updated` bigint(12) unsigned NOT NULL DEFAULT '0',
  `readfolderid` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `writefolderid` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `groupname` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `siteid` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mdl_morsacti_cou_uix` (`courseid`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='records of courses that have added the morsle block' AUTO_INCREMENT=19 ;
