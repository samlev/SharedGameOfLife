SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- --------------------------------------------------------

--
-- Table structure for table `generations`
--

CREATE TABLE IF NOT EXISTS `generations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `key` char(6) NOT NULL,
  `generated` datetime NOT NULL,
  `millisecond` double NOT NULL,
  `position` longtext NOT NULL,
  `change` longtext NOT NULL,
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
  `key` char(32) NOT NULL,
  `expires` datetime NOT NULL,
  `liferemaining` int(11) NOT NULL DEFAULT '50',
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `waitinglife`
--

CREATE TABLE IF NOT EXISTS `waitinglife` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `session` int(11) NOT NULL,
  `type` enum('single','block','glider','lwss','pulsar') NOT NULL,
  `orientation` enum('N','E','S','W') NOT NULL DEFAULT 'N',
  `xpos` int(11) NOT NULL,
  `ypos` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;