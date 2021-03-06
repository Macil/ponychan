<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

if (realpath($_SERVER['SCRIPT_FILENAME']) == str_replace('\\', '/', __FILE__)) {
	// You cannot request this file directly.
	exit;
}

require_once 'inc/display.php';
require_once 'inc/template.php';
require_once 'inc/database.php';
require_once 'inc/events.php';
require_once 'inc/lib/gettext/gettext.inc';

// the user is not currently logged in as a moderator
$mod = false;

// Setting to true will cause error messages to be given as JSON.
$wantjson = false;

utf8_clean_userinput();

$userhash = null;
check_userid();

fix_cloudflare_headers();

$timings = array();
$timing_details = array();
timing_mark('start');
register_shutdown_function('timing_end');

register_shutdown_function('fatal_error_handler');
mb_internal_encoding('UTF-8');
loadConfig();

function loadConfig() {
	global $board, $config, $__ip, $debug, $__version;

	$error = function_exists('error') ? 'error' : 'basic_error_function_because_the_other_isnt_loaded_yet';

	reset_events();

	if (!isset($_SERVER['REMOTE_ADDR']))
		$_SERVER['REMOTE_ADDR'] = '0.0.0.0';

	$arrays = array(
		'db',
		'cache',
		'cookies',
		'error',
		'dir',
		'mod',
		'spam',
		'flood_filters',
		'wordfilters',
		'custom_capcode',
		'custom_tripcode',
		'dnsbl',
		'dnsbl_exceptions',
		'remote',
		'allowed_image_types',
		'allowed_video_types',
		'allowed_ext_files',
		'file_icons',
		'footer',
		'stylesheets',
		'markup',
		'banners',
		'custom_pages'
	);

	$config = array();
	foreach ($arrays as $key) {
		$config[$key] = array();
	}

	require 'inc/config.php';
	if (file_exists('inc/site-config.php'))
		require 'inc/site-config.php';
	if (!file_exists('inc/instance-config.php'))
		$error('Tinyboard is not configured! Create inc/instance-config.php.');

	require 'inc/instance-config.php';

	if (isset($config['overrides'])) {
		$config = array_merge_deep([$config, $config['overrides']]);
		unset($config['overrides']);
	}

	if (isset($board['dir']) && file_exists($board['dir'] . '/config.php')) {
		require $board['dir'] . '/config.php';
	}

	if (!isset($__version))
		$__version = file_exists('.installed') ? trim(file_get_contents('.installed')) : false;
	$config['version'] = $__version;

	if ($config['debug']) {
		if (!isset($debug)) {
			$debug = array('sql' => array(), 'purge' => array(), 'cached' => array());
			$debug['start'] = microtime(true);
		}
	}

	date_default_timezone_set($config['timezone']);

	if (!isset($config['blotter']))
		$config['blotter'] = false;

	if (!isset($config['post_url']))
		$config['post_url'] = $config['root'] . $config['file_post'];

	if (!isset($config['referer_match']))
		if (isset($_SERVER['HTTP_HOST'])) {
			$config['referer_match'] = '/^https?:\/\/' . preg_quote($_SERVER['HTTP_HOST'], '/') . '($|\/)/';
		} else {
			// CLI mode
			$config['referer_match'] = '//';
		}
	if (!isset($config['cookies']['path']))
		$config['cookies']['path'] = &$config['root'];

	if (!isset($config['dir']['static']))
		$config['dir']['static'] = $config['root'] . 'static/';

	if (!isset($config['image_sticky']))
		$config['image_sticky'] = $config['dir']['static'] . 'sticky.gif';
	if (!isset($config['image_locked']))
		$config['image_locked'] = $config['dir']['static'] . 'locked.gif';
	if (!isset($config['image_bumplocked']))
		$config['image_bumplocked'] = $config['dir']['static'] . 'sage.gif';
	if (!isset($config['image_deleted']))
		$config['image_deleted'] = $config['dir']['static'] . 'deleted.png';
	if (!isset($config['image_zip']))
		$config['image_zip'] = $config['dir']['static'] . 'zip.png';

	if (!isset($config['uri_thumb']))
		$config['uri_thumb'] = $config['root'] . $board['dir'] . $config['dir']['thumb'];
	elseif (isset($board['dir']))
		$config['uri_thumb'] = sprintf($config['uri_thumb'], $board['dir']);

	if (!isset($config['uri_img']))
		$config['uri_img'] = $config['root'] . $board['dir'] . $config['dir']['img'];
	elseif (isset($board['dir']))
		$config['uri_img'] = sprintf($config['uri_img'], $board['dir']);

	if (!isset($config['uri_stylesheets']))
		$config['uri_stylesheets'] = $config['root'] . 'stylesheets/';

	if (!isset($config['url_stylesheet']))
		$config['url_stylesheet'] = $config['uri_stylesheets'] . 'style.css';
	if (!isset($config['url_instance_script']))
		$config['url_instance_script'] = $config['root'] . $config['file_instance_script'];
	if (!isset($config['url_main_script']))
		$config['url_main_script'] = $config['root'] . $config['file_main_script'];

	if (!isset($config['mature_image']))
		$config['mature_image'] = $config['spoiler_image'];

	if ($config['root_file']) {
		chdir($config['root_file']);
	}

	if ($config['verbose_errors']) {
		error_reporting(E_ALL);
		ini_set('display_errors', 1);
	}

	// Keep the original address to properly comply with other board configurations
	if (!isset($__ip))
		$__ip = $_SERVER['REMOTE_ADDR'];

	// ::ffff:0.0.0.0
	if (preg_match('/^\:\:(ffff\:)?(\d+\.\d+\.\d+\.\d+)$/', $__ip, $m))
		$_SERVER['REMOTE_ADDR'] = $m[2];

	if (_setlocale(LC_ALL, $config['locale']) === false) {
		$error('The specified locale (' . $config['locale'] . ') does not exist on your platform!');
	}

	if (extension_loaded('gettext')) {
		bindtextdomain('tinyboard', './inc/locale');
		bind_textdomain_codeset('tinyboard', 'UTF-8');
		textdomain('tinyboard');
	} else {
		_bindtextdomain('tinyboard', './inc/locale');
		_bind_textdomain_codeset('tinyboard', 'UTF-8');
		_textdomain('tinyboard');
	}


	if ($config['syslog'])
		openlog('tinyboard', LOG_ODELAY, LOG_SYSLOG); // open a connection to sysem logger

	if ($config['recaptcha'])
		require_once 'inc/lib/recaptcha/recaptchalib.php';
	if ($config['cache']['enabled'])
		require_once 'inc/cache.php';
	event('load-config');
}

function basic_error_function_because_the_other_isnt_loaded_yet($message, $priority = true) {
	global $config;

	if ($config['syslog'] && $priority !== false) {
		// Use LOG_NOTICE instead of LOG_ERR or LOG_WARNING because most error message are not significant.
		_syslog($priority !== true ? $priority : LOG_NOTICE, $message);
	}

	// Yes, this is horrible.
	die('<!DOCTYPE html><html><head><title>Error</title>' .
		'<style type="text/css">' .
			'body{text-align:center;font-family:arial, helvetica, sans-serif;font-size:10pt;}' .
			'p{padding:0;margin:20px 0;}' .
			'p.c{font-size:11px;}' .
		'</style></head>' .
		'<body><h2>Error</h2>' . $message . '<hr/>' .
		'<p class="c">This alternative error page is being displayed because the other couldn\'t be found or hasn\'t loaded yet.</p></body></html>');
}

function fatal_error_handler() {
	if ($error = error_get_last()) {
		if ($error['type'] == E_ERROR) {
			if (function_exists('error')) {
				error('Caught fatal error: ' . $error['message'] . ' in <strong>' . $error['file'] . '</strong> on line ' . $error['line'], LOG_ERR);
			} else {
				basic_error_function_because_the_other_isnt_loaded_yet('Caught fatal error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'], LOG_ERR);
			}
		}
	}
}

