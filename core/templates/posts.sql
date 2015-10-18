CREATE TABLE IF NOT EXISTS `posts_{{ board }}` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread` int(11) DEFAULT NULL,
  `subject` varchar(100) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `name` varchar(75) DEFAULT NULL,
  `trip` varchar(25) DEFAULT NULL,
  `capcode` varchar(50) DEFAULT NULL,
  `body` text NOT NULL,
  `body_nomarkup` text DEFAULT NULL,
  `post_modifiers` text DEFAULT NULL COMMENT
    'optional json object describing changes to how the post is rendered or marked up',
  `time` int(11) NOT NULL,
  `bump` int(11) DEFAULT NULL,
  `thumb` varchar(50) DEFAULT NULL,
  `thumb_uri` varchar(255) DEFAULT NULL COMMENT 'Temporary override for remote images',
  `thumbwidth` int(11) DEFAULT NULL,
  `thumbheight` int(11) DEFAULT NULL,
  `file` varchar(50) DEFAULT NULL,
  `file_uri` varchar(255) DEFAULT NULL COMMENT 'Temporary override for remote images',
  `filewidth` int(11) DEFAULT NULL,
  `fileheight` int(11) DEFAULT NULL,
  `filesize` int(11) DEFAULT NULL,
  `filename` text DEFAULT NULL,
  `filehash` text DEFAULT NULL,
  `password` char(40) DEFAULT NULL,
  `userhash` char(40) DEFAULT NULL,
  `ip_type` int(11) NOT NULL COMMENT '0:ipv4, 1:ipv6',
  `ip_data` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
  `sticky` int(1) NOT NULL,
  `locked` int(1) NOT NULL,
  `sage` int(1) NOT NULL,
  `embed` text,
  `mature` int(1) NOT NULL,
  UNIQUE KEY `id` (`id`),
  KEY `thread_id` (`thread`, `id`),
  KEY `userhash` (`userhash`),
  KEY `ip_type_data` (`ip_type`, `ip_data`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1 ;
