CREATE TABLE `state_day` (
  `senderId` varchar(255) NOT NULL,
  `data` longtext,
  `timestamp` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `state_location` (
  `senderId` varchar(255) NOT NULL,
  `data` longtext,
  `timestamp` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