function _syslog($priority, $message) {
	if (isset($_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'])) {
		// CGI
		syslog($priority, $message . ' - client: ' . $_SERVER['REMOTE_ADDR'] . ', request: "' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . '"');
	} else {
		syslog($priority, $message);
	}
}

function utf8_clean($str) {
	// Removes invalid UTF-8 byte sequences from string.
	return @iconv('UTF-8', 'UTF-8//IGNORE', $str);
}

function utf8_clean_array(&$arr) {
	foreach ($arr as &$item) {
		if (is_array($item)) {
			utf8_clean_array($item);
		} else {
			$item = utf8_clean($item);
		}
	}
}

function utf8_clean_userinput() {
	utf8_clean_array($_GET);
	utf8_clean_array($_POST);
	utf8_clean_array($_COOKIE);
	foreach ($_FILES as &$item) {
		$item['name'] = utf8_clean($item['name']);
	}
}

function array_merge_deep($arrays) {
	$result = array();
	foreach ($arrays as $array) {
		foreach ($array as $key => $value) {
			if (isset($result[$key]) && is_array($result[$key]) && is_array($value) && $result[$key] !== $value) {
				$result[$key] = array_merge_deep(array($result[$key], $value));
			} else {
				$result[$key] = $value;
			}
		}
	}
	return $result;
}

function computeResize($width, $height, $max_width, $max_height) {
	$x_ratio = $width == 0 ? 1 : $max_width / $width;
	$y_ratio = $height == 0 ? 1 : $max_height / $height;

	if (($width > $max_width) || ($height > $max_height)) {
		if (($x_ratio * $height) < $max_height) {
			$height = min(round($x_ratio * $height), $max_height);
			$width = $max_width;
		} else {
			$width = min(round($y_ratio * $width), $max_width);
			$height = $max_height;
		}
	}

	return array('width' => $width, 'height' => $height);
}

function check_userid() {
	global $userhash;
	if (!isset($_COOKIE['userid']))
		return;
	if (!preg_match('/^[0-9a-f]{32}$/', $_COOKIE['userid'])) {
		// invalid userid cookie, ignore it
		return;
	}
	$userhash = substr(hash('sha256', $_COOKIE['userid']), 0, 40);
}

function fix_cloudflare_headers() {
	if (!isset($_SERVER['HTTP_IF_NONE_MATCH']) && isset($_SERVER['HTTP_X_CF_DODGE_IF_NONE_MATCH'])) {
		$_SERVER['HTTP_IF_NONE_MATCH'] = $_SERVER['HTTP_X_CF_DODGE_IF_NONE_MATCH'];
	}
}

function close_request() {
	global $config;
	if ($config['use_fastcgi_finish_request']) {
		if (fastcgi_finish_request())
			timing_mark('fastcgi_finish_request');
		else
			timing_mark('fastcgi_finish_request_fail');
	} else {
		timing_mark('fastcgi_finish_request_unused');
	}
}

function timing_mark($name) {
	global $timings;

	array_push($timings, array($name, microtime(true)));
}

function timing_detail($name, $val) {
	global $timing_details;

	$timing_details[$name] = $val;
}

function timing_end() {
	global $config, $userhash, $timings, $timing_details;

	if (isset($config) && isset($config['timing_log']) && count($timings) > 1) {
		timing_mark('end');

		$logdata = array();
		$logdata['time'] = date(DATE_ATOM);
		$logdata['userhash'] = $userhash;
		$logdata['ip'] = $_SERVER['REMOTE_ADDR'];
		if (isset($_SERVER['REQUEST_METHOD']))
			$logdata['method'] = $_SERVER['REQUEST_METHOD'];
		if (isset($_SERVER['REQUEST_URI']))
			$logdata['uri'] = $_SERVER['REQUEST_URI'];
		if (isset($_SERVER['HTTP_REFERER']))
			$logdata['referrer'] = $_SERVER['HTTP_REFERER'];

		$logdata['timings'] = $timings;
		$logdata['details'] = $timing_details;
		$logline = json_encode($logdata);
		logToFile($config['timing_log'], $logline);
	}
}

// Converts a glob expression with * symbols to an SQL LIKE expression.
function globToSqlLike($glob) {
	return str_replace(['\\', '%', '_', '*'], ['\\\\', '\\%', '\\_', '%'], $glob);
}

// Take an IP string and transform it to a range that should match the user.
// IPv4 addresses are unchanged. IPv6 addresses are effectively transformed to
// a /64 range.
function ipToUserRange($ip) {
	return preg_replace('/^((?:[0-9a-f]+:){4}).*$/i', '\1*', $ip);
}

function cyclicThreadCleanup($thread, $limit) {
	global $board, $config;

	$query = prepare(sprintf("SELECT `id` FROM `posts_%s` WHERE `thread` = :thread AND `id` < (SELECT MIN(`id`) FROM (SELECT `id` FROM `posts_%s` WHERE `thread` = :thread ORDER BY `id` DESC LIMIT :limit) AS subquery)", $board['uri'], $board['uri']));
	$query->bindValue(':thread', $thread, PDO::PARAM_INT);
	$query->bindValue(':limit', $limit, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	$ids = array();
	while ($row = $query->fetch()) {
		array_push($ids, $row['id']);
	}

	deletePosts($ids, false, false);
}

// This should be done on any state-changing requests such as posts or mod actions.
// TODO all state-changing requests should be POST requests, and this should be
// done on all POST requests.
function checkCsrf() {
	global $config;

	if (
		(!isset($_SERVER['HTTP_ORIGIN']) || !preg_match($config['referer_match'], $_SERVER['HTTP_ORIGIN'])) &&
		(!isset($_SERVER['HTTP_REFERER']) || !preg_match($config['referer_match'], $_SERVER['HTTP_REFERER']))
	) {
		error($config['error']['referer'], true, 400);
	}
}

function create_antibot($board, $thread = null) {
	require_once dirname(__FILE__) . '/anti-bot.php';

	return _create_antibot($board, $thread);
}

function rebuildThemes($action, $board = false) {
	// List themes
	$query = query("SELECT `theme` FROM `theme_settings` WHERE `name` IS NULL AND `value` IS NULL") or error(db_error());

	while ($theme = $query->fetch()) {
		rebuildTheme($theme['theme'], $action, $board);
	}
}


function loadThemeConfig($_theme) {
	global $config;

	if (!file_exists($config['dir']['themes'] . '/' . $_theme . '/info.php'))
		return false;

	// Load theme information into $theme
	include $config['dir']['themes'] . '/' . $_theme . '/info.php';

	return $theme;
}

function rebuildTheme($theme, $action, $board = false) {
	global $config, $_theme;
	$_theme = $theme;

	$theme = loadThemeConfig($_theme);

	if (file_exists($config['dir']['themes'] . '/' . $_theme . '/theme.php')) {
		require_once $config['dir']['themes'] . '/' . $_theme . '/theme.php';

		$theme['build_function']($action, themeSettings($_theme), $board);
	}
}


function themeSettings($theme) {
	$query = prepare("SELECT `name`, `value` FROM `theme_settings` WHERE `theme` = :theme AND `name` IS NOT NULL");
	$query->bindValue(':theme', $theme);
	$query->execute() or error(db_error($query));

	$settings = array();
	while ($s = $query->fetch()) {
		$settings[$s['name']] = $s['value'];
	}

	return $settings;
}

function sprintf3($str, $vars, $delim = '%') {
	$replaces = array();
	foreach ($vars as $k => $v) {
		$replaces[$delim . $k . $delim] = $v;
	}
	return str_replace(array_keys($replaces),
	                   array_values($replaces), $str);
}

function simplifiedHash($text) {
	$text = strtolower($text);
	$text = preg_replace('/[^a-z0-9]/', '', $text);
	return sha1($text);
}

function setupBoard($array) {
	global $board, $config;

	$board = array(
		'uri' => $array['uri'],
		'title' => $array['title'],
		'subtitle' => $array['subtitle']
	);

	// older versions
	$board['name'] = &$board['title'];

	$board['dir'] = sprintf($config['board_path'], $board['uri']);
	$board['url'] = sprintf($config['board_abbreviation'], $board['uri']);

	loadConfig();

	if (!file_exists($board['dir']))
		@mkdir($board['dir'], 0777) or error("Couldn't create " . $board['dir'] . ". Check permissions.", true);
	if (!file_exists($board['dir'] . $config['dir']['img']))
		@mkdir($board['dir'] . $config['dir']['img'], 0777)
			or error("Couldn't create " . $board['dir'] . $config['dir']['img'] . ". Check permissions.", true);
	if (!file_exists($board['dir'] . $config['dir']['thumb']))
		@mkdir($board['dir'] . $config['dir']['thumb'], 0777)
			or error("Couldn't create " . $board['dir'] . $config['dir']['img'] . ". Check permissions.", true);
	if (!file_exists($board['dir'] . $config['dir']['res']))
		@mkdir($board['dir'] . $config['dir']['res'], 0777)
			or error("Couldn't create " . $board['dir'] . $config['dir']['img'] . ". Check permissions.", true);
}

function openBoard($uri, $forcereload=false) {
	global $board;

	// If the board is already loaded, don't do anything.
	if (isset($board['uri']) && $board['uri'] === $uri && !$forcereload)
		return true;

	$boardinfo = getBoardInfo($uri);
	if ($boardinfo) {
		setupBoard($boardinfo);
		return true;
	}
	return false;
}

function getBoardInfo($uri) {
	global $config;

	if ($config['cache']['enabled'] && ($board = cache::get('board_' . $uri))) {
		return $board;
	}

	$query = prepare("SELECT * FROM `boards` WHERE `uri` = :uri LIMIT 1");
	$query->bindValue(':uri', $uri);
	$query->execute() or error(db_error($query));

	if ($board = $query->fetch()) {
		if ($config['cache']['enabled'])
			cache::set('board_' . $uri, $board);
		return $board;
	}

	return false;
}

function boardTitle($uri) {
	$board = getBoardInfo($uri);
	if ($board)
		return $board['title'];
	return false;
}

function file_write($path, $data, $simple = false) {
	global $config;

	if (preg_match('/^remote:\/\/(.+)\:(.+)$/', $path, $m)) {
		if (isset($config['remote'][$m[1]])) {
			require_once 'inc/remote.php';

			$remote = new Remote($config['remote'][$m[1]]);
			$remote->write($data, $m[2]);
			return;
		} else {
			error('Invalid remote server: ' . $m[1]);
		}
	}

	if (!$simple) {
		// Windows doesn't support atomic renames replacing the original file.
		if (strncasecmp(PHP_OS, 'WIN', 3) !== 0) {
			$use_move = true;
			$use_lock = false;
		} else {
			$use_move = false;
			$use_lock = true;
		}
	} else {
		$use_lock = false;
		$use_move = false;
	}

	// Path for file we're writing to. If $use_move, then later we'll
	// rename this temporary file to the final filename.
	if ($use_move) {
		$tpath = $path . '.tmp-' . getmypid() . '~';
	} else {
		$tpath = $path;
	}

	if (!$fp = fopen($tpath, !$use_lock ? 'w' : 'c'))
		error('Unable to open file for writing: ' . $tpath);

	if ($use_lock) {
		// File locking
		if (!flock($fp, LOCK_EX))
			error('Unable to lock file: ' . $tpath);

		// Truncate file
		if (!ftruncate($fp, 0))
			error('Unable to truncate file: ' . $tpath);
	}

	// Write data
	if (fwrite($fp, $data) === false)
		error('Unable to write to file: ' . $tpath);

	// Unlock
	if ($use_lock)
		flock($fp, LOCK_UN);

	// Close
	if (!fclose($fp))
		error('Unable to close file: ' . $tpath);

	if ($use_move) {
		if (!rename($tpath, $path))
			error('Unable to move file: ' . $tpath);
	}

	event('write', $path);
}

function file_unlink($path) {
	global $config, $debug;

	if ($config['debug']) {
		if (!isset($debug['unlink']))
			$debug['unlink'] = array();
		$debug['unlink'][] = $path;
	}

	$ret = @unlink($path);

	event('unlink', $path);

	return $ret;
}

function file_move_no_overwrite($oldname, $newname) {
	$fd = @fopen($newname, 'x');
	if ($fd === false) {
		return false;
	}
	fclose($fd);
	if (rename($oldname, $newname)) {
		return true;
	} else {
		unlink($newname);
		throw new Exception("Failed to move file $oldname");
	}
}

// $action must be a string!
function hasPermission($action, $board = null, $_mod = null) {
	global $config;

	if (isset($_mod))
		$mod = &$_mod;
	else
		global $mod;

	if (!is_array($mod))
		return false;

	if (
		$mod['type'] < $config['permissions'][$action] &&
		(!isset($config['extra_permissions'][$mod['type']]) ||
			!in_array($action, $config['extra_permissions'][$mod['type']], true))
	) {
		return false;
	}

	if (!isset($board) || $config['mod']['skip_per_board'])
		return true;

	if (!isset($mod['boards']))
		return false;

	if (!in_array('*', $mod['boards']) && !in_array($board, $mod['boards']))
		return false;

	return true;
}

function mod_legal_username_check($username) {
	if (mb_strlen($username) > 30) {
		error('The username was too long. The username must be between 3 and 30 characters.');
	}
	if (mb_strlen($username) < 3) {
		error('The username was too short. The username must be between 3 and 30 characters.');
	}
}

function mod_legal_password_check($password) {
	if (mb_strlen($password) > 100) {
		error('The password was too long. The password must be less than 100 characters.');
	}
	if (mb_strlen($password) < 8) {
		error('The password was too short. The password must be at least 8 characters.');
	}
}

function listBoards() {
	global $config;

	if ($config['cache']['enabled'] && ($boards = cache::get('all_boards')))
		return $boards;

	$query = query("SELECT * FROM `boards` ORDER BY `uri`") or error(db_error());
	$boards = $query->fetchAll(PDO::FETCH_ASSOC);

	if ($config['cache']['enabled'])
		cache::set('all_boards', $boards);

	return $boards;
}

function isIPv6($ip) {
	return strstr($ip, ':') !== false;
}

define('IP_TYPE_IPV4', 0);
define('IP_TYPE_IPV6', 1);

function ipType($ip) {
	return isIPv6($ip) ? IP_TYPE_IPV6 : IP_TYPE_IPV4;
}

function parse_mask($mask) {
	/*
	 * We support three types of ban ranges: single IPs, cidr ranges, and glob expressions.
	 * All can apply to either ipv4 or ipv6 addresses.
	 *
	 * In all cases, we return a pair of strings containing the start and end addresses
	 * of the range.
	 */

	if (strpos($mask, '*') !== false) {
		// For a glob expression, we need to manually parse the ip address. Ugh.

		if (strpos($mask, ':') !== false) {
			// ipv6
			$parts = explode(':', $mask);
			if (count($parts) < 2 || count($parts) > 8)
				return null;

			$packed = "";
			$starSeen = false;
			$emptySeen = false;
			foreach ($parts as $chunk) {
				if ($starSeen && $chunk != '*')
					return null;
				if ($chunk == '*') {
					$starSeen = true;
					continue;
				}
				if ($emptySeen)
					return null;
				if ($chunk == '') {
					$emptySeen = true;
					continue;
				}

				if (!ctype_xdigit($chunk) || strlen($chunk) > 4)
					return null;
				$bioctet = intval($chunk, 16);
				if ($bioctet > 65535)
					return null;
				$packed .= chr($bioctet >> 8);
				$packed .= chr($bioctet & 0xff);
			}

			$start = $packed;
			$end = $packed;
			while (strlen($start) < 16) {
				$start .= chr(0);
				$end .= chr(255);
			}
			return array('range_type'=>IP_TYPE_IPV6, 'range_start'=>inet_ntop($start), 'range_end'=>inet_ntop($end));
		} else {
			// ipv4
			$parts = explode('.', $mask);
			if (count($parts) < 2 || count($parts) > 4)
				return null;

			$packed = "";
			$seen = false;
			foreach ($parts as $chunk) {
				if ($seen && $chunk != '*')
					return null;
				if ($chunk == '*') {
					$seen = true;
					continue;
				}

				if (!ctype_digit($chunk) || strlen($chunk) > 3)
					return null;
				$byte = intval($chunk);
				if ($byte > 0xff)
					return null;
				$packed .= chr($byte);
			}

			$start = $packed;
			$end = $packed;
			while (strlen($start) < 4) {
				$start .= chr(0);
				$end .= chr(255);
			}
			return array('range_type'=>IP_TYPE_IPV4, 'range_start'=>inet_ntop($start), 'range_end'=>inet_ntop($end));
		}
	} else {
		$parts = explode('/', $mask);
		if (count($parts) == 1) {
			$ip = $parts[0];
			$rangeString = null;
		} else if (count($parts) == 2) {
			$ip = $parts[0];
			$rangeString = $parts[1];
		} else {
			return null;
		}

		$packed = @inet_pton($ip);
		if ($packed === false)
			return null;

		$maxRange = strlen($packed) * 8;
		if ($rangeString === null) {
			$range = $maxRange;
		} else {
			if (!ctype_digit($rangeString) || strlen($rangeString) > 3)
				return null;
			$range = intval($rangeString);
			if ($range > $maxRange)
				return null;
		}

		$start = "";
		$end = "";
		for ($i = 0; $i < strlen($packed); $i++) {
			$byte = ord($packed[$i]);
			if ($range <= $i * 8)
				$mask = 0xff;
			else if ($range >= ($i + 1) * 8)
				$mask = 0;
			else
				$mask = (1 << ((($i + 1) * 8) - $range)) - 1;

			$start .= chr($byte & ~$mask);
			$end .= chr($byte | $mask);
		}

		return array('range_type'=>(strlen($packed) == 4 ? IP_TYPE_IPV4 : IP_TYPE_IPV6), 'range_start'=>inet_ntop($start), 'range_end'=>inet_ntop($end));
	}
}

function render_mask($mask) {
	$base = $mask['range_start'];
	$start = inet_pton($mask['range_start']);
	$end = inet_pton($mask['range_end']);
	$range = null;
	for ($i = 0; $i < strlen($start); $i++) {
		if (ord($start[$i]) != ord($end[$i])) {
			for ($j = 0; $j < 8; $j++) {
				if (ord($start[$i]) >> (7 - $j) != ord($end[$i]) >> (7 - $j)) {
					$range = $i * 8 + $j;
					break 2;
				}
			}
		}
	}
	if ($range === null) {
		return $base;
	} else {
		return "$base/$range";
	}
}

function mask_url($mask) {
	return str_replace('/', '^', $mask);
}

function checkFlood($post) {
	global $board, $config;

	if (event('check-flood', $post)) {
		error($config['error']['flood']);
	}

	$range = parse_mask(ipToUserRange($_SERVER['REMOTE_ADDR']));

	$query = prepare(sprintf(
		"SELECT 1 FROM `posts_%s` WHERE (
			`ip_type` = :range_type AND
			`ip_data` >= INET6_ATON(:range_start) AND
			`ip_data` <= INET6_ATON(:range_end) AND (
				`time` > :floodtime OR
				(`body` != '' AND `body` = :body AND `time` > :floodsameiptime)
			)
		) OR (
			`body` != ''  AND `body` = :body AND `time` > :floodsametime
		) LIMIT 1",
		$board['uri']));
	$query->bindValue(':range_type', $range['range_type'], PDO::PARAM_INT);
	$query->bindValue(':range_start', $range['range_start']);
	$query->bindValue(':range_end', $range['range_end']);
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':floodtime', time()-$config['flood_time'], PDO::PARAM_INT);
	$query->bindValue(':floodsameiptime', time()-$config['flood_time_ip'], PDO::PARAM_INT);
	$query->bindValue(':floodsametime', time()-$config['flood_time_same'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ((bool)$query->fetch()) {
		error($config['error']['flood']);
	}

	if ($post['op'] && count($config['flood_time_op']) > 0) {
		$max_count = max(array_map(function($pair) {
			return $pair[0];
		}, $config['flood_time_op']));
		$max_time = max(array_map(function($pair) {
			return $pair[1];
		}, $config['flood_time_op']));
		$query = prepare(sprintf(
			"SELECT `time` FROM `posts_%s` WHERE
				`thread` IS NULL AND
				`ip_type` = :range_type AND
				`ip_data` >= INET6_ATON(:range_start) AND
				`ip_data` <= INET6_ATON(:range_end) AND
				`time` > :start_time
				LIMIT :max_count",
			$board['uri']));
		$query->bindValue(':range_type', $range['range_type'], PDO::PARAM_INT);
		$query->bindValue(':range_start', $range['range_start']);
		$query->bindValue(':range_end', $range['range_end']);
		$query->bindValue(':max_count', $max_count, PDO::PARAM_INT);
		$query->bindValue(':start_time', time()-$max_time, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$thread_times = $query->fetchAll(PDO::FETCH_ASSOC);

		foreach ($config['flood_time_op'] as $limitPair) {
			list($count, $time) = $limitPair;
			$start_time = time()-$time;
			$threads_in_time = count(array_filter($thread_times, function($post) use ($start_time) {
				return $post['time'] > $start_time;
			}));
			if ($threads_in_time >= $count) {
				error($config['error']['flood_threads']);
			}
		}
	}
}

// Triggers an error if the file isn't suitable to be posted.
function check_post_file($filename, $file_size) {
	global $config;

	$mime_type = trim(shell_exec('file -b --mime-type ' . escapeshellarg($filename)));

	if (array_key_exists($mime_type, $config['allowed_image_types'])) {
		if ($file_size > $config['max_filesize'])
			error(sprintf3($config['error']['filesize'], array(
				'filesz' => number_format($file_size),
				'maxsz' => number_format($config['max_filesize'])
			)));

		$file_type = 'image';
		$extension = $config['allowed_image_types'][$mime_type];
	} elseif (array_key_exists($mime_type, $config['allowed_video_types'])) {
		if ($file_size > $config['max_video_filesize'])
			error(sprintf3($config['error']['filesize'], array(
				'filesz' => number_format($file_size),
				'maxsz' => number_format($config['max_video_filesize'])
			)));

		$file_type = 'video';
		$extension = $config['allowed_video_types'][$mime_type];
	} else {
		error($config['error']['unsupported_type']);
	}

	return [
		'mime_type' => $mime_type,
		'file_type' => $file_type,
		'extension' => $extension
	];
}

function time_length($time) {
	if ($time < 60) {
		return $time . ' second' . ($time != 1 ? 's' : '');
	} elseif ($time < 60*60) {
		return ($num = round($time/(60))) . ' minute' . ($num != 1 ? 's' : '');
	} elseif ($time < 60*60*24) {
		return ($num = round($time/(60*60))) . ' hour' . ($num != 1 ? 's' : '');
	} elseif ($time < 60*60*24*7) {
		return ($num = round($time/(60*60*24))) . ' day' . ($num != 1 ? 's' : '');
	} elseif ($time < 60*60*24*365) {
		return ($num = round($time/(60*60*24*7))) . ' week' . ($num != 1 ? 's' : '');
	}

	return ($num = round($time/(60*60*24*365))) . ' year' . ($num != 1 ? 's' : '');
}

function until($timestamp) {
	return time_length($timestamp - time());
}

function ago($timestamp) {
	return time_length(time() - $timestamp);
}

define('FULL_BAN', 0);
define('IMAGE_BAN', 1);
define('THREAD_START_BAN', 2);

define('BAN_STATUS_ACTIVE', 0);
define('BAN_STATUS_EXPIRED', 1);
define('BAN_STATUS_LIFTED', 2);

function displayBan($ban) {
	global $config, $wantjson;

	http_response_code(403);

	if (!$ban['seen']) {
		$query = prepare("UPDATE `bans` SET `seen` = 1 WHERE `id` = :id");
		$query->bindValue(':id', $ban['id'], PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
	}

	$ban['ip'] = $_SERVER['REMOTE_ADDR'];

	$query = prepare("SELECT *, UNIX_TIMESTAMP(`timestamp`) AS `time` FROM `ban_appeals` WHERE `ban` = :id");
	$query->bindValue(':id', $ban['id'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	$appeals = $query->fetchAll(PDO::FETCH_ASSOC);

	$banhtml = Element('page.html', array(
			'title' => 'Banned!',
			'config' => $config,
			'body' => Element('banned.html', array(
				'config' => $config,
				'ban' => $ban,
				'appeals' => $appeals
			))));

	if ($wantjson) {
		header('Content-Type: application/json');
		die(json_encode(array('error' => 'ban', 'banhtml' => $banhtml)));
	}

	die($banhtml);
}

function ban_type_name($type) {
	switch($type) {
		case FULL_BAN: return 'Full ban';
		case IMAGE_BAN: return 'Image ban';
		case THREAD_START_BAN: return 'Thread starting';
		default: return "Unknown type $type";
	}
}

function checkBan($board = 0, $types = null) {
	global $config;

	if (!isset($_SERVER['REMOTE_ADDR'])) {
		// Server misconfiguration
		return;
	}

	if (event('check-ban', $board))
		return;

	if ($types === null)
		$types = array(FULL_BAN);

	$query = prepare("SELECT `id`, `set`, `expires`, `reason`, `board`, `seen`, `ban_type`, `signed_name`, `signed_trip`, `appealable` FROM `bans`
		WHERE `range_type` = :ip_type AND `range_start` <= INET6_ATON(:ip) AND INET6_ATON(:ip) <= `range_end`
		AND (`board` IS NULL OR `board` = :board)
		AND `status` = 0
		AND `ban_type` IN (" . implode(', ', $types) . ")
		ORDER BY `ban_type` ASC, `id` ASC"
		);
	$query->bindValue(':ip_type', ipType($_SERVER['REMOTE_ADDR']));
	$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
	$query->bindValue(':board', $board);
	$query->execute() or error(db_error($query));

	foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $ban) {
		if (event('process-ban', $ban, $board))
			continue;

		if ($ban['expires'] && $ban['expires'] < time()) {
			// Ban expired
			$query = prepare("UPDATE `bans` SET `status` = 1 WHERE `id` = :id");
			$query->bindValue(':id', $ban['id'], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));

			if ($ban['seen'] && !$config['require_ban_view'])
				continue;
		}

		displayBan($ban);
	}
}

function checkCaptcha() {
	$query = prepare("SELECT `id` FROM `needs_captcha`
		WHERE `range_type` = :ip_type AND
		`range_start` <= INET6_ATON(:ip) AND
		INET6_ATON(:ip) <= `range_end`
		LIMIT 1"
	);
	$query->bindValue(':ip_type', ipType($_SERVER['REMOTE_ADDR']));
	$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
	$query->execute() or error(db_error($query));

	if ($query->rowCount() > 0) {
		error('You need to <a href="/captcha" target="_blank">complete a CAPTCHA</a> before you can post. <a href="/captcha" target="_blank">Click here to receive a CAPTCHA.</a>');
	}
}

function threadLocked($id) {
	global $board;

	if (event('check-locked', $id))
		return true;

	$query = prepare(sprintf("SELECT `locked` FROM `posts_%s` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error());

	if (!$post = $query->fetch()) {
		// Non-existant, so it can't be locked...
		return false;
	}

	return (bool)$post['locked'];
}

function threadSageLocked($id) {
	global $board;

	if (event('check-sage-locked', $id))
		return true;

	$query = prepare(sprintf("SELECT `sage` FROM `posts_%s` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error());

	if (!$post = $query->fetch()) {
		// Non-existant, so it can't be locked...
		return false;
	}

	return (bool) $post['sage'];
}

function threadExists($id) {
	global $board;

	$query = prepare(sprintf("SELECT 1 FROM `posts_%s` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error());

	if ($query->rowCount()) {
		return true;
	}

	return false;
}

// takes a string like "foo_123456.jpg" and returns "foo_123457.jpg"
function incrementFilename($f) {
	$newname = preg_replace_callback('/\\d+/', function($matches) {
		return $matches[0]+1;
	}, $f, 1);
	if ($newname === $f) {
		$newname = preg_replace('/\\./', '-1.', $f, 1);
	}
	if ($newname === $f) {
		$newname = $f . '-1';
	}
	return $newname;
}

function post(array $post) {
	global $pdo, $board;
	$query = prepare(sprintf(
		'INSERT INTO `posts_%s` (`id`, `thread`, `subject`, `email`, `email_protocol`, `name`, ' .
		'`trip`, `capcode`, `body`, `body_nomarkup`, `time`, `bump`, `thumb`, `thumb_uri`, ' .
		'`thumbwidth`, `thumbheight`, `file`, `filetype`, `file_uri`, `filewidth`, `fileheight`, ' .
		'`filesize`, `filename`, `filehash`, `password`, `userhash`, `ip_type`, `ip_data`, `sticky`, ' .
		'`locked`, `sage`, `embed`, `mature`, `anon_thread`) VALUES ( NULL, :thread, :subject, ' .
		':email, :email_protocol, :name, :trip, :capcode, :body, :body_nomarkup, :time, :bump, ' .
		':thumb, :thumb_uri, :thumbwidth, :thumbheight, :file, :filetype, :file_uri, :width, :height, :filesize, ' .
		':filename, :filehash, :password, :userhash, :ip_type, INET6_ATON(:ip), :sticky, :locked, 0, :embed, ' .
		':mature, :anon_thread)', $board['uri']));

	// Basic stuff
	if (!empty($post['subject'])) {
		$query->bindValue(':subject', $post['subject']);
	} else {
		$query->bindValue(':subject', NULL, PDO::PARAM_NULL);
	}

	if (!empty($post['email'])) {
		$query->bindValue(':email', $post['email']);
	} else {
		$query->bindValue(':email', NULL, PDO::PARAM_NULL);
	}

	if (!empty($post['trip'])) {
		$query->bindValue(':trip', $post['trip']);
	} else {
		$query->bindValue(':trip', NULL, PDO::PARAM_NULL);
	}
	$query->bindValue(':email_protocol', !empty($post['email_protocol']) ? $post['email_protocol'] : null);
	$query->bindValue(':name', $post['name']);
	$query->bindValue(':body', $post['body']);
	$query->bindValue(':body_nomarkup', $post['body_nomarkup']);
	$query->bindValue(':time', isset($post['time']) ? $post['time'] : time(), PDO::PARAM_INT);
	$query->bindValue(':bump', isset($post['bump']) ? $post['bump'] : (isset($post['time']) ? $post['time'] : time()), PDO::PARAM_INT);
	$query->bindValue(':password', $post['password']);
	$query->bindValue(':userhash', $post['userhash']);
	$ip = isset($post['ip']) ? $post['ip'] : $_SERVER['REMOTE_ADDR'];
	$query->bindValue(':ip', $ip);
	$query->bindValue(':ip_type', ipType($ip));

	if ($post['op'] && isset($post['sticky']) && $post['sticky']) {
		$query->bindValue(':sticky', 1, PDO::PARAM_INT);
	} else {
		$query->bindValue(':sticky', 0, PDO::PARAM_INT);
	}

	if ($post['op'] && isset($post['locked']) && $post['locked']) {
		$query->bindValue(':locked', 1, PDO::PARAM_INT);
	} else {
		$query->bindValue(':locked', 0, PDO::PARAM_INT);
	}

	$query->bindValue(':mature', isset($post['mature']) && $post['mature'] ? 1 : 0, PDO::PARAM_INT);

	$query->bindValue(':anon_thread', isset($post['anon_thread']) && $post['anon_thread'] ? 1 : 0, PDO::PARAM_INT);

	if (isset($post['capcode']) && $post['capcode']) {
		$query->bindValue(':capcode', $post['capcode']);
	} else {
		$query->bindValue(':capcode', NULL, PDO::PARAM_NULL);
	}

	if (!empty($post['embed'])) {
		$query->bindValue(':embed', $post['embed']);
	} else {
		$query->bindValue(':embed', NULL, PDO::PARAM_NULL);
	}

	if ($post['op']) {
		// No parent thread, image
		$query->bindValue(':thread', null, PDO::PARAM_NULL);
	} else {
		$query->bindValue(':thread', $post['thread'], PDO::PARAM_INT);
	}

	$query->bindValue(':thumb_uri', (isset($post['thumb_uri']) && $post['thumb_uri']) ? $post['thumb_uri'] : null);
	$query->bindValue(':file_uri', (isset($post['file_uri']) && $post['file_uri']) ? $post['file_uri'] : null);

	if ($post['has_file']) {
		$query->bindValue(':thumb', $post['thumb']);
		$query->bindValue(':thumbwidth', $post['thumbwidth'], PDO::PARAM_INT);
		$query->bindValue(':thumbheight', $post['thumbheight'], PDO::PARAM_INT);
		$query->bindValue(':file', $post['file']);
		$query->bindValue(':filetype', $post['filetype']);

		if (isset($post['width'], $post['height'])) {
			$query->bindValue(':width', $post['width'], PDO::PARAM_INT);
			$query->bindValue(':height', $post['height'], PDO::PARAM_INT);
		} else {
			$query->bindValue(':width', null, PDO::PARAM_NULL);
			$query->bindValue(':height', null, PDO::PARAM_NULL);
		}

		$query->bindValue(':filesize', $post['filesize'], PDO::PARAM_INT);
		$query->bindValue(':filename', $post['filename']);
		$query->bindValue(':filehash', $post['filehash']);
	} else {
		$query->bindValue(':thumb', null, PDO::PARAM_NULL);
		$query->bindValue(':thumbwidth', null, PDO::PARAM_NULL);
		$query->bindValue(':thumbheight', null, PDO::PARAM_NULL);
		$query->bindValue(':file', null, PDO::PARAM_NULL);
		$query->bindValue(':filetype', null, PDO::PARAM_NULL);
		$query->bindValue(':width', null, PDO::PARAM_NULL);
		$query->bindValue(':height', null, PDO::PARAM_NULL);
		$query->bindValue(':filesize', null, PDO::PARAM_NULL);
		$query->bindValue(':filename', null, PDO::PARAM_NULL);
		$query->bindValue(':filehash', null, PDO::PARAM_NULL);
	}

	if (!$query->execute()) {
		undoFile($post);
		error(db_error($query));
	}

	return $pdo->lastInsertId();
}

function calculateOldThreadBumpInterval($id, $lastbump) {
	global $config;

	if (!$config['old_thread_bump_interval_min'] || !$config['old_thread_bump_interval_max']) return null;

	$ma = unpack('l', sha1($config['secure_trip_salt'] . ':' . $id . ':' . $lastbump, true));
	$m = ($ma[1] & 0x7fffffff) / 0x7fffffff;

	$r = $config['old_thread_bump_interval_max'] - $config['old_thread_bump_interval_min'];

	return $config['old_thread_bump_interval_min'] + $m*$r;
}

function bumpThread($id) {
	global $board;

	if (event('bump', $id))
		return true;

	$query = prepare(sprintf("UPDATE `posts_%s` SET `bump` = :time WHERE `id` = :id AND `thread` IS NULL", $board['uri']));
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
}

$_userHasPosts_cache = [];
function userHasPosts($ip, $userhash) {
	global $board;

	$ip_mask = ipToUserRange($ip);
	if (isset($_userHasPosts_cache[$ip_mask])) {
		return $_userHasPosts_cache[$ip_mask];
	}

	$parsed_ip = parse_mask($ip_mask);

	$checkForPostsInBoard = function($boarduri) use ($parsed_ip, $userhash) {
		$query = prepare("SELECT id FROM `posts_${boarduri}` WHERE
			(`userhash` IS NOT NULL AND `userhash` = :userhash) OR
			(`ip_type` = :ip_type AND `ip_data` >= INET6_ATON(:range_start) AND `ip_data` <= INET6_ATON(:range_end)) LIMIT 1");
		$query->bindValue(':userhash', $userhash);
		$query->bindValue(':ip_type', $parsed_ip['range_type']);
		$query->bindValue(':range_start', $parsed_ip['range_start']);
		$query->bindValue(':range_end', $parsed_ip['range_end']);
		$query->execute() or error(db_error($query));

		return $query->rowCount() > 0;
	};

	// Check current board first because it's more likely to have posts from the
	// user if they're posting in it now.
	$hasPosts = $checkForPostsInBoard($board['uri'], $ip, $userhash);
	if (!$hasPosts) {
		foreach(listBoards() as $_board) {
			if ($_board['uri'] === $board['uri']) continue;
			$hasPosts = $checkForPostsInBoard($_board['uri'], $ip, $userhash);
			if ($hasPosts) break;
		}
	}

	$_userHasPosts_cache[$ip_mask] = $hasPosts;
	return $hasPosts;
}

function userHasNotSolvedCaptchaInLastMinutes($ip, $minutes) {
	$ip_mask = ipToUserRange($ip);
	$parsed_ip = parse_mask($ip_mask);

	$query = prepare("SELECT id FROM `solved_captcha` WHERE
		`ip_type` = :ip_type AND
		`ip_data` >= INET6_ATON(:range_start) AND
		`ip_data` <= INET6_ATON(:range_end) AND
		`timestamp` >= NOW() - INTERVAL :minutes MINUTE
		LIMIT 1");
	$query->bindValue(':ip_type', $parsed_ip['range_type']);
	$query->bindValue(':range_start', $parsed_ip['range_start']);
	$query->bindValue(':range_end', $parsed_ip['range_end']);
	$query->bindValue(':minutes', $minutes, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	return $query->rowCount() === 0;
}

// Remove file from post
function deleteFile($id, $remove_entirely_if_already=true, $rebuild_after=true) {
	global $board, $config;

	$query = prepare(sprintf("SELECT `thread`,`thumb`,`file` FROM `posts_%s` WHERE `id` = :id LIMIT 1", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	if (!$post = $query->fetch())
		error($config['error']['invalidpost']);

	if ($post['file'] == 'deleted' && !$post['thread'])
		return; // Can't delete OP's image completely.
	if (!$post['file'])
		return; // Nothing to delete.

	$query = prepare(sprintf("UPDATE `posts_%s` SET `thumb` = NULL, `thumbwidth` = NULL, `thumbheight` = NULL, `filewidth` = NULL, `fileheight` = NULL, `filesize` = NULL, `filename` = NULL, `filehash` = NULL, `file` = :file WHERE `id` = :id", $board['uri']));
	if ($post['file'] == 'deleted' && $remove_entirely_if_already) {
		// Already deleted; remove file fully
		$query->bindValue(':file', null, PDO::PARAM_NULL);
	} else {
		// Delete thumbnail
		file_unlink($board['dir'] . $config['dir']['thumb'] . $post['thumb']);

		// Delete file
		file_unlink($board['dir'] . $config['dir']['img'] . $post['file']);

		// Set file to 'deleted'
		$query->bindValue(':file', 'deleted', PDO::PARAM_INT);
	}

	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($rebuild_after) {
		if ($post['thread'])
			buildThread($post['thread']);
		else
			buildThread($id);
	}
}

// rebuild post (markup)
function rebuildPost($id, $rebuildThread=true) {
	global $board;

	$query = prepare(sprintf("SELECT `body_nomarkup`, `thread` FROM `posts_%s` WHERE `id` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ((!$post = $query->fetch()) || !$post['body_nomarkup'])
		return false;
	markup($body = &$post['body_nomarkup']);

	$query = prepare(sprintf("UPDATE `posts_%s` SET `body` = :body WHERE `id` = :id", $board['uri']));
	$query->bindValue(':body', $body);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	$thread = $post['thread'] ? $post['thread'] : $id;

	if ($rebuildThread)
		buildThread($thread);

	return $thread;
}

function deletePostContent($id, $rebuild_after=true) {
	global $board, $config;

	$query = prepare(sprintf("SELECT `thread` FROM `posts_%s` WHERE `id` = :id LIMIT 1", $board['uri']));
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));
	if (!$post = $query->fetch())
		error($config['error']['invalidpost']);

	$query = prepare("DELETE FROM `cites` WHERE `board` = :board AND `post` = :id");
	$query->bindValue(':board', $board['uri']);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	$query = prepare(sprintf("UPDATE `posts_%s` SET `name` = :name, `trip` = NULL, `subject` = NULL, `email` = NULL, `capcode` = NULL, `body` = :body, `body_nomarkup` = :body, `password` = NULL WHERE `id` = :id", $board['uri']));
	$query->bindValue(':name', '[deleted]');
	$query->bindValue(':body', '[deleted]');
	$query->bindValue(':id', $id);
	$query->execute() or error(db_error($query));

	deleteFile($id, false, false);

	if ($rebuild_after) {
		if ($post['thread'])
			buildThread($post['thread']);
		else
			buildThread($id);
	}
}

// Delete a post (reply or thread)
function deletePost($id, $error_if_doesnt_exist=true, $rebuild_after=true) {
	return deletePosts(array($id), $error_if_doesnt_exist, $rebuild_after);
}

function deletePosts($ids, $error_if_doesnt_exist=true, $rebuild_after=true) {
	global $board, $config;

	if (count($ids) == 0)
		return true;

	// Select post and replies (if thread) in one query
	$query = prepare(sprintf("SELECT `id`,`thread`,`thumb`,`file` FROM `posts_%s` WHERE `id` IN (" . implode(', ', $ids) . ") OR `thread` IN (" . implode(', ', $ids) . ")", $board['uri']));
	$query->execute() or error(db_error($query));

	if ($query->rowCount() < 1) {
		if ($error_if_doesnt_exist)
			error($config['error']['invalidpost']);
		else return false;
	}

	// Is an array where the keys are boards, values are arrays
	// where the keys are thread numbers.
	$threads_to_rebuild = array();
	// This one is just a simple array of IDs. All in the original
	// board.
	$deleted_ids = array();

	// Delete posts and maybe replies
	while ($post = $query->fetch()) {
		if (!$post['thread']) {
			// Delete thread HTML page
			file_unlink($board['dir'] . $config['dir']['res'] . sprintf($config['file_page'], $post['id']));
			file_unlink($board['dir'] . $config['dir']['res'] . sprintf($config['file_page50'], $post['id']));
		} else {
			// Mark thread for rebuild
			$threads_to_rebuild[ $board['uri'] ][ $post['thread'] ] = true;
		}
		if ($post['thumb']) {
			// Delete thumbnail
			file_unlink($board['dir'] . $config['dir']['thumb'] . $post['thumb']);
		}
		if ($post['file']) {
			// Delete file
			file_unlink($board['dir'] . $config['dir']['img'] . $post['file']);
		}

		$deleted_ids[] = (int)$post['id'];
	}

	$query = prepare(sprintf("DELETE FROM `posts_%s` WHERE `id` IN (" . implode(', ', $ids) . ") OR `thread` IN (" . implode(', ', $ids) . ")", $board['uri']));
	$query->execute() or error(db_error($query));

	$query = prepare("SELECT `board`, `post` FROM `cites` WHERE `target_board` = :board AND `target` IN (" . implode(', ', $deleted_ids) . ")");
	$query->bindValue(':board', $board['uri']);
	$query->execute() or error(db_error($query));

	$orig_board = $board['uri'];

	while ($cite = $query->fetch()) {
		if ($board['uri'] != $cite['board'])
			openBoard($cite['board']);
		$citedThread = rebuildPost($cite['post'], false);
		if ($citedThread !== false)
			$threads_to_rebuild[ $cite['board'] ][ $citedThread ] = true;
	}

	$query = prepare("DELETE FROM `cites` WHERE (`target_board` = :board AND `target` IN (" . implode(', ', $deleted_ids) . ")) OR (`board` = :board AND `post` IN (" . implode(', ', $deleted_ids) . "))");
	$query->bindValue(':board', $orig_board);
	$query->execute() or error(db_error($query));

	if ($rebuild_after) {
		foreach ($threads_to_rebuild as $_board => $_threads) {
			if ($board['uri'] != $_board)
				openBoard($_board);
			foreach ($_threads as $_thread => $_dummy) {
				if ($_dummy && ($board['uri'] != $orig_board || !in_array($_thread, $deleted_ids)))
					buildThread($_thread);
			}
		}
	}

	if ($board['uri'] != $orig_board)
		openBoard($orig_board);

	return true;
}

function clean() {
	global $board, $config;
	$offset = round($config['max_pages']*$config['threads_per_page']);

	// I too wish there was an easier way of doing this...
	$query = prepare(sprintf("SELECT `id` FROM `posts_%s` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset, 9001", $board['uri']));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);

	$query->execute() or error(db_error($query));
	while ($post = $query->fetch()) {
		deletePost($post['id']);
	}
}

// Builds an index page.
// $oldbump parameter is used by buildIndex(). Causes this function to return
// false if the first thread's bump value on this page is older than it.
function index($page, $mod=false, $oldbump=false) {
	global $board, $config, $debug;

	$body = '';
	$offset = round($page*$config['threads_per_page']-$config['threads_per_page']);

	$query = prepare(sprintf("SELECT *, INET6_NTOA(`ip_data`) AS `ip` FROM `posts_%s` WHERE `thread` IS NULL ORDER BY `sticky` DESC, `bump` DESC LIMIT :offset,:threads_per_page", $board['uri']));
	$query->bindValue(':offset', $offset, PDO::PARAM_INT);
	$query->bindValue(':threads_per_page', $config['threads_per_page'], PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($query->rowcount() < 1 && $page > 1)
		return false;

	$first = true;
	while ($th = $query->fetch()) {
		if ($first) {
			if (!$th['sticky'] && $oldbump !== false && $th['bump'] < $oldbump)
				return false;
			$first = false;
		}
		$thread = new Thread(
			$th['id'], $th['subject'], $th['email'], $th['email_protocol'], $th['name'], $th['trip'], $th['capcode'], $th['body'], $th['time'], $th['thumb'],
			$th['thumb_uri'], $th['thumbwidth'], $th['thumbheight'], $th['file'], $th['filetype'], $th['file_uri'], $th['filewidth'], $th['fileheight'], $th['filesize'], $th['filename'], $th['ip'],
			$th['sticky'], $th['locked'], $th['sage'], $th['embed'], $mod ? '?/' : $config['root'], $mod, true, $th['mature'], $th['anon_thread']
		);

		if ($config['cache']['enabled'] && $cached = cache::get("thread_index_{$board['uri']}_{$th['id']}")) {
			$replies = $cached['replies'];
			$omitted = $cached['omitted'];
		} else {
			$posts = prepare(sprintf("SELECT *, INET6_NTOA(`ip_data`) AS `ip`, NULL AS `ip_data` FROM `posts_%s` WHERE `thread` = :id ORDER BY `id` DESC LIMIT :limit", $board['uri']));
			$posts->bindValue(':id', $th['id']);
			$posts->bindValue(':limit', ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']), PDO::PARAM_INT);
			$posts->execute() or error(db_error($posts));

			$replies = array_reverse($posts->fetchAll(PDO::FETCH_ASSOC));

			if (count($replies) == ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview'])) {
				$count = numPosts($th['id']);
				$omitted = array('post_count' => $count['replies'], 'image_count' => $count['images']);
			} else {
				$omitted = false;
			}

			if ($config['cache']['enabled'])
				cache::set("thread_index_{$board['uri']}_{$th['id']}", array(
					'replies' => $replies,
					'omitted' => $omitted,
				));
		}

		$num_images = 0;
		foreach ($replies as $po) {
			if ($po['file'])
				$num_images++;

			$thread->add(new Post(
				$po['id'], $th['id'], $po['subject'], $po['email'], $po['email_protocol'], $po['name'], $po['trip'], $po['capcode'], $po['body'], $po['time'],
				$po['thumb'], $po['thumb_uri'], $po['thumbwidth'], $po['thumbheight'], $po['file'], $po['filetype'], $po['file_uri'],
				$po['filewidth'], $po['fileheight'], $po['filesize'],
				$po['filename'], $po['ip'], $po['embed'], $mod ? '?/' : $config['root'], $mod, $po['mature'])
			);
		}

		if ($omitted) {
			$thread->omitted = $omitted['post_count'] - ($th['sticky'] ? $config['threads_preview_sticky'] : $config['threads_preview']);
			$thread->omitted_images = $omitted['image_count'] - $num_images;
		}

		$body .= $thread->build(true);
	}

	return array(
		'board' => $board,
		'body' => $body,
		'post_url' => $config['post_url'],
		'config' => $config,
		'boardlist' => createBoardlist($mod)
	);
}

function getPageButtons($pages, $mod=false) {
	global $config, $board;

	$btn = array();
	$root = $config['root'] . ($mod ? 'mod.php?/' : '') . $board['uri'] . '/';

	foreach ($pages as $num => $page) {
		if (isset($page['selected'])) {
			// Previous button
			if ($num == 0) {
				// There is no previous page.
				$btn['prev'] = _('Previous');
			} else {
				$loc = $root .
					($num == 1 ?
						''
					:
						sprintf($config['file_page'], $num)
					);

				$btn['prev'] = '<a href="' . utf8tohtml($loc) . '">' .
					'<button>' . utf8tohtml(_('Previous')) . '</button></a>';
			}

			if ($num == count($pages) - 1) {
				// There is no next page.
				$btn['next'] = _('Next');
			} else {
				$loc = $root . sprintf($config['file_page'], $num + 2);

				$btn['next'] = '<a href="' . utf8tohtml($loc) . '">' .
					'<button>' . utf8tohtml(_('Next')) . '</button></a>';
			}
		}
	}

	return $btn;
}

function getPages($mod=false) {
	global $board, $config;

	// Count threads
	$query = query(sprintf("SELECT COUNT(`id`) as `num` FROM `posts_%s` WHERE `thread` IS NULL", $board['uri'])) or error(db_error());

	$count = current($query->fetch());
	$count = floor(($config['threads_per_page'] + $count - 1) / $config['threads_per_page']);

	if ($count < 1) $count = 1;

	$pages = array();
	for ($x=0;$x<$count && $x<$config['max_pages'];$x++) {
		$pages[] = array(
			'num' => $x+1,
			'link' => $x==0 ? ($mod ? '?/' : $config['root']) . $board['dir'] : ($mod ? '?/' : $config['root']) . $board['dir'] . sprintf($config['file_page'], $x+1)
		);
	}

	return $pages;
}

// Returns an associative array with 'replies' and 'images' keys
function numPosts($id) {
	global $board;
	$query = prepare(sprintf("SELECT COUNT(*) as `num` FROM `posts_%s` WHERE `thread` = :thread UNION ALL SELECT COUNT(*) FROM `posts_%s` WHERE `file` IS NOT NULL AND `thread` = :thread", $board['uri'], $board['uri']));
	$query->bindValue(':thread', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	$num_posts = $query->fetch();
	$num_posts = $num_posts['num'];
	$num_images = $query->fetch();
	$num_images = $num_images['num'];

	return array('replies' => $num_posts, 'images' => $num_images);
}


// Rebuilds the board's index pages.
// $oldbump can optionally be a timestamp. Only pages up until the page
// that would contain a thread with that bump value are built.
function buildIndex($oldbump=false) {
	global $board, $config;

	$pages = getPages();
	$antibot = create_antibot($board['uri']);

	$page = 1;
	while ($page <= $config['max_pages'] && $content = index($page, false, $oldbump)) {
		$filename = $board['dir'] . ($page == 1 ? $config['file_index'] : sprintf($config['file_page'], $page));

		$antibot->reset();

		$content['pages'] = $pages;
		$content['pages'][$page-1]['selected'] = true;
		$content['btn'] = getPageButtons($content['pages']);
		$content['antibot'] = $antibot;

		file_write($filename, Element('index.html', $content));

		$page++;
	}
	if (!$oldbump && $page < $config['max_pages']) {
		for (;$page<=$config['max_pages'];$page++) {
			$filename = $board['dir'] . ($page==1 ? $config['file_index'] : sprintf($config['file_page'], $page));
			file_unlink($filename);
		}
	}
}

function buildJavascript() {
	global $config;

	$styles = array();
	foreach ($config['stylesheets'] as $name => $item) {
		$displayName = $item[0];
		$file = $item[1];
		$styles[] = array(
			'name' => $name,
			'displayName' => $displayName,
			'uri' => empty($file) ? '' :
				$config['uri_stylesheets'] . $file . '?v=' . filemtime('stylesheets/' . $file)
		);
	}

	$SITE_DATA = array(
		'default_stylesheet' => $config['default_stylesheet'],
		'styles' => $styles,
		'cookiename' => $config['cookies']['js'],
		'cookiepath' => $config['cookies']['jail'] ? $config['cookies']['path'] : '/',
		'genpassword_chars' => $config['genpassword_chars'],
		'url_favicon' => $config['url_favicon'],
		'url_favicon_alert' => $config['url_favicon_alert'],
		'siteroot' => $config['root']
	);

	$script = Element('instance.js', array(
		'config' => $config,
		'SITE_DATA' => json_encode($SITE_DATA)
	));

	file_write($config['file_instance_script'], $script);
}

function getBoardConfig($config) {
	return json_encode([
		'image_hard_limit' => $config['image_hard_limit'],
		'reply_hard_limit' => $config['reply_hard_limit'],
		'allow_self_edit' => $config['allow_self_edit'],
		'edit_time_end' => $config['edit_time_end']
	]);
}

function checkDNSBL() {
	global $config;


	if (isIPv6($_SERVER['REMOTE_ADDR']))
		return; // No IPv6 support yet.

	if (!isset($_SERVER['REMOTE_ADDR']))
		return; // Fix your web server configuration

	if (in_array($_SERVER['REMOTE_ADDR'], $config['dnsbl_exceptions']))
		return;

	$ipaddr = ReverseIPOctets($_SERVER['REMOTE_ADDR']);

	foreach ($config['dnsbl'] as $blacklist) {
		if (!is_array($blacklist))
			$blacklist = array($blacklist);

		if (($lookup = str_replace('%', $ipaddr, $blacklist[0])) == $blacklist[0])
			$lookup = $ipaddr . '.' . $blacklist[0];

		if (!$ip = DNS($lookup))
			continue; // not in list

		$blacklist_name = isset($blacklist[2]) ? $blacklist[2] : $blacklist[0];

		if (!isset($blacklist[1])) {
			// If you're listed at all, you're blocked.
			error(sprintf($config['error']['dnsbl'], $blacklist_name));
		} elseif (is_array($blacklist[1])) {
			foreach ($blacklist[1] as $octet) {
				if ($ip == $octet || $ip == '127.0.0.' . $octet)
					error(sprintf($config['error']['dnsbl'], $blacklist_name));
			}
		} elseif (is_callable($blacklist[1])) {
			if ($blacklist[1]($ip))
				error(sprintf($config['error']['dnsbl'], $blacklist_name));
		} else {
			if ($ip == $blacklist[1] || $ip == '127.0.0.' . $blacklist[1])
				error(sprintf($config['error']['dnsbl'], $blacklist_name));
		}
	}
}

function ReverseIPOctets($ip) {
	return implode('.', array_reverse(explode('.', $ip)));
}

function wordfilters(&$body) {
	global $config;

	foreach ($config['wordfilters'] as $filter) {
		if (isset($filter[2]) && $filter[2]) {
			$body = preg_replace($filter[0], $filter[1], $body);
		} else {
			$body = str_ireplace($filter[0], $filter[1], $body);
		}
	}
}

function quote($body, $quote=true) {
	global $config;

	$body = str_replace('<br/>', "\n", $body);

	$body = strip_tags($body);

	$body = preg_replace("/(^|\n)/", '$1&gt;', $body);

	$body .= "\n";

	if ($config['minify_html'])
		$body = str_replace("\n", '&#010;', $body);

	return $body;
}

function markup_url($matches) {
	global $markup_urls;

	$url = $matches[1];
	$after = $matches[2];

	$markup_urls[] = $url;

	return '<a target="_blank" class="bodylink" rel="nofollow" href="' . $url . '">' . $url . '</a>' . $after;
}

function unicodify($body) {
	$body = str_replace('...', '&hellip;', $body);
	$body = str_replace('&lt;--', '&larr;', $body);
	$body = str_replace('--&gt;', '&rarr;', $body);

	// En and em- dashes are rendered exactly the same in
	// most monospace fonts (they look the same in code
	// editors).
	$body = str_replace('---', '&mdash;', $body); // em dash
	$body = str_replace('--', '&ndash;', $body); // en dash

	return $body;
}

function markup(&$body, $track_cites = false, $checkFlood = false) {
	global $board, $config, $markup_urls;

	$body = str_replace("\r", '', $body);
	$body = utf8tohtml($body);

	foreach ($config['markup'] as $markup) {
		if (is_string($markup[1])) {
			$body = preg_replace($markup[0], $markup[1], $body);
		} elseif (is_callable($markup[1])) {
			$body = preg_replace_callback($markup[0], $markup[1], $body);
		}
	}

	$num_links = 0;

	if ($config['user_url_markup']) {
		$markup_urls = array();
		$body = preg_replace_callback(
			'/\[url="?((?:https?|ftp|irc):\/\/[^\s<>"\[\]]+)"?\](.+?)\[\/url\]/',
			function($matches) {
				global $markup_urls;
				$url = $matches[1];
				$text = $matches[2];
				$markup_urls[] = $url;
				return '<a target="_blank" class="bodylink" rel="nofollow" href="' . $url . '">' . $text . '</a>';
			},
			$body,
			($checkFlood ? $config['max_links'] + 1 : -1),
			$num_links);
	}

	if ($config['markup_urls']) {
		if (!isset($markup_urls))
			$markup_urls = array();

		$body = preg_replace_callback('/(?<=^|>)[^<]+/s', function($matches) use (&$num_links, $checkFlood) {
			global $config;
			$res = preg_replace_callback(
				'/((?:https?|ftp|irc):\/\/[^\s<>()"]+?(?:\([^\s<>()"]*?\)[^\s<>()"]*?)*)((?:\s|<|>|"|\.||\]|!|\?|,|&#44;|&quot;)*(?:[\s<>()"]|$))/',
				'markup_url',
				$matches[0],
				($checkFlood ? $config['max_links'] + 1 : -1),
				$more_num_links);
			$num_links += $more_num_links;
			return $res;
		}, $body);
	}

	if ($num_links > $config['max_links'] && $checkFlood)
		error($config['error']['toomanylinks']);

	if ($config['auto_unicode']) {
		$body = unicodify($body);

		if (isset($markup_urls)) {
			foreach ($markup_urls as &$url) {
				$body = str_replace(unicodify($url), $url, $body);
			}
		}
	}

	// replace tabs with 8 spaces
	$body = str_replace("\t", '        ', $body);

	$tracked_cites = array();

	$body = preg_replace_callback('/(?<=^|>)[^<]+/s', function($matches) use ($track_cites, &$tracked_cites, $checkFlood) {
		global $config, $board;
		$body = $matches[0];

		// Cites
		if (isset($board) && preg_match_all('/([^a-z0-9&;]|^)&gt;&gt;(\d+)([^a-z0-9&;]|$)/im', $body, $cites)) {
			if (count($cites[0]) > $config['max_cites'] && $checkFlood) {
				error($config['error']['toomanycites']);
			}

			for ($index=0;$index<count($cites[0]);$index++) {
				$cite = $cites[2][$index];
				$query = prepare(sprintf("SELECT `thread`,`id` FROM `posts_%s` WHERE `id` = :id LIMIT 1", $board['uri']));
				$query->bindValue(':id', $cite);
				$query->execute() or error(db_error($query));

				if ($post = $query->fetch()) {
					$replacement = '<a class="bodylink postlink" href="' .
						$config['root'] . $board['dir'] . $config['dir']['res'] . ($post['thread']?$post['thread']:$post['id']) . '.html#' . $cite . '">' .
							'&gt;&gt;' . $cite .
							'</a>';
					$body = str_replace($cites[0][$index], $cites[1][$index] . $replacement . $cites[3][$index], $body);

					if ($track_cites && $config['track_cites'])
						$tracked_cites[] = array($board['uri'], $post['id']);
				}
			}
		}

		// Cross-board linking
		if (preg_match_all('/([^a-z0-9&;]|^)&gt;&gt;&gt;\/(\w+)\/(\d+)?([^a-z0-9&;]|$)/m', $body, $cites)) {
			if (count($cites[0]) > $config['max_cites']) {
				error($config['error']['toomanycross']);
			}

			for ($index=0;$index<count($cites[0]);$index++) {
				$_board = $cites[2][$index];
				$cite = @$cites[3][$index];

				// Temporarily store board information because it will be overwritten
				$tmp_board = $board['uri'];

				// Check if the board exists, and load settings
				if (openBoard($_board)) {
					if ($cite) {
						$query = prepare(sprintf("SELECT `thread`,`id` FROM `posts_%s` WHERE `id` = :id LIMIT 1", $board['uri']));
						$query->bindValue(':id', $cite);
						$query->execute() or error(db_error($query));

						if ($post = $query->fetch()) {
							$replacement = '<a class="bodylink postlink" href="' .
								$config['root'] . $board['dir'] . $config['dir']['res'] . ($post['thread']?$post['thread']:$post['id']) . '.html#' . $cite . '">' .
									'&gt;&gt;&gt;/' . $_board . '/' . $cite .
									'</a>';
							$body = str_replace($cites[0][$index], $cites[1][$index] . $replacement . $cites[4][$index], $body);

							if ($track_cites && $config['track_cites'])
								$tracked_cites[] = array($board['uri'], $post['id']);
						}
					} else {
						$replacement = '<a class="bodylink" href="' .
							$config['root'] . $board['dir'] . '">' .
								'&gt;&gt;&gt;/' . $_board . '/' .
								'</a>';
						$body = str_replace($cites[0][$index], $cites[1][$index] . $replacement . $cites[4][$index], $body);
					}
				}

				// Restore main board settings
				openBoard($tmp_board);
			}
		}

		return $body;
	}, $body);

	$body = preg_replace("/^\s*&gt;.*$/m", '<span class="quote">$0</span>', $body);
	$body = preg_replace("/^\s*&lt;.*$/m", '<span class="orangetext">$0</span>', $body);

	if ($config['strip_superfluous_returns'])
		$body = preg_replace('/\s+$/', '', $body);

	$body = preg_replace("/\n/", '<br/>', $body);

	return $tracked_cites;
}

function utf8tohtml($utf8) {
	return htmlspecialchars($utf8, ENT_NOQUOTES, 'UTF-8');
}

function buildThread($id, $return=false, $mod=false) {
	global $board, $config;
	$id = round($id);

	if (event('build-thread', $id))
		return;

	if ($config['cache']['enabled'] && !$mod) {
		// Clear cache
		cache::delete("thread_index_{$board['uri']}_{$id}");
		cache::delete("thread_{$board['uri']}_{$id}");
	}

	$query = prepare(sprintf("SELECT *, INET6_NTOA(`ip_data`) AS `ip` FROM `posts_%s` WHERE (`thread` IS NULL AND `id` = :id) OR `thread` = :id ORDER BY `thread`,`id`", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	while ($post = $query->fetch()) {
		if (!isset($thread)) {
			$thread = new Thread(
				$post['id'], $post['subject'], $post['email'], $post['email_protocol'], $post['name'], $post['trip'], $post['capcode'], $post['body'], $post['time'],
				$post['thumb'], $post['thumb_uri'], $post['thumbwidth'], $post['thumbheight'], $post['file'], $post['filetype'], $post['file_uri'], $post['filewidth'], $post['fileheight'], $post['filesize'],
				$post['filename'], $post['ip'], $post['sticky'], $post['locked'], $post['sage'], $post['embed'], $mod ? '?/' : $config['root'], $mod, true, $post['mature'], $post['anon_thread']
			);
		} else {
			$thread->add(new Post(
				$post['id'], $thread->id, $post['subject'], $post['email'], $post['email_protocol'], $post['name'], $post['trip'], $post['capcode'], $post['body'],
				$post['time'], $post['thumb'], $post['thumb_uri'], $post['thumbwidth'], $post['thumbheight'],
				$post['file'], $post['filetype'], $post['file_uri'], $post['filewidth'], $post['fileheight'],
				$post['filesize'], $post['filename'], $post['ip'], $post['embed'], $mod ? '?/' : $config['root'], $mod, $post['mature'])
			);
		}
	}

	// Check if any posts were found
	if (!isset($thread))
		error($config['error']['nonexistant']);

	$hasnoko50 = $thread->postCount() >= $config['noko50_min'];

	$body = Element('thread.html', array(
		'board' => $board,
		'thread' => $thread,
		'body' => $thread->build(),
		'config' => $config,
		'id' => $id,
		'mod' => $mod,
		'hasnoko50' => $hasnoko50,
		'isnoko50' => false,
		'antibot' => $mod ? false : create_antibot($board['uri'], $id),
		'boardlist' => createBoardlist($mod),
		'return' => ($mod ? '?' . $board['url'] : $config['root'] . $board['uri'] . '/')
	));

	if ($return)
		return $body;

	$noko50fn = $board['dir'] . $config['dir']['res'] . sprintf($config['file_page50'], $id);
	if ($hasnoko50 || file_exists($noko50fn)) {
		buildThread50($id, $return, $mod, $thread);
	}

	file_write($board['dir'] . $config['dir']['res'] . sprintf($config['file_page'], $id), $body);

	if ($config['cache']['enabled'] && !$mod)
		cache::set("thread_etag_{$board['uri']}_{$id}", uniqid());
}

function buildThread50($id, $return=false, $mod=false, $thread=null) {
	global $board, $config;
	$id = round($id);

	if (event('build-thread', $id))
		return;

	if (!$thread) {
		$query = prepare(sprintf("SELECT *, INET6_NTOA(`ip_data`) AS `ip` FROM `posts_%s` WHERE (`thread` IS NULL AND `id` = :id) OR `thread` = :id ORDER BY `thread`,`id` DESC LIMIT :limit", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->bindValue(':limit', $config['noko50_count']+1, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$num_images = 0;
		while ($post = $query->fetch()) {
			if (!isset($thread)) {
				$thread = new Thread(
				$post['id'], $post['subject'], $post['email'], $post['email_protocol'], $post['name'], $post['trip'], $post['capcode'], $post['body'], $post['time'],
				$post['thumb'], $post['thumb_uri'], $post['thumbwidth'], $post['thumbheight'], $post['file'], $post['filetype'], $post['file_uri'], $post['filewidth'], $post['fileheight'], $post['filesize'],
				$post['filename'], $post['ip'], $post['sticky'], $post['locked'], $post['sage'], $post['embed'], $mod ? '?/' : $config['root'], $mod, true, $post['mature'], $post['anon_thread']
				);
			} else {
				if ($post['file'])
					$num_images++;

				$thread->add(new Post(
					$post['id'], $thread->id, $post['subject'], $post['email'], $post['email_protocol'], $post['name'], $post['trip'], $post['capcode'], $post['body'],
					$post['time'], $post['thumb'], $post['thumb_uri'], $post['thumbwidth'], $post['thumbheight'],
					$post['file'], $post['filetype'], $post['file_uri'], $post['filewidth'], $post['fileheight'],
					$post['filesize'], $post['filename'], $post['ip'], $post['embed'], $mod ? '?/' : $config['root'], $mod, $post['mature'])
				);
			}
		}

		// Check if any posts were found
		if (!isset($thread))
			error($config['error']['nonexistant']);


		if ($query->rowCount() == $config['noko50_count']+1) {
			$c = numPosts($id);
			$thread->omitted = $c['replies'] - $config['noko50_count'];
			$thread->omitted_images = $c['images'] - $num_images;
		}

		$thread->posts = array_reverse($thread->posts);
	} else {
		$allPosts = $thread->posts;

		$thread->posts = array_slice($allPosts, -$config['noko50_count']);
		$thread->omitted += count($allPosts) - count($thread->posts);
		foreach ($allPosts as $index => $post) {
			if ($index == count($allPosts)-count($thread->posts))
				break;
			if ($post->file)
				$thread->omitted_images++;
		}
	}

	$hasnoko50 = $thread->postCount() >= $config['noko50_min'];

	$body = Element('thread.html', array(
		'board'=>$board,
		'thread' => $thread,
		'body'=>$thread->build(false, true),
		'config' => $config,
		'id' => $id,
		'mod' => $mod,
		'hasnoko50' => $hasnoko50,
		'isnoko50' => true,
		'antibot' => $mod ? false : create_antibot($board['uri'], $id),
		'boardlist' => createBoardlist($mod),
		'return' => ($mod ? '?' . $board['url'] : $config['root'] . $board['uri'] . '/')
	));

	if ($return)
		return $body;

	file_write($board['dir'] . $config['dir']['res'] . sprintf($config['file_page50'], $id), $body);
}

function hashPostPassword($password) {
	return sha1($password);
}

function doesPasswordMatchPostHash($password, $hash) {
	if (strpos($hash, '$MD5$$$$') === 0) {
		return '$MD5$$$$' . md5($password) === $hash;
	} else {
		return sha1($password) === $hash;
	}
}

function editPostForm($postid, $password=false, $mod=false) {
	global $config, $board;

	$query = prepare(sprintf("SELECT *, INET6_NTOA(`ip_data`) AS `ip` FROM `posts_%s` WHERE `id` = :id", $board['uri']));
	$query->bindValue(':id', $postid, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	$post = $query->fetch();
	if(!$post)
		error($config['error']['noedit']);

	// The <textarea> gets screwed up if it's minified.
	$config['minify_html'] = false;
	echo Element('page.html', array(
		'title' => 'Edit Post',
		'config' => $config,
		'boardlist' => createBoardlist($mod),
		'body' => Element('post_edit.html', array(
			'board' => $board,
			'mod' => $mod,
			'config' => $config,
			'post' => $post,
			'password' => $password,
		))));
}

 function rrmdir($dir) {
	if (is_dir($dir)) {
		$objects = scandir($dir);
		foreach ($objects as $object) {
			if ($object != "." && $object != "..") {
				if (filetype($dir."/".$object) == "dir")
					rrmdir($dir."/".$object);
				else
					file_unlink($dir."/".$object);
			}
		}
		reset($objects);
		rmdir($dir);
	}
}

function timezone() {
	// there's probably a much easier way of doing this
	return sprintf("%s%02d", ($hr = (int)floor(($tz = date('Z')) / 3600)) > 0 ? '+' : '-', abs($hr)) . ':' . sprintf("%02d", (($tz / 3600) - $hr) * 60);
}

function poster_id($ip, $thread) {
	global $config;

	if ($id = event('poster-id', $ip, $thread))
		return $id;

	// Confusing, hard to brute-force, but simple algorithm
	return substr(sha1(sha1($ip . $config['secure_trip_salt'] . $thread) . $config['secure_trip_salt']), 0, $config['poster_id_length']);
}

function simple_hash($input) {
	// convert to SHIT_JIS encoding
	$input = mb_convert_encoding($input, 'Shift_JIS', 'UTF-8');

	// generate salt
	$salt = substr($input . 'H..', 1, 2);
	$salt = preg_replace('/[^\.-z]/', '.', $salt);
	$salt = strtr($salt, ':;<=>?@[\]^_`', 'ABCDEFGabcdef');

	return substr(crypt($input, $salt), -10);
}

function secure_hash($input) {
	global $config;

	if ($config['kusabax_secure_tripcode']) {
		$secure_tripcode = md5($input . $config['KU_RANDOMSEED']);
		$secure_tripcode = base64_encode($secure_tripcode);
		$secure_tripcode = str_rot13($secure_tripcode);
		return substr($secure_tripcode, 2, 10);
	} else {
		$count = 1 << $config['secure_count_log2'];

		$hash = md5($config['secure_trip_salt'] . $input, TRUE);
		do {
			$hash = md5($hash . $input, TRUE);
		} while (--$count);

		return substr(base64_encode($hash), 0, 10);
	}
}

function generate_tripcode($name) {
	global $config;

	if ($trip = event('tripcode', $name))
		return $trip;

	if (!preg_match('/^(?P<name>[^#]*)(#(?P<trip>[^#]+))?(##?(?P<secure>[^#]+))?/', $name, $match))
		return array('Tripcode Error'); // Shouldn't ever happen

	$name = isset($match['name']) ? $match['name'] : '';
	$trip = isset($match['trip']) ? $match['trip'] : '';
	$secure = isset($match['secure']) ? $match['secure'] : '';

	$result_trip = '';

	if (strlen($trip) > 0) {
		if (isset($config['custom_tripcode']["#{$trip}"]))
			$result_trip .= $config['custom_tripcode']["#{$trip}"];
		else
			$result_trip .= '!' . simple_hash($trip);
	}

	if (strlen($secure) > 0) {
		if (isset($config['custom_tripcode']["##{$secure}"]))
			$result_trip .= $config['custom_tripcode']["##{$secure}"];
		else
			$result_trip .= '!!' . secure_hash($secure);
	}

	return array($name, $result_trip);
}

// Highest common factor
function hcf($a, $b){
	$gcd = 1;
	if ($a>$b) {
		$a = $a+$b;
		$b = $a-$b;
		$a = $a-$b;
	}
	if ($b==(round($b/$a))*$a)
		$gcd=$a;
	else {
		for ($i=round($a/2);$i;$i--) {
			if ($a == round($a/$i)*$i && $b == round($b/$i)*$i) {
				$gcd = $i;
				$i = false;
			}
		}
	}
	return $gcd;
}

function fraction($numerator, $denominator, $sep) {
	$gcf = hcf($numerator, $denominator);
	$numerator = $numerator / $gcf;
	$denominator = $denominator / $gcf;

	return "{$numerator}{$sep}{$denominator}";
}

function getPostByHash($hash) {
	global $board;
	$query = prepare(sprintf("SELECT `id`,`thread` FROM `posts_%s` WHERE `filehash` = :hash", $board['uri']));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch()) {
		return $post;
	}

	return false;
}

function getPostByHashInThread($hash, $thread) {
	global $board;
	$query = prepare(sprintf("SELECT `id`,`thread` FROM `posts_%s` WHERE `filehash` = :hash AND ( `thread` = :thread OR `id` = :thread )", $board['uri']));
	$query->bindValue(':hash', $hash, PDO::PARAM_STR);
	$query->bindValue(':thread', $thread, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if ($post = $query->fetch()) {
		return $post;
	}

	return false;
}

function undoFile(array $post) {
	if (!$post['has_file'])
		return;

	if (isset($post['file']))
		file_unlink($post['file']);
	if (isset($post['thumb']))
		file_unlink($post['thumb']);
}

function logToFile($filename, $line) {
	$fd = fopen($filename, 'at');
	if (!$fd)
		return false;
	fwrite($fd, $line . "\n");
	fclose($fd);
	return true;
}

function rDNS($ip_addr) {
	global $config;

	if ($config['cache']['enabled'] && ($host = cache::get('rdns_' . $ip_addr))) {
		return $host;
	}

	if (!$config['dns_system']) {
		$host = gethostbyaddr($ip_addr);
	} else {
		$resp = shell_exec('host -W 1 ' . $ip_addr);
		if (preg_match('/domain name pointer ([^\s]+)$/', $resp, $m))
			$host = $m[1];
		else
			$host = $ip_addr;
	}

	if ($config['cache']['enabled'])
		cache::set('rdns_' . $ip_addr, $host, 3600);

	return $host;
}

function DNS($host) {
	global $config;

	if ($config['cache']['enabled'] && ($ip_addr = cache::get('dns_' . $host))) {
		return $ip_addr;
	}

	if (!$config['dns_system']) {
		$ip_addr = gethostbyname($host);
		if ($ip_addr == $host)
			$ip_addr = false;
	} else {
		$resp = shell_exec('host -W 1 ' . $host);
		if (preg_match('/has address ([^\s]+)$/', $resp, $m))
			$ip_addr = $m[1];
		else
			$ip_addr = false;
	}

	if ($config['cache']['enabled'])
		cache::set('dns_' . $host, $ip_addr, 3600);

	return $ip_addr;
}
