<?php

// Installation/upgrade file
define('VERSION', 'v0.9.6-dev-8-ponychan-10');

require 'inc/functions.php';
header("Cache-Control: private");

$step = isset($_GET['step']) ? round($_GET['step']) : 0;
$page = array(
	'config' => $config,
	'title' => 'Install',
	'body' => '',
	'nojavascript' => true
);

// this breaks the dispaly of licenses if enabled
$config['minify_html'] = false;

// New migration procedures. The benefit is that these migrations don't have
// to be applied in a specific order. It's easier to merge branches together
// that each introduce their own necessary migrations. Add new migrations
// here!
$migration_procedures = [
	'1-example' => function() {
		// Example migration procedure.
		//query("ALTER TABLE `ip_notes` DROP COLUMN `ip`") or error(db_error());
	},
	'2-filename-dehtml' => function() {
		global $boards;
		foreach ($boards as $board) {
			query("UPDATE `posts_${board['uri']}` SET `filename`=REPLACE(REPLACE(REPLACE(REPLACE(`filename`, '&gt;', '>'), '&lt;', '<'), '&amp;', '&'), '%22', '\"') WHERE filename like '%&%' OR filename like '%\%%'") or error(db_error());
		}
	},
	'3-userhash' => function() {
		global $boards;
		foreach ($boards as $board) {
			query("ALTER TABLE `posts_${board['uri']}`
				ADD `userhash` char(40) DEFAULT NULL,
				ADD KEY `userhash` (`userhash`)") or error(db_error());
		}
	},
	'review-queue' => function() {
		query("CREATE TABLE IF NOT EXISTS `review_queue` (
			`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			`ip_type` int(11) NOT NULL COMMENT '0:ipv4, 1:ipv6',
			`ip_data` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
			`board` varchar(120) NOT NULL,
			`thread` int(11) DEFAULT NULL,
			`subject` varchar(100) DEFAULT NULL,
			`email` varchar(254) DEFAULT NULL,
			`name` varchar(75) DEFAULT NULL,
			`trip` varchar(25) DEFAULT NULL,
			`capcode` varchar(50) DEFAULT NULL,
			`body_nomarkup` text DEFAULT NULL,
			`file` varchar(50) DEFAULT NULL,
			`filename` text DEFAULT NULL,
			`filehash` text DEFAULT NULL,
			`password` char(40) DEFAULT NULL,
			`userhash` char(40) DEFAULT NULL,
			`rawhtml` int(1) NOT NULL,
			`spoiler` int(1) NOT NULL,
			`mature` int(1) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `board_thread_time` (`board`, `thread`, `timestamp`),
			KEY `userhash` (`userhash`),
			KEY `ip_type_data` (`ip_type`, `ip_data`),
			KEY `timestamp` (`timestamp`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
	},
	'db-filters' => function() {
		query("CREATE TABLE IF NOT EXISTS `captchas` (
			`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
			`timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			`question` text NOT NULL,
			`answers` text NOT NULL COMMENT 'json array',
			PRIMARY KEY (`id`),
			KEY `timestamp` (`timestamp`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("INSERT INTO `captchas` (`question`, `answers`) VALUES
			('Please type the word \"apple\".', '[\"apple\"]'),
			('What is 3+5?', '[\"8\",\"eight\"]')") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `captcha_attempts` (
			`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
			`timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			`userhash` char(40) DEFAULT NULL,
			`ip_type` int NOT NULL COMMENT '0:ipv4, 1:ipv6',
			`ip_data` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
			`captcha_id` int UNSIGNED DEFAULT NULL,
			`correct` int(1) NOT NULL,
			`answer` varchar(75) NOT NULL,
			PRIMARY KEY (`id`),
			KEY `userhash_time` (`userhash`, `timestamp`),
			KEY `ip_type_data` (`ip_type`, `ip_data`, `timestamp`),
			KEY `captcha_time` (`captcha_id`, `timestamp`),
			KEY `timestamp` (`timestamp`),
			FOREIGN KEY (captcha_id)
				REFERENCES captchas(id)
				ON DELETE SET NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `post_filters` (
			`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
			`filter_json` text NOT NULL,
			`mode` int(1) NOT NULL COMMENT '0:disable, 1:audit, 2:enforce',
			PRIMARY KEY (`id`),
			KEY `mode` (`mode`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `post_filter_hits` (
			`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
			`timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			`userhash` char(40) DEFAULT NULL,
			`ip_type` int NOT NULL COMMENT '0:ipv4, 1:ipv6',
			`ip_data` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
			`filter_id` int UNSIGNED DEFAULT NULL,
			`fail_step` int UNSIGNED DEFAULT NULL,
			`blocked` int NOT NULL,
			`board` varchar(120) DEFAULT NULL,
			`successful_post_id` int UNSIGNED DEFAULT NULL,
			`thread` int(11) DEFAULT NULL,
			`first_time_poster` int(1) DEFAULT NULL,
			`subject` varchar(100) DEFAULT NULL,
			`email` varchar(254) DEFAULT NULL,
			`name` varchar(75) DEFAULT NULL,
			`trip` varchar(25) DEFAULT NULL,
			`capcode` varchar(50) DEFAULT NULL,
			`filename` text DEFAULT NULL,
			`filehash` text DEFAULT NULL,
			`body_nomarkup` text DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `userhash_time` (`userhash`, `timestamp`),
			KEY `ip_type_data` (`ip_type`, `ip_data`, `timestamp`),
			KEY `timestamp` (`timestamp`),
			FOREIGN KEY (filter_id)
				REFERENCES post_filters(id)
				ON DELETE SET NULL,
			FOREIGN KEY (board)
				REFERENCES boards(uri)
				ON DELETE SET NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
	},
	'db-filters-fix' => function() {
		query("ALTER TABLE `post_filter_hits` DROP COLUMN `fail_step`, DROP COLUMN `first_time_poster`") or error(db_error());
	},
	'db-filters-fix2' => function() {
		query("ALTER TABLE `post_filters`
			ADD COLUMN `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			ADD COLUMN `author` smallint UNSIGNED DEFAULT NULL,
			ADD COLUMN `parent` int UNSIGNED DEFAULT NULL,
			ADD FOREIGN KEY (`author`)
				REFERENCES mods(`id`)
				ON DELETE SET NULL,
			ADD FOREIGN KEY (`parent`)
				REFERENCES post_filters(`id`)
				ON DELETE SET NULL
			") or error(db_error());
	},
	'db-filters-changes' => function() {
		query("CREATE TABLE IF NOT EXISTS `post_filter_changes` (
		  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
		  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `filter_id` int UNSIGNED NOT NULL,
		  `mod` smallint UNSIGNED DEFAULT NULL,
		  `old_mode` int(1) DEFAULT NULL,
		  `new_mode` int(1) DEFAULT NULL,
		  PRIMARY KEY (`id`),
		  FOREIGN KEY (filter_id)
		    REFERENCES post_filters(id)
		    ON DELETE CASCADE,
		  FOREIGN KEY (`mod`)
		    REFERENCES mods(`id`)
		    ON DELETE SET NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
	},
	'db-filters-keys' => function() {
		query("ALTER TABLE `post_filters`
			ADD KEY (`author`),
			ADD KEY (`parent`)") or error(db_error());
	},
	'email-protocol' => function() {
		global $boards;
		foreach ($boards as $board) {
			query("ALTER TABLE `posts_{$board['uri']}` ADD `email_protocol` varchar(30) DEFAULT NULL") or error(db_error());
		}
	},
	'email-dehtml' => function() {
		global $boards;
		foreach ($boards as $board) {
			query("UPDATE `posts_${board['uri']}` SET `email`=REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`email`, '&gt;', '>'), '&lt;', '<'), '&amp;', '&'), '%22', '\"'), '%20', ' ') WHERE email like '%&%' OR email like '%\%%'") or error(db_error());
		}
	},
	'poll' => function() {
		query("CREATE TABLE IF NOT EXISTS `users` (
			`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
			`uuid` char(36) NOT NULL,
			PRIMARY KEY(`id`),
			UNIQUE KEY(`uuid`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `user_ips` (
			`user_id` int UNSIGNED NOT NULL,
			`range_type` int(11) NOT NULL COMMENT '0:ipv4, 1:ipv6',
			`range_start` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
			`range_end` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
			PRIMARY KEY(`range_type`, `range_start`, `range_end`),
			FOREIGN KEY (`user_id`)
				REFERENCES users(id)
				ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `user_userhashes` (
			`user_id` int UNSIGNED NOT NULL,
			`userhash` char(40) NOT NULL,
			PRIMARY KEY(`userhash`),
			FOREIGN KEY (`user_id`)
				REFERENCES users(id)
				ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `user_tripcodes` (
			`user_id` int UNSIGNED NOT NULL,
			`trip` varchar(25) NOT NULL,
			PRIMARY KEY(`trip`),
			FOREIGN KEY (`user_id`)
				REFERENCES users(id)
				ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `polls` (
			`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
			PRIMARY KEY(`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `poll_eligible_users` (
			`poll_id` int UNSIGNED NOT NULL,
			`user_id` int UNSIGNED NOT NULL,
			PRIMARY KEY (`poll_id`, `user_id`),
			FOREIGN KEY (`poll_id`)
				REFERENCES polls(id)
				ON DELETE CASCADE,
			FOREIGN KEY (`user_id`)
				REFERENCES users(id)
				ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `poll_questions` (
			`poll_id` int UNSIGNED NOT NULL,
			`id` int UNSIGNED NOT NULL AUTO_INCREMENT,
			PRIMARY KEY (`id`),
			FOREIGN KEY (`poll_id`)
				REFERENCES polls(id)
				ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `poll_results` (
			`timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			`poll_id` int UNSIGNED NOT NULL,
			`user_id` int UNSIGNED NOT NULL,
			`question_id` int UNSIGNED NOT NULL,
			`answer` int UNSIGNED NOT NULL,
			PRIMARY KEY (`poll_id`, `user_id`, `question_id`),
			KEY (`user_id`),
			FOREIGN KEY (`poll_id`, `user_id`)
				REFERENCES poll_eligible_users(poll_id, user_id)
				ON DELETE RESTRICT,
			FOREIGN KEY (`poll_id`)
				REFERENCES polls(id)
				ON DELETE CASCADE,
			FOREIGN KEY (`poll_id`, `question_id`)
				REFERENCES poll_questions(poll_id, id)
				ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
	},
	'ban-signing' => function() {
		query('ALTER TABLE `bans`
			ADD `signed_name` VARCHAR(75) DEFAULT NULL,
			ADD `signed_trip` VARCHAR(25) DEFAULT NULL')
		or error(db_error());
	},
	'new-signatures' => function() {
		query('ALTER TABLE `mods`
			ADD `signed_name` VARCHAR(75) DEFAULT NULL,
			ADD `signed_trip` VARCHAR(25) DEFAULT NULL')
		 or error(db_error());
	},
	'mod-unique-names' => function() {
		query('ALTER TABLE `mods`
			DROP KEY `id`,
			ADD UNIQUE KEY (`username`)')
		or error(db_error());
	},
	'appeals' => function() {
		query('ALTER TABLE `bans`
			ADD `appealable` int(1) NOT NULL DEFAULT 1')
		or error(db_error());
		query('CREATE TABLE IF NOT EXISTS `ban_appeals` (
		  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `ban` int(11) UNSIGNED NOT NULL,
		  `is_user` int(1) NOT NULL,
		  `mod` smallint UNSIGNED DEFAULT NULL,
		  `name` varchar(75) DEFAULT NULL,
		  `trip` varchar(25) DEFAULT NULL,
		  `capcode` varchar(50) DEFAULT NULL,
		  `body` text NOT NULL,
		  PRIMARY KEY (`id`),
		  FOREIGN KEY (`ban`)
		    REFERENCES bans(`id`)
		    ON DELETE CASCADE,
		  FOREIGN KEY (`mod`)
		    REFERENCES mods(`id`)
		    ON DELETE SET NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1')
		or error(db_error());
	},
	'ban-range-key' => function() {
		query('ALTER TABLE `bans`
			ADD KEY `range` (`range_type`, `range_start`, `range_end`)')
		or error(db_error());
	},
	'anon-threads' => function() {
		global $boards;
		foreach ($boards as $board) {
			query(sprintf("ALTER TABLE `posts_%s` ADD `anon_thread` INT(1) NOT NULL AFTER `mature`", $board['uri'])) or error(db_error());
		}
	},
	'review-queue-uuid' => function() {
		query('ALTER TABLE `review_queue`
			ADD `uuid` char(36) NOT NULL')
		or error(db_error());
	},
	'captcha-take-two' => function() {
		query("CREATE TABLE IF NOT EXISTS `needs_captcha` (
		  `uuid` char(36) NOT NULL,
		  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `ip_type` int NOT NULL COMMENT '0:ipv4, 1:ipv6',
		  `ip_data` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
		  KEY `ip_type_data` (`ip_type`, `ip_data`, `timestamp`),
		  KEY `timestamp` (`timestamp`),
		  PRIMARY KEY (`uuid`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `solved_captcha` (
		  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
		  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `ip_type` int NOT NULL COMMENT '0:ipv4, 1:ipv6',
		  `ip_data` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
		  PRIMARY KEY (`id`),
		  KEY `ip_type_data` (`ip_type`, `ip_data`, `timestamp`),
		  KEY `timestamp` (`timestamp`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
		query("ALTER TABLE `captchas`
			ADD `enabled` int(1) NOT NULL DEFAULT 1")
		or error(db_error());
		query("DROP TABLE `review_queue`") or error(db_error());
	},
	'drop-text-captchas' => function() {
		query("DROP TABLE `captcha_attempts`") or error(db_error());
		query("DROP TABLE `captchas`") or error(db_error());
	},
	'redo-needs-captcha' => function() {
		query("DROP TABLE `needs_captcha`") or error(db_error());
		query("CREATE TABLE IF NOT EXISTS `needs_captcha` (
		  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
		  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
		  `range_type` int NOT NULL COMMENT '0:ipv4, 1:ipv6',
		  `range_start` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
		  `range_end` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data',
		  KEY `range` (`range_type`, `range_start`, `range_end`, `timestamp`),
		  KEY `timestamp` (`timestamp`),
		  PRIMARY KEY (`id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 AUTO_INCREMENT=1") or error(db_error());
	},
	'add-filetype-column' => function() {
		global $boards;
		foreach ($boards as $board) {
			query(sprintf("ALTER TABLE `posts_%s` ADD `filetype` ENUM('image', 'video', 'silentvideo', 'audio', 'file') DEFAULT NULL AFTER `file`", $board['uri'])) or error(db_error());
		}
	},
	'populate-filetype-column' => function() {
		global $boards;
		foreach ($boards as $board) {
			query(sprintf("UPDATE `posts_%s` SET `filetype`=CASE WHEN RIGHT(`file`, 4) IN ('.jpg', '.bmp', '.gif', '.png') OR RIGHT(file,5)='.jpeg' THEN 'image' WHEN RIGHT(`file`,5)='.webm' THEN 'silentvideo' ELSE NULL END WHERE `filetype` IS NULL AND `file` IS NOT NULL", $board['uri'])) or error(db_error());
		}
	}
];

if (file_exists($config['has_installed'])) {
	// Upgrading can take a while.
	set_time_limit(0);

	// Check the version number
	$version = trim(file_get_contents($config['has_installed']));
	if (empty($version))
		$version = 'v0.9.1';

	$boards = listBoards();

	// Old migration code. Add new migrations to $migration_procedures above
	// instead of making new versions here!
	switch ($version) {
		case 'v0.9':
		case 'v0.9.1':
			// Upgrade to v0.9.2-dev

			foreach ($boards as &$_board) {
				// Add `capcode` field after `trip`
				query(sprintf("ALTER TABLE `posts_%s` ADD  `capcode` VARCHAR( 50 ) NULL AFTER  `trip`", $_board['uri'])) or error(db_error());

				// Resize `trip` to 15 characters
				query(sprintf("ALTER TABLE `posts_%s` CHANGE  `trip`  `trip` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.2-dev':
			// Upgrade to v0.9.2-dev-1

			// New table: `theme_settings`
			query("CREATE TABLE IF NOT EXISTS `theme_settings` ( `name` varchar(40) NOT NULL, `value` text, UNIQUE KEY `name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;") or error(db_error());

			// New table: `news`
			query("CREATE TABLE IF NOT EXISTS `news` ( `id` int(11) NOT NULL AUTO_INCREMENT, `name` text NOT NULL, `time` int(11) NOT NULL, `subject` text NOT NULL, `body` text NOT NULL, UNIQUE KEY `id` (`id`) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;") or error(db_error());
		case 'v0.9.2.1-dev':
		case 'v0.9.2-dev-1':
			// Fix broken version number/mistake
			$version = 'v0.9.2-dev-1';
			// Upgrade to v0.9.2-dev-2

			foreach ($boards as &$_board) {
				// Increase field sizes
				query(sprintf("ALTER TABLE `posts_%s` CHANGE  `subject` `subject` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL", $_board['uri'])) or error(db_error());
				query(sprintf("ALTER TABLE `posts_%s` CHANGE  `name` `name` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.2-dev-2':
			// Upgrade to v0.9.2-dev-3 (v0.9.2)

			foreach ($boards as &$_board) {
				// Add `custom_fields` field
				query(sprintf("ALTER TABLE `posts_%s` ADD `embed` TEXT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.2-dev-3': // v0.9.2-dev-3 == v0.9.2
		case 'v0.9.2':
			// Upgrade to v0.9.3-dev-1

			// Upgrade `theme_settings` table
			query("TRUNCATE TABLE `theme_settings`") or error(db_error());
			query("ALTER TABLE  `theme_settings` ADD  `theme` VARCHAR( 40 ) NOT NULL FIRST") or error(db_error());
			query("ALTER TABLE  `theme_settings` CHANGE  `name`  `name` VARCHAR( 40 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL") or error(db_error());
			query("ALTER TABLE  `theme_settings` DROP INDEX  `name`") or error(db_error());
		case 'v0.9.3-dev-1':
			query("ALTER TABLE  `mods` ADD  `boards` TEXT NOT NULL") or error(db_error());
			query("UPDATE `mods` SET `boards` = '*'") or error(db_error());
		case 'v0.9.3-dev-2':
			foreach ($boards as &$_board) {
				query(sprintf("ALTER TABLE `posts_%s` CHANGE `filehash`  `filehash` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-3':
			// Board-specifc bans
			query("ALTER TABLE `bans` ADD  `board` SMALLINT NULL AFTER  `reason`") or error(db_error());
		case 'v0.9.3-dev-4':
			// add ban ID
			query("ALTER TABLE `bans` ADD  `id` INT NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY ( `id` ), ADD UNIQUE (`id`)");
		case 'v0.9.3-dev-5':
			foreach ($boards as &$_board) {
				// Increase subject field size
				query(sprintf("ALTER TABLE `posts_%s` CHANGE  `subject` `subject` VARCHAR( 100 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-6':
			// change to MyISAM
			$tables = array(
				'bans', 'boards', 'ip_notes', 'modlogs', 'mods', 'mutes', 'noticeboard', 'pms', 'reports', 'robot', 'theme_settings', 'news'
			);
			foreach ($boards as &$board) {
				$tables[] = "posts_{$board['uri']}";
			}

			foreach ($tables as &$table) {
				query("ALTER TABLE  `{$table}` ENGINE = MYISAM DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci") or error(db_error());
			}
		case 'v0.9.3-dev-7':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  `posts_%s` CHANGE  `filename` `filename` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL", $board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-8':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE `posts_%s` ADD INDEX (  `thread` )", $board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-9':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE `posts_%s`ADD INDEX (  `time` )", $board['uri'])) or error(db_error());
				query(sprintf("ALTER TABLE `posts_%s`ADD FULLTEXT (`body`)", $board['uri'])) or error(db_error());
			}
		case 'v0.9.3-dev-10':
		case 'v0.9.3':
			query("ALTER TABLE  `bans` DROP INDEX `id`") or error(db_error());
			query("ALTER TABLE  `pms` DROP INDEX  `id`") or error(db_error());
			query("ALTER TABLE  `boards` DROP PRIMARY KEY") or error(db_error());
			query("ALTER TABLE  `reports` DROP INDEX  `id`") or error(db_error());
			query("ALTER TABLE  `boards` DROP INDEX `uri`") or error(db_error());

			query("ALTER IGNORE TABLE  `robot` ADD PRIMARY KEY (`hash`)") or error(db_error());
			query("ALTER TABLE  `bans` ADD FULLTEXT (`ip`)") or error(db_error());
			query("ALTER TABLE  `ip_notes` ADD INDEX (`ip`)") or error(db_error());
			query("ALTER TABLE  `modlogs` ADD INDEX (`time`)") or error(db_error());
			query("ALTER TABLE  `boards` ADD PRIMARY KEY(`uri`)") or error(db_error());
			query("ALTER TABLE  `mutes` ADD INDEX (`ip`)") or error(db_error());
			query("ALTER TABLE  `news` ADD INDEX (`time`)") or error(db_error());
			query("ALTER TABLE  `theme_settings` ADD INDEX (`theme`)") or error(db_error());
		case 'v0.9.4-dev-1':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  `posts_%s` ADD  `sage` INT( 1 ) NOT NULL AFTER  `locked`", $board['uri'])) or error(db_error());
			}
		case 'v0.9.4-dev-2':
			if (!isset($_GET['confirm'])) {
				$page['title'] = 'License Change';
				$page['body'] = '<p style="text-align:center">You are upgrading to a version which uses an amended license. The licenses included with Tinyboard distributions prior to this version (v0.9.4-dev-2) are still valid for those versions, but no longer apply to this and newer versions.</p>' .
					'<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black" disabled>' . htmlentities(file_get_contents('LICENSE.md')) . '</textarea>
					<p style="text-align:center">
						<a href="?confirm=1">I have read and understood the agreement. Proceed to upgrading.</a>
					</p>';

				file_write($config['has_installed'], 'v0.9.4-dev-2');

				break;
			}
		case 'v0.9.4-dev-3':
		case 'v0.9.4-dev-4':
		case 'v0.9.4':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  `posts_%s`
					CHANGE `subject` `subject` VARCHAR( 100 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL ,
					CHANGE  `email`  `email` VARCHAR( 30 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL ,
					CHANGE  `name`  `name` VARCHAR( 35 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL", $board['uri'])) or error(db_error());
			}
		case 'v0.9.5-dev-1':
			foreach ($boards as &$board) {
				query(sprintf("ALTER TABLE  `posts_%s` ADD  `body_nomarkup` TEXT NULL AFTER  `body`", $board['uri'])) or error(db_error());
			}
			query("CREATE TABLE IF NOT EXISTS `cites` (  `board` varchar(8) NOT NULL,  `post` int(11) NOT NULL,  `target_board` varchar(8) NOT NULL,  `target` int(11) NOT NULL,  KEY `target` (`target_board`,`target`),  KEY `post` (`board`,`post`)) ENGINE=MyISAM DEFAULT CHARSET=utf8;") or error(db_error());
		case 'v0.9.5-dev-2':
			query("ALTER TABLE  `boards`
				CHANGE  `uri`  `uri` VARCHAR( 15 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
				CHANGE  `title`  `title` VARCHAR( 40 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
				CHANGE  `subtitle`  `subtitle` VARCHAR( 120 ) CHARACTER SET utf8 COLLATE utf8_general_ci NULL") or error(db_error());
		case 'v0.9.5-dev-3':
			// v0.9.5
		case 'v0.9.5':
			query("ALTER TABLE  `boards`
				CHANGE  `uri`  `uri` VARCHAR( 50 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
				CHANGE  `title`  `title` TINYTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
				CHANGE  `subtitle`  `subtitle` TINYTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL") or error(db_error());
		case 'v0.9.6-dev-1':
			query("CREATE TABLE IF NOT EXISTS `antispam` (
				  `board` varchar(255) NOT NULL,
				  `thread` int(11) DEFAULT NULL,
				  `hash` bigint(20) NOT NULL,
				  `created` int(11) NOT NULL,
				  `expires` int(11) DEFAULT NULL,
				  `passed` smallint(6) NOT NULL,
				  PRIMARY KEY (`hash`),
				  KEY `board` (`board`,`thread`)
				) ENGINE=MyISAM DEFAULT CHARSET=utf8;") or error(db_error());
		case 'v0.9.6-dev-2':
			query("ALTER TABLE `boards`
				DROP `id`,
				CHANGE  `uri`  `uri` VARCHAR( 120 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL") or error(db_error());
			query("ALTER TABLE  `bans` CHANGE  `board`  `board` VARCHAR( 120 ) NULL DEFAULT NULL") or error(db_error());
			query("ALTER TABLE  `reports` CHANGE  `board`  `board` VARCHAR( 120 ) NULL DEFAULT NULL") or error(db_error());
			query("ALTER TABLE  `modlogs` CHANGE  `board`  `board` VARCHAR( 120 ) NULL DEFAULT NULL") or error(db_error());
			foreach ($boards as $board) {
				$query = prepare("UPDATE `bans` SET `board` = :newboard WHERE `board` = :oldboard");
				$query->bindValue(':newboard', $board['uri']);
				$query->bindValue(':oldboard', $board['id']);
				$query->execute() or error(db_error($query));

				$query = prepare("UPDATE `modlogs` SET `board` = :newboard WHERE `board` = :oldboard");
				$query->bindValue(':newboard', $board['uri']);
				$query->bindValue(':oldboard', $board['id']);
				$query->execute() or error(db_error($query));

				$query = prepare("UPDATE `reports` SET `board` = :newboard WHERE `board` = :oldboard");
				$query->bindValue(':newboard', $board['uri']);
				$query->bindValue(':oldboard', $board['id']);
				$query->execute() or error(db_error($query));
			}
		case 'v0.9.6-dev-3':
			query("ALTER TABLE  `antispam` CHANGE  `hash`  `hash` CHAR( 40 ) NOT NULL") or error(db_error());
		case 'v0.9.6-dev-4':
			query("ALTER TABLE  `news` DROP INDEX  `id`, ADD PRIMARY KEY ( `id` )") or error(db_error());
		case 'v0.9.6-dev-5':
			query("ALTER TABLE  `bans` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `mods` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `news` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `noticeboard` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `pms` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			query("ALTER TABLE  `reports` CHANGE  `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT") or error(db_error());
			foreach ($boards as $board) {
				query(sprintf("ALTER TABLE  `posts_%s` CHANGE `id`  `id` INT( 11 ) UNSIGNED NOT NULL AUTO_INCREMENT", $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-6':
			foreach ($boards as &$_board) {
				query(sprintf("CREATE INDEX `thread_id` ON `posts_%s` (`thread`, `id`)", $_board['uri'])) or error(db_error());
				query(sprintf("ALTER TABLE `posts_%s` DROP INDEX `thread`", $_board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-7':
			query("ALTER TABLE  `bans` ADD  `seen` BOOLEAN NOT NULL") or error(db_error());
		case 'v0.9.6-dev-8':
			// to mlpchan-1
			query("DROP TABLE `antispam`") or error(db_error());
			query("ALTER TABLE `bans` ADD COLUMN `ip_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:exact, 1:range glob, 2:IPv4 CIDR'") or error(db_error());
			query("ALTER TABLE `bans` ADD KEY (`expires`)") or error(db_error());
			query("ALTER TABLE `bans` ADD KEY (`ip_type`,`ip`)") or error(db_error());
			query("UPDATE `bans` SET `ip_type` = 1 WHERE `ip` LIKE '%*%'") or error(db_error());
			query("UPDATE `bans` SET `ip_type` = 2 WHERE `ip` LIKE '%/%'") or error(db_error());
			foreach ($boards as $board) {
				query(sprintf("ALTER TABLE `posts_%s` ADD `mature` INT(1) NOT NULL AFTER `embed`", $board['uri'])) or error(db_error());
				query(sprintf("ALTER TABLE `posts_%s` MODIFY `email` VARCHAR(254)", $board['uri'])) or error(db_error());
				query(sprintf("CREATE INDEX `thread_id` ON `posts_%s` (`thread`, `id`)", $board['uri'])) or error(db_error());
				query(sprintf("DROP INDEX `thread` ON `posts_%s`", $board['uri'])) or error(db_error());
				query(sprintf("CREATE INDEX `ip` ON `posts_%s` (`ip`)", $board['uri'])) or error(db_error());
				// Add bodylink and postlink classes to links in posts
				query(sprintf("UPDATE `posts_%s` SET `body`=replace( replace(body, '<a target=\"_blank\" rel=\"nofollow\" href=\"', '<a target=\"_blank\" class=\"bodylink\" rel=\"nofollow\" href=\"'), '<a onclick=\"highlightReply(', '<a class=\"bodylink postlink\" onclick=\"highlightReply(')", $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-8-mlpchan-1':
			query("DROP TABLE `mutes`") or error(db_error());
			query("DROP TABLE `robot`") or error(db_error());
		case 'v0.9.6-dev-8-mlpchan-1-cleanup':
			query("ALTER TABLE `bans`
				ADD COLUMN `range_type` int(11) NOT NULL COMMENT '0:ipv4, 1:ipv6' AFTER `ip_type`,
				ADD COLUMN `range_start` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data' AFTER `range_type`,
				ADD COLUMN `range_end` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data' AFTER `range_start`,
				ADD KEY `range` (`range_type`, `range_start`, `range_end`)") or error(db_error());
			query("ALTER TABLE `ip_notes`
				ADD COLUMN `range_type` int(11) NOT NULL COMMENT '0:ipv4, 1:ipv6' AFTER `ip`,
				ADD COLUMN `range_start` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data' AFTER `range_type`,
				ADD COLUMN `range_end` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data' AFTER `range_start`,
				ADD KEY `range` (`range_type`, `range_start`, `range_end`)") or error(db_error());
			query("ALTER TABLE `modlogs`
				ADD COLUMN `ip_type` int(11) NOT NULL COMMENT '0:ipv4, 1:ipv6' AFTER `ip`,
				ADD COLUMN `ip_data` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data' AFTER `ip_type`") or error(db_error());
			query("ALTER TABLE `reports`
				ADD COLUMN `ip_type` int(11) NOT NULL COMMENT '0:ipv4, 1:ipv6' AFTER `ip`,
				ADD COLUMN `ip_data` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data' AFTER `ip_type`") or error(db_error());
			foreach ($boards as $board) {
				query(sprintf(
					"ALTER TABLE `posts_%s`
						ADD COLUMN `ip_type` int(11) NOT NULL COMMENT '0:ipv4, 1:ipv6' AFTER `ip`,
						ADD COLUMN `ip_data` varbinary(16) NOT NULL COMMENT 'INET6_ATON() address data' AFTER `ip_type`,
						ADD KEY `ip_type_data` (`ip_type`, `ip_data`)"
				, $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-8-mlpchan-2-prepare':
			foreach (query("SELECT `id`, `ip` FROM `bans` WHERE length(`range_start`) = 0")->fetchAll(PDO::FETCH_ASSOC) as $ban) {
				$range = parse_mask($ban["ip"]);
				if ($range === null) {
					$query = prepare("DELETE FROM `bans` WHERE `id` = :id");
					$query->bindValue(':id', $ban['id']);
					$query->execute() or error(db_error($query));
				} else {
					$query = prepare("UPDATE `bans` SET `range_type` = :range_type, `range_start` = INET6_ATON(:range_start), `range_end` = INET6_ATON(:range_end) WHERE `id` = :id");
					$query->bindValue(':id', $ban['id']);
					$query->bindValue(':range_type', $range['range_type']);
					$query->bindValue(':range_start', $range['range_start']);
					$query->bindValue(':range_end', $range['range_end']);
					$query->execute() or error(db_error($query));
				}
			}
			foreach (query("SELECT `id`, `ip` FROM `ip_notes` WHERE length(`range_start`) = 0")->fetchAll(PDO::FETCH_ASSOC) as $note) {
				$range = parse_mask($note["ip"]);
				if ($range === null) {
					$query = prepare("DELETE FROM `ip_notes` WHERE `id` = :id");
					$query->bindValue(':id', $note['id']);
					$query->execute() or error(db_error($query));
				} else {
					$query = prepare("UPDATE `ip_notes` SET `range_type` = :range_type, `range_start` = INET6_ATON(:range_start), `range_end` = INET6_ATON(:range_end) WHERE `id` = :id");
					$query->bindValue(':id', $note['id']);
					$query->bindValue(':range_type', $range['range_type']);
					$query->bindValue(':range_start', $range['range_start']);
					$query->bindValue(':range_end', $range['range_end']);
					$query->execute() or error(db_error($query));
				}
			}
			query("UPDATE `modlogs`
				SET `ip_type` = IF(`ip` LIKE '%.%', 0, 1), `ip_data` = INET6_ATON(`ip`)
				WHERE LENGTH(`ip_data`) = 0") or error(db_error());
			query("UPDATE `reports`
				SET `ip_type` = IF(`ip` LIKE '%.%', 0, 1), `ip_data` = INET6_ATON(`ip`)
				WHERE LENGTH(`ip_data`) = 0") or error(db_error());
			foreach ($boards as $board) {
				query(sprintf("UPDATE `posts_%s`
					SET `ip_type` = IF(`ip` LIKE '%%.%%', 0, 1), `ip_data` = INET6_ATON(`ip`)
					WHERE LENGTH(`ip_data`) = 0", $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-8-mlpchan-2-migrate':
			query("ALTER TABLE `bans` DROP COLUMN `ip`, DROP COLUMN `ip_type`") or error(db_error());
			query("ALTER TABLE `ip_notes` DROP COLUMN `ip`") or error(db_error());
			query("ALTER TABLE `reports` DROP COLUMN `ip`") or error(db_error());
			query("ALTER TABLE `modlogs` DROP COLUMN `ip`") or error(db_error());
			foreach ($boards as $board) {
				query(sprintf("ALTER TABLE `posts_%s` DROP COLUMN `ip`", $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-8-mlpchan-2':
			query("ALTER TABLE `bans` ADD COLUMN `ban_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0:full, 1:image only' AFTER `board`") or error(db_error());
		case 'v0.9.6-dev-8-mlpchan-3':
			query("ALTER TABLE `bans`
				ADD COLUMN `status` int(11) NOT NULL COMMENT '0:active, 1:expired, 2:lifted' AFTER `id`,
				ADD COLUMN `lifted` int(11) DEFAULT NULL AFTER `expires`,
				DROP INDEX `range`,
				ADD INDEX `status_range` (`status`, `range_type`, `range_start`, `range_end`)") or error(db_error());
		case 'v0.9.6-dev-8-mlpchan-4':
			foreach ($boards as $board) {
				query(sprintf(
					"ALTER TABLE `posts_%s`
					ADD COLUMN `thumb_uri` varchar(255) DEFAULT NULL COMMENT 'Temporary override for remote images',
					ADD COLUMN `file_uri` varchar(255) DEFAULT NULL COMMENT 'Temporary override for remote images'"
				, $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-8-mlpchan-5':
			foreach ($boards as $board) {
				query(
					"ALTER TABLE `posts_${board['uri']}`
					CHANGE `password` `password` varchar(40) DEFAULT NULL"
				) or error(db_error());
				query(
					"UPDATE `posts_${board['uri']}` SET `password` = SHA1(`password`)"
				) or error(db_error());
				query(
					"ALTER TABLE `posts_${board['uri']}`
					CHANGE `password` `password` char(40) DEFAULT NULL"
				) or error(db_error());
			}
		case 'v0.9.6-dev-8-mlpchan-6':
			foreach ($boards as $board) {
				query("ALTER TABLE `posts_${board['uri']}` ADD `post_modifiers` text DEFAULT NULL COMMENT
			    'optional json object describing changes to how the post is rendered or marked up' AFTER `body_nomarkup`") or error(db_error());
			}
		case 'v0.9.6-dev-8-mlpchan-7':
			query("ALTER TABLE `modlogs` ADD `permission_level` SMALLINT(1) DEFAULT 1 NOT NULL") or error(db_error());
			query("UPDATE `modlogs` SET `permission_level` = 2 WHERE `text` LIKE '% a PM%' OR `text` LIKE '%password%' OR `text` LIKE 'Promoted%' OR `text` LIKE 'Demoted%' OR `text` LIKE 'Created a new user: %' OR `text` LIKE 'Deleted user %' OR `text` = 'Logged in'") or error(db_error());
		case 'v0.9.6-dev-8-mlpchan-8':
			// Remove onclick property from post links
			foreach ($boards as $board) {
				query(sprintf("UPDATE `posts_%s` SET `body`=REGEXP_REPLACE(body, '<a class=\"bodylink postlink\" onclick=\"[^\"]*\" ', '<a class=\"bodylink postlink\" ')", $board['uri'])) or error(db_error());
			}
		case 'v0.9.6-dev-8-mlpchan-9':
			// Add table for tracking future schema changes
			query("CREATE TABLE IF NOT EXISTS `migrations` (
				  `name` varchar(120) NOT NULL,
				  `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
				  PRIMARY KEY (`name`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
			) or error(db_error());
		// Don't add new migrations here. Look below to the New Migrations area instead.
		case false:
			// Update version number
			file_write($config['has_installed'], VERSION);

			$page['title'] = 'Upgraded';
			$page['body'] = '<p style="text-align:center">Successfully upgraded from ' . htmlentities($version) . ' to <strong>' . htmlentities(VERSION) . '</strong>.</p>';
			break;
		default:
			$page['title'] = 'Unknown version';
			$page['body'] = '<p style="text-align:center">Tinyboard was unable to determine what version is currently installed.</p>';
			break;
		case VERSION:
			$page['title'] = 'Already installed';
			$page['body'] = '<p style="text-align:center">It appears that Tinyboard is already installed (' . htmlentities($version) . ') and there is nothing to upgrade! Delete <strong>' . htmlentities($config['has_installed']) . '</strong> to reinstall.</p>';
			break;
	}

	$query = query("SELECT `name` FROM `migrations`") or error(db_error());
	$completed_migrations = []; // set where keys are completed migrations
	foreach($query->fetchAll(PDO::FETCH_ASSOC) as $value) {
		$completed_migrations[$value['name']] = true;
	}

	$applied_migrations = [];
	foreach($migration_procedures as $name => $proc) {
		if (!isset($completed_migrations[$name])) {
			$proc();

			$query = prepare("INSERT INTO `migrations` (`name`) VALUES (:name)");
			$query->bindValue(':name', $name);
			$query->execute() or error(db_error($query));

			// Migration procedures may start a transaction.
			if ($pdo->inTransaction()) {
				db_commit();
			}

			$applied_migrations[] = $name;
		}
	}

	if (count($applied_migrations) > 0) {
		$page['title'] = 'Upgraded';
		$page['body'] = '<p style="text-align:center">Successfully applied the following migrations: ' .
			htmlentities(implode(', ', $applied_migrations)) . '</p>';
	}

	die(Element('page.html', $page));
}

if ($step == 0) {
	// Agreeement
	$page['body'] = '
	<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black" disabled>' .
		htmlentities(file_get_contents('LICENSE-Tinyboard.md')) . "\n" .
		htmlentities(file_get_contents('LICENSE-Macil.txt')) .
	'</textarea>
	<p style="text-align:center">
		<a href="?step=1">I have read and understood the agreement. Proceed to installation.</a>
	</p>';

	echo Element('page.html', $page);
} elseif ($step == 1) {
	$page['title'] = 'Pre-installation test';

	$page['body'] = '<table class="test">';

	function rheader($item) {
		global $page, $config;

		$page['body'] .= '<tr class="h"><th colspan="2">' . $item . '</th></tr>';
	}

	function row($item, $result) {
		global $page, $config, $__is_error;
		if (!$result)
			$__is_error = true;
		$page['body'] .= '<tr><th>' . $item . '</th><td><img style="width:16px;height:16px" src="' . $config['dir']['static'] . ($result ? 'ok.png' : 'error.png') . '" /></td></tr>';
	}


	// Required extensions
	rheader('PHP extensions');
	row('PDO', extension_loaded('pdo'));
	row('GD', extension_loaded('gd'));

	// GD tests
	rheader('GD tests');
	row('JPEG', function_exists('imagecreatefromjpeg'));
	row('PNG', function_exists('imagecreatefrompng'));
	row('GIF', function_exists('imagecreatefromgif'));

	// Database drivers
	$drivers = PDO::getAvailableDrivers();

	rheader('PDO drivers <em>(currently installed drivers)</em>');
	foreach ($drivers as &$driver) {
		row($driver, true);
	}

	// Permissions
	rheader('File permissions');
	row('<em>root directory</em> (' . getcwd() . ')', is_writable('.'));

	$page['body'] .= '</table>
	<p style="text-align:center">
		<a href="?step=2"' .
			(isset($__is_error) ? ' onclick="return confirm(\'Are you sure you want to continue when errors exist?\')"' : '') .
		'>Continue' . (isset($__is_error) ? ' anyway' : '') . '</a>
	</p>';

	echo Element('page.html', $page);
} elseif ($step == 2) {
	// Basic config
	$page['title'] = 'Configuration';

	function create_salt() {
		return substr(base64_encode(sha1(rand())), 0, rand(25, 31));
	}

	$page['body'] = '
<form action="?step=3" method="post">
	<fieldset>
	<legend>Database</legend>
		<label for="db_type">Type:</label>
		<select id="db_type" name="db[type]">';

		$drivers = PDO::getAvailableDrivers();

		foreach ($drivers as &$driver) {
			$driver_txt = $driver;
			switch ($driver) {
				case 'cubrid':
					$driver_txt = 'Cubrid';
					break;
				case 'dblib':
					$driver_txt = 'FreeTDS / Microsoft SQL Server / Sybase';
					break;
				case 'firebird':
					$driver_txt = 'Firebird/Interbase 6';
					break;
				case 'ibm':
					$driver_txt = 'IBM DB2';
					break;
				case 'informix':
					$driver_txt = 'IBM Informix Dynamic Server';
					break;
				case 'mysql':
					$driver_txt = 'MySQL';
					break;
				case 'oci':
					$driver_txt = 'OCI';
					break;
				case 'odbc':
					$driver_txt = 'ODBC v3 (IBM DB2, unixODBC)';
					break;
				case 'pgsql':
					$driver_txt = 'PostgreSQL';
					break;
				case 'sqlite':
					$driver_txt = 'SQLite 3';
					break;
				case 'sqlite2':
					$driver_txt = 'SQLite 2';
					break;
			}
			$page['body'] .= '<option value="' . htmlspecialchars($driver) . '">' . htmlspecialchars($driver_txt) . '</option>';
		}

		$page['body'] .= '
		</select>

		<label for="db_server">Server:</label>
		<input type="text" id="db_server" name="db[server]" value="' . htmlspecialchars($config['db']['server']) . '" />

		<label for="db_db">Database:</label>
		<input type="text" id="db_db" name="db[database]" value="' . htmlspecialchars($config['db']['database']) . '" />

		<label for="db_user">Username:</label>
		<input type="text" id="db_user" name="db[user]" value="' . htmlspecialchars($config['db']['user']) . '" />

		<label for="db_pass">Password:</label>
		<input type="password" id="db_pass" name="db[password]" value="' . htmlspecialchars($config['db']['password']) . '" />
	</fieldset>
	<p style="text-align:center" class="unimportant">The following is all later configurable. For more options, <a href="http://tinyboard.org/docs/?p=Config">edit your configuration files</a> after installing.</p>
	<fieldset>
	<legend>Cookies</legend>
		<label for="cookies_mod">Moderator cookie:</label>
		<input type="text" id="cookies_mod" name="cookies[mod]" value="' . htmlspecialchars($config['cookies']['mod']) . '" />

		<label for="cookies_salt">Secure salt:</label>
		<input type="text" id="cookies_salt" name="cookies[salt]" value="' . htmlspecialchars(create_salt()) . '" size="40" />
	</fieldset>

	<fieldset>
	<legend>Flood control</legend>
		<label for="flood_time">Seconds before each post:</label>
		<input type="text" id="flood_time" name="flood_time" value="' . htmlspecialchars($config['flood_time']) . '" />

		<label for="flood_time_ip">Seconds before you can repost something (post the exact same text):</label>
		<input type="text" id="flood_time_ip" name="flood_time_ip" value="' . htmlspecialchars($config['flood_time_ip']) . '" />

		<label for="flood_time_same">Same as above, but with a different IP address:</label>
		<input type="text" id="flood_time_same" name="flood_time_same" value="' . htmlspecialchars($config['flood_time_same']) . '" />

		<label for="max_body">Maximum post body length:</label>
		<input type="text" id="max_body" name="max_body" value="' . htmlspecialchars($config['max_body']) . '" />

		<label for="reply_limit">Replies in a thread before it can no longer be bumped:</label>
		<input type="text" id="reply_limit" name="reply_limit" value="' . htmlspecialchars($config['reply_limit']) . '" />

		<label for="max_links">Maximum number of links in a single post:</label>
		<input type="text" id="max_links" name="max_links" value="' . htmlspecialchars($config['max_links']) . '" />
	</fieldset>

	<fieldset>
	<legend>Images</legend>
		<label for="max_filesize">Maximum image filesize:</label>
		<input type="text" id="max_filesize" name="max_filesize" value="' . htmlspecialchars($config['max_filesize']) . '" />

		<label for="thumb_width">Thumbnail width:</label>
		<input type="text" id="thumb_width" name="thumb_width" value="' . htmlspecialchars($config['thumb_width']) . '" />

		<label for="thumb_height">Thumbnail height:</label>
		<input type="text" id="thumb_height" name="thumb_height" value="' . htmlspecialchars($config['thumb_height']) . '" />

		<label for="max_width">Maximum image width:</label>
		<input type="text" id="max_width" name="max_width" value="' . htmlspecialchars($config['max_width']) . '" />

		<label for="max_height">Maximum image height:</label>
		<input type="text" id="max_height" name="max_height" value="' . htmlspecialchars($config['max_height']) . '" />
	</fieldset>

	<fieldset>
	<legend>Display</legend>
		<label for="threads_per_page">Threads per page:</label>
		<input type="text" id="threads_per_page" name="threads_per_page" value="' . htmlspecialchars($config['threads_per_page']) . '" />

		<label for="max_pages">Page limit:</label>
		<input type="text" id="max_pages" name="max_pages" value="' . htmlspecialchars($config['max_pages']) . '" />

		<label for="threads_preview">Number of replies to show per thread on the index page:</label>
		<input type="text" id="threads_preview" name="threads_preview" value="' . htmlspecialchars($config['threads_preview']) . '" />
	</fieldset>

	<fieldset>
	<legend>Directories</legend>
		<label for="root">Root URI (include trailing slash):</label>
		<input type="text" id="root" name="root" value="' . htmlspecialchars($config['root']) . '" />

		<label for="dir_img">Image directory:</label>
		<input type="text" id="dir_img" name="dir[img]" value="' . htmlspecialchars($config['dir']['img']) . '" />

		<label for="dir_thumb">Thumbnail directory:</label>
		<input type="text" id="dir_thumb" name="dir[thumb]" value="' . htmlspecialchars($config['dir']['thumb']) . '" />

		<label for="dir_res">Thread directory:</label>
		<input type="text" id="dir_res" name="dir[res]" value="' . htmlspecialchars($config['dir']['res']) . '" />
	</fieldset>

	<fieldset>
	<legend>Miscellaneous</legend>
		<label for="secure_trip_salt">Secure trip (##) salt:</label>
		<input type="text" id="secure_trip_salt" name="secure_trip_salt" value="' . htmlspecialchars(create_salt()) . '" size="40" />
	</fieldset>

	<p style="text-align:center">
		<input type="submit" value="Complete installation" />
	</p>
</form>
	';


	echo Element('page.html', $page);
} elseif ($step == 3) {
	$instance_config =
'<?php

/*
*  Instance Configuration
*  ----------------------
*  Edit this file and not config.php for imageboard configuration.
*
*  You can copy values from config.php (defaults) and paste them here.
*/

';

	function create_config_from_array(&$instance_config, &$array, $prefix = '') {
		foreach ($array as $name => $value) {
			if (is_array($value)) {
				$instance_config .= "\n";
				create_config_from_array($instance_config, $value, $prefix . '[\'' . addslashes($name) . '\']');
				$instance_config .= "\n";
			} else {
				$instance_config .= '	$config' . $prefix . '[\'' . addslashes($name) . '\'] = ';

				if (is_numeric($value))
					$instance_config .= $value;
				else
					$instance_config .= "'" . addslashes($value) . "'";

				$instance_config .= ";\n";
			}
		}
	}

	create_config_from_array($instance_config, $_POST);

	$instance_config .= "\n";

	if (@file_put_contents('inc/instance-config.php', $instance_config)) {
		opcache_invalidate('inc/instance-config.php');
		header('Location: ?step=4', true, $config['redirect_http']);
	} else {
		$page['title'] = 'Manual installation required';
		$page['body'] = '
			<p>I couldn\'t write to <strong>inc/instance-config.php</strong> with the new configuration, probably due to a permissions error.</p>
			<p>Please complete the installation manually by copying and pasting the following code into the contents of <strong>inc/instance-config.php</strong>:</p>
			<textarea style="width:700px;height:370px;margin:auto;display:block;background:white;color:black">' . htmlentities($instance_config) . '</textarea>
			<p style="text-align:center">
				<a href="?step=4">Once complete, click here to complete installation.</a>
			</p>
		';
		echo Element('page.html', $page);
	}
} elseif ($step == 4) {
	// SQL installation

	buildJavascript();

	$sql = @file_get_contents('install.sql') or error("Couldn't load install.sql.");
	$sql = str_replace(array("\r\n", "\n", "\r"), "\n", $sql);

	// This code is probably horrible, but what I'm trying
	// to do is find all of the SQL queires and put them
	// in an array.
	preg_match_all("/(^|\n)((SET|CREATE|INSERT).+)\n\n/msU", $sql, $queries);
	$queries = $queries[2];

	$queries[] = Element('posts.sql', array('board' => 'b'));

	$sql_errors = '';
	foreach ($queries as &$query) {
		if (!query($query))
			$sql_errors .= '<li>' . db_error() . '</li>';
	}

	foreach ($migration_procedures as $name => $proc) {
		$query = prepare("INSERT INTO `migrations` (`name`) VALUES (:name)");
		$query->bindValue(':name', $name);
		$query->execute() or error(db_error($query));
	}

	$boards = listBoards();
	foreach ($boards as &$_board) {
		setupBoard($_board);
		buildIndex();
	}

	$page['title'] = 'Installation complete';
	$page['body'] = '<p style="text-align:center">Thank you for using Tinyboard. Please remember to report any bugs you discover. <a href="http://tinyboard.org/docs/?p=Config">How do I edit the config files?</a></p>';

	if (!empty($sql_errors)) {
		$page['body'] .= '<div class="ban"><h2>SQL errors</h2><p>SQL errors were encountered when trying to install the database. This may be the result of using a database which is already occupied with a Tinyboard installation; if so, you can probably ignore this.</p><p>The errors encountered were:</p><ul>' . $sql_errors . '</ul><p><a href="?step=5">Ignore errors and complete installation.</a></p></div>';
	} else {
		file_write($config['has_installed'], VERSION);
		if (!file_unlink(__FILE__)) {
			$page['body'] .= '<div class="ban"><h2>Delete install.php!</h2><p>I couldn\'t remove <strong>install.php</strong>. You will have to remove it manually.</p></div>';
		}
	}

	echo Element('page.html', $page);
} elseif ($step == 5) {
	$page['title'] = 'Installation complete';
	$page['body'] = '<p style="text-align:center">Thank you for using Tinyboard. Please remember to report any bugs you discover.</p>';

	file_write($config['has_installed'], VERSION);
	if (!file_unlink(__FILE__)) {
		$page['body'] .= '<div class="ban"><h2>Delete install.php!</h2><p>I couldn\'t remove <strong>install.php</strong>. You will have to remove it manually.</p></div>';
	}

	echo Element('page.html', $page);
}
