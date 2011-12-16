SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- --------------------------------------------------------

--
-- Table structure for table `generations`
--

CREATE TABLE IF NOT EXISTS `generations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `key` char(6) NOT NULL,
  `generated` datetime NOT NULL,
  `position` text NOT NULL,
  `change` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lock`
--

CREATE TABLE IF NOT EXISTS `lock` (
  `lockdate` datetime NOT NULL,
  PRIMARY KEY (`lockdate`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `key` varchar(32) NOT NULL,
  `expires` date NOT NULL,
  `liferemaining` int(11) NOT NULL DEFAULT '50',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
