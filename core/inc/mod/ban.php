<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

if (realpath($_SERVER['SCRIPT_FILENAME']) == str_replace('\\', '/', __FILE__)) {
	// You cannot request this file directly.
	exit;
}

function parse_time($str) {
	if (empty($str))
		return false;

	if (($time = @strtotime($str)) !== false)
		return $time;

	if (!preg_match('/^((\d+)\s?ye?a?r?s?)?\s?+((\d+)\s?mon?t?h?s?)?\s?+((\d+)\s?we?e?k?s?)?\s?+((\d+)\s?da?y?s?)?((\d+)\s?ho?u?r?s?)?\s?+((\d+)\s?mi?n?u?t?e?s?)?\s?+((\d+)\s?s?e?c?o?n?d?s?)?$/', $str, $matches))
		return false;

	$expire = 0;

	if (isset($matches[2])) {
		// Years
		$expire += $matches[2]*60*60*24*365;
	}
	if (isset($matches[4])) {
		// Months
		$expire += $matches[4]*60*60*24*30;
	}
	if (isset($matches[6])) {
		// Weeks
		$expire += $matches[6]*60*60*24*7;
	}
	if (isset($matches[8])) {
		// Days
		$expire += $matches[8]*60*60*24;
	}
	if (isset($matches[10])) {
		// Hours
		$expire += $matches[10]*60*60;
	}
	if (isset($matches[12])) {
		// Minutes
		$expire += $matches[12]*60;
	}
	if (isset($matches[14])) {
		// Seconds
		$expire += $matches[14];
	}

	return time() + $expire;
}

function ban($mask, $reason, $length, $board, $ban_type, $sign) {
	global $mod, $pdo, $config;

	if(mb_strlen(trim($reason)) == 0) {
		error('Reason must be given for ban!');
	}

	$range = parse_mask($mask);
	if ($range === null) {
		error('Invalid ban mask.');
	}

	$ban_type = (int)$ban_type;
	if (
		$ban_type !== FULL_BAN &&
		$ban_type !== IMAGE_BAN &&
		$ban_type !== THREAD_START_BAN
	) {
		error(sprintf($config['error']['invalidfield'], 'ban_type'));
	}

	$query = prepare("INSERT INTO `bans` (`status`,`range_type`,`range_start`,`range_end`,`mod`,`set`,`expires`,`reason`,`board`,`ban_type`,`seen`,`signed_name`,`signed_trip`) VALUES (0, :range_type, INET6_ATON(:range_start), INET6_ATON(:range_end), :mod, :time, :expires, :reason, :board, :ban_type, 0, :signed_name, :signed_trip)");
	$query->bindValue(':range_type', $range['range_type'], PDO::PARAM_INT);
	$query->bindValue(':range_start', $range['range_start']);
	$query->bindValue(':range_end', $range['range_end']);
	$query->bindValue(':mod', $mod['id'], PDO::PARAM_INT);
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':ban_type', $ban_type, PDO::PARAM_INT);
	$query->bindValue(':signed_name', $sign ? $mod['signed_name'] : null);
	$query->bindValue(':signed_trip', $sign ? $mod['signed_trip'] : null);
	if ($reason !== '') {
		markup($reason);
		$query->bindValue(':reason', $reason);
	} else
		$query->bindValue(':reason', null, PDO::PARAM_NULL);

	if ($length > 0)
		$query->bindValue(':expires', $length);
	else
		$query->bindValue(':expires', null, PDO::PARAM_NULL);

	if ($board)
		$query->bindValue(':board', $board);
	else
		$query->bindValue(':board', null, PDO::PARAM_NULL);

	$query->execute() or error(db_error($query));

	$mask_url = str_replace('/', '^', $mask);

	$typeStr = '';
	if ($ban_type !== FULL_BAN) {
		$typeStr = ' (type: ' . ban_type_name($ban_type) . ')';
	}

	modLog('Created a new ' .
		($length > 0 ? preg_replace('/^(\d+) (\w+?)s?$/', '$1-$2', until($length)) : 'permanent') .
		$typeStr .
		' ban (<small>#' . $pdo->lastInsertId() . "</small>) for <a href=\"?/IP/$mask_url\">$mask</a> with " .
		($reason ? 'reason: ' . utf8tohtml($reason) . '' : 'no reason'));
}

function unban($id) {
	$query = prepare("UPDATE `bans` SET `status` = 2, `lifted` = :time WHERE `id` = :id");
	$query->bindValue(':id', $id);
	$query->bindValue(':time', time());
	$query->execute() or error(db_error($query));

	modLog("Removed ban #{$id}");
}
