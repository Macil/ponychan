<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

require 'inc/functions.php';
require 'inc/anti-bot.php';

// Fix for magic quotes
if (get_magic_quotes_gpc()) {
	function strip_array($var) {
		return is_array($var) ? array_map('strip_array', $var) : stripslashes($var);
	}
	
	$_GET = strip_array($_GET);
	$_POST = strip_array($_POST);
}

header("Cache-Control: no-cache, must-revalidate");

if (isset($_POST['delete'])) {
	// Delete
	
	if (!isset($_POST['board'], $_POST['password']))
		error($config['error']['bot']);
	
	$password = $_POST['password'];
	
	if ($password == '')
		error($config['error']['invalidpassword']);
	
	$delete = array();
	foreach ($_POST as $post => $value) {
		if (preg_match('/^delete_(\d+)$/', $post, $m)) {
			$delete[] = (int)$m[1];
		}
	}
	
	checkDNSBL();
		
	// Check if board exists
	if (!openBoard($_POST['board']))
		error($config['error']['noboard']);
	
	// Check if banned
	checkBan($board['uri']);
	
	if (empty($delete))
		error($config['error']['nodelete']);
		
	foreach ($delete as $id) {
		$query = prepare(sprintf("SELECT `thread`, `time`,`password` FROM `posts_%s` WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		if ($post = $query->fetch()) {
			if ($password != '' && $post['password'] != $password)
				error($config['error']['invalidpassword']);
			
			if ($post['time'] >= time() - $config['delete_time']) {
				error(sprintf($config['error']['delete_too_soon'], until($post['time'] + $config['delete_time'])));
			}
			
			if (isset($_POST['file'])) {
				// Delete just the file
				deleteFile($id);
			} else {
				// Delete entire post
				deletePost($id);
			}
			
			_syslog(LOG_INFO, 'Deleted post: ' .
				'/' . $board['dir'] . $config['dir']['res'] . sprintf($config['file_page'], $post['thread'] ? $post['thread'] : $id) . ($post['thread'] ? '#' . $id : '')
			);
		}
	}
	
	buildIndex();
	
	$is_mod = isset($_POST['mod']) && !!$_POST['mod'];
	$root = $is_mod ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];
	
	header('Location: ' . $root . $board['dir'] . $config['file_index'], true, $config['redirect_http']);

} elseif (isset($_POST['edit'])) {
	// User picked a post to edit

	if (!isset($_POST['board'], $_POST['password']))
		error($config['error']['bot']);
	
	$password = $_POST['password'];
	
	if ($password == '')
		error($config['error']['invalidpassword']);
	
	$editposts = array();
	foreach ($_POST as $post => $value) {
		if (preg_match('/^delete_(\d+)$/', $post, $m)) {
			$editposts[] = (int)$m[1];
		}
	}
	
	checkDNSBL();
		
	// Check if board exists
	if (!openBoard($_POST['board']))
		error($config['error']['noboard']);
	
	if (!$config['allow_self_edit'])
		error($config['error']['bot']);

	// Check if banned
	checkBan($board['uri']);
	
	if (empty($editposts))
		error($config['error']['noedit']);
	if (count($editposts)!=1)
		error($config['error']['toomanyedits']);

	if (isset($_POST['mod']) && $_POST['mod']) {
		require 'inc/mod.php';
		if (!$mod) {
			// Liar. You're not a mod.
			error($config['error']['notamod']);
		}
	}

	$id = $editposts[0];	
	$query = prepare(sprintf("SELECT `thread`,`time`,`password` FROM `posts_%s` WHERE `id` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	
	$post = $query->fetch();
	if(!$post)
		error($config['error']['noedit']);

	if ($password != '' && $post['password'] != $password)
		error($config['error']['invalidpassword']);
	
	if ($post['time'] >= time() - $config['edit_time']) {
		error(sprintf($config['error']['edit_too_soon'], until($post['time'] + $config['edit_time'])));
	}

	editPostForm($id, $password, $mod);
} elseif (isset($_POST['editpost'])) {
	// User is submitting an edited post

	if (!isset($_POST['board'], $_POST['id']))
		error($config['error']['bot']);
		
	$id = $_POST['id'];
	
	checkDNSBL();
		
	// Check if board exists
	if (!openBoard($_POST['board']))
		error($config['error']['noboard']);
	
	// Check if banned
	checkBan($board['uri']);
	
	$query = prepare(sprintf("SELECT `thread`,`time`,`password`,`file`,`embed` FROM `posts_%s` WHERE `id` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));
	
	$post = $query->fetch();
	if(!$post)
		error($config['error']['noedit']);

	if ($post['time'] >= time() - $config['edit_time']) {
		error(sprintf($config['error']['edit_too_soon'], until($post['time'] + $config['edit_time'])));
	}

	if (isset($_POST['password']))
		$password = $_POST['password'];

	if (isset($password) && !$config['allow_self_edit'])
		error($config['error']['bot']);

	if (isset($_POST['mod']) && $_POST['mod']) {
		require 'inc/mod.php';
		if (!$mod) {
			// Liar. You're not a mod.
			error($config['error']['notamod']);
		}
		
		$post['raw'] = isset($_POST['raw']);
		$post['noeditmsg'] = isset($_POST['noeditmsg']);
		
		if (!isset($password) && !hasPermission($config['mod']['editpost'], $board['uri']))
			error($config['error']['noaccess']);
		if ($post['raw'] && !hasPermission($config['mod']['rawhtml'], $board['uri']))
			error($config['error']['noaccess']);
		if ($post['noeditmsg'] && !hasPermission($config['mod']['noeditmsg'], $board['uri']))
			error($config['error']['noaccess']);
	} else {
		if (!isset($password))
			error($config['error']['bot']);
	}

	if (isset($password)) {
		if ($password == '' || $post['password'] != $password)
			error($config['error']['invalidpassword']);
	} else {
		// Check the referrer
		if (!isset($_SERVER['HTTP_REFERER']) || !preg_match($config['referer_match'], $_SERVER['HTTP_REFERER']))
			error($config['error']['referer']);
	}
		
	if ($post['time'] >= time() - $config['edit_time']) {
		error(sprintf($config['error']['edit_too_soon'], until($post['time'] + $config['edit_time'])));
	}

	$post['op'] = !$post['thread'];
	$post['body'] = $_POST['body'];

	if (!($post['file'] || isset($post['embed'])) || (($post['op'] && $config['force_body_op']) || (!$post['op'] && $config['force_body']))) {
		$stripped_whitespace = preg_replace('/[\s]/u', '', $post['body']);
		if ($stripped_whitespace == '') {
			error($config['error']['tooshort_body']);
		}
	}

	if (!$mod && mb_strlen($post['body']) > $config['max_body'])
		error($config['error']['toolong_body']);

	wordfilters($post['body']);
	
	$post['body_nomarkup'] = $post['body'];

	if (!($mod && isset($post['raw']) && $post['raw']))
		$post['tracked_cites'] = markup($post['body'], true);

	require_once 'inc/filters.php';
	
	do_filters($post);

	if (!($mod && isset($post['noeditmsg']) && $post['noeditmsg'])) {
		$now = new DateTime();
		$nowStr = $now->format(DateTime::W3C);
		$time = '<time datetime="'.$nowStr.'">'.$nowStr.'</time>';
		$post['body'] .= "\n" . sprintf(isset($password) ? $config['edit_self_message'] : $config['edit_mod_message'], $time);
	}
	
	if (!hasPermission($config['mod']['postunoriginal'], $board['uri']) && $config['robot_enable'] && checkRobot($post['body_nomarkup'])) {
		if ($config['robot_mute']) {
			error(sprintf($config['error']['muted'], mute()));
		} else {
			error($config['error']['unoriginal']);
		}
	}

	$post = (object)$post;
	if ($error = event('post-edit', $post)) {
		error($error);
	}
	$post = (array)$post;
	
	$query = prepare(sprintf("UPDATE `posts_%s` SET `body` = :body, `body_nomarkup` = :body_nomarkup WHERE `id` = :id", $board['uri']));
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->bindValue(':body', $post['body'], PDO::PARAM_STR);
	$query->bindValue(':body_nomarkup', $post['body_nomarkup'], PDO::PARAM_STR);
	$query->execute() or error(db_error($query));

	$query = prepare("DELETE FROM `cites` WHERE `board` = :board AND `post` = :id");
	$query->bindValue(':board', $board['uri']);
	$query->bindValue(':id', $id, PDO::PARAM_INT);
	$query->execute() or error(db_error($query));

	if (isset($post['tracked_cites'])) {
		foreach ($post['tracked_cites'] as $cite) {
			$query = prepare('INSERT INTO `cites` VALUES (:board, :post, :target_board, :target)');
			$query->bindValue(':board', $board['uri']);
			$query->bindValue(':post', $id, PDO::PARAM_INT);
			$query->bindValue(':target_board',$cite[0]);
			$query->bindValue(':target', $cite[1], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));
		}
	}
	
	buildThread($post['op'] ? $id : $post['thread']);

	buildIndex();
	
	if ($config['syslog'])
		_syslog(LOG_INFO, 'Edited post: /' . $board['dir'] . $config['dir']['res'] .
			sprintf($config['file_page'], $post['op'] ? $id : $post['thread']) . (!$post['op'] ? '#' . $id : ''));

	if (isset($config['action_log'])) {
		$logdata = array();
		$logdata['userid'] = $userid;
		$logdata['action'] = 'editpost';
		$logdata['board'] = $board['uri'];
		$logdata['number'] = intval($id);
		$logdata['byauthor'] = isset($password);
		$logdata['time'] = date(DATE_ATOM);
		$logdata['thread'] = $post['op'] ? null : intval($post['thread']);
		$logdata['ip'] = $_SERVER['REMOTE_ADDR'];
		$logdata['commentsimplehash'] = simplifiedHash($post['body_nomarkup']);
		$logline = json_encode($logdata);
		logToFile($config['action_log'], $logline);
	}
	
	rebuildThemes('post');

	$is_mod = isset($_POST['mod']) && $_POST['mod'];
	$root = $is_mod ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];
	
	header('Location: ' . $root . $board['dir'] . $config['file_index'], true, $config['redirect_http']);
} elseif (isset($_POST['report'])) {
	if (!isset($_POST['board'], $_POST['password'], $_POST['reason']))
		error($config['error']['bot']);
	
	$report = array();
	foreach ($_POST as $post => $value) {
		if (preg_match('/^delete_(\d+)$/', $post, $m)) {
			$report[] = (int)$m[1];
		}
	}
	
	checkDNSBL();
		
	// Check if board exists
	if (!openBoard($_POST['board']))
		error($config['error']['noboard']);
	
	// Check if banned
	checkBan($board['uri']);
	
	if (empty($report))
		error($config['error']['noreport']);
	
	if (count($report) > $config['report_limit'])
		error($config['error']['toomanyreports']);
	
	$reason = $_POST['reason'];
	markup($reason);
	
	foreach ($report as $id) {
		$query = prepare(sprintf("SELECT `thread` FROM `posts_%s` WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
		
		$post = $query->fetch();
		
		if ($post) {
			if ($config['syslog'])
				_syslog(LOG_INFO, 'Reported post: ' .
					'/' . $board['dir'] . $config['dir']['res'] . sprintf($config['file_page'], $post['thread'] ? $post['thread'] : $id) . ($post['thread'] ? '#' . $id : '') .
					' for "' . $reason . '"'
				);
			$query = prepare("INSERT INTO `reports` VALUES (NULL, :time, :ip, :board, :post, :reason)");
			$query->bindValue(':time', time(), PDO::PARAM_INT);
			$query->bindValue(':ip', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
			$query->bindValue(':board', $board['uri'], PDO::PARAM_INT);
			$query->bindValue(':post', $id, PDO::PARAM_INT);
			$query->bindValue(':reason', $reason, PDO::PARAM_STR);
			$query->execute() or error(db_error($query));
		}
	}
	
	$is_mod = isset($_POST['mod']) && $_POST['mod'];
	$root = $is_mod ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];
	
	header('Location: ' . $root . $board['dir'] . $config['file_index'], true, $config['redirect_http']);
} elseif (isset($_POST['post'])) {
	
	if (isset($_POST['wantjson']) && $_POST['wantjson'])
		$wantjson = true;
	
	if (!isset($_POST['body'], $_POST['board']))
		error($config['error']['bot']);
	
	if (!isset($_POST['name']))
		$_POST['name'] = $config['anonymous'];
	
	if (!isset($_POST['email']))
		$_POST['email'] = '';
	
	if (!isset($_POST['subject']))
		$_POST['subject'] = '';
	
	if (!isset($_POST['password']))
		$_POST['password'] = '';
	
	$post = array('board' => $_POST['board']);
	
	if (isset($_POST['thread'])) {
		$post['op'] = false;
		$post['thread'] = round($_POST['thread']);
	} elseif ($config['quick_reply'] && isset($_POST['quick-reply'])) {
		$post['op'] = false;
		$post['thread'] = round($_POST['quick-reply']);
	} else
		$post['op'] = true;
	
	if (!(($post['op'] && $_POST['post'] == $config['button_newtopic']) ||
	    (!$post['op'] && $_POST['post'] == $config['button_reply'])))
		error($config['error']['bot']);
	
	// Check the referrer
	if (!isset($_SERVER['HTTP_REFERER']) || !preg_match($config['referer_match'], $_SERVER['HTTP_REFERER']))
		error($config['error']['referer']);
	
	checkDNSBL();
		
	// Check if board exists
	if (!openBoard($post['board']))
		error($config['error']['noboard']);
	
	// Check if banned
	checkBan($board['uri']);
	
	// Check for CAPTCHA right after opening the board so the "return" link is in there
	if ($config['recaptcha']) {
		if (!isset($_POST['recaptcha_challenge_field']) || !isset($_POST['recaptcha_response_field']))
			error($config['error']['bot']);
		// Check what reCAPTCHA has to say...
		$resp = recaptcha_check_answer($config['recaptcha_private'],
			$_SERVER['REMOTE_ADDR'],
			$_POST['recaptcha_challenge_field'],
			$_POST['recaptcha_response_field']);
		if (!$resp->is_valid) {
			error($config['error']['captcha']);
		}
	}
	
	if ($post['mod'] = isset($_POST['mod']) && $_POST['mod']) {
		require 'inc/mod.php';
		if (!$mod) {
			// Liar. You're not a mod.
			error($config['error']['notamod']);
		}
		
		$post['sticky'] = $post['op'] && isset($_POST['sticky']);
		$post['locked'] = $post['op'] && isset($_POST['lock']);
		$post['raw'] = isset($_POST['raw']);
		
		if ($post['sticky'] && !hasPermission($config['mod']['sticky'], $board['uri']))
			error($config['error']['noaccess']);
		if ($post['locked'] && !hasPermission($config['mod']['lock'], $board['uri']))
			error($config['error']['noaccess']);
		if ($post['raw'] && !hasPermission($config['mod']['rawhtml'], $board['uri']))
			error($config['error']['noaccess']);
	}
	
	if (!$post['mod']) {
		$post['antispam_hash'] = checkSpam(array($board['uri'], isset($post['thread']) && !($config['quick_reply'] && isset($_POST['quick-reply'])) ? $post['thread'] : null));
		if ($post['antispam_hash'] === true) {
			if (!$userid) {
				error($config['error']['spam']);
			}
		}
	}
	
	if ($config['robot_enable'] && $config['robot_mute']) {
		checkMute();
	}
	
	//Check if thread exists
	if (!$post['op']) {
		$query = prepare(sprintf("SELECT `sticky`,`locked`,`sage`,`mature`,`body` FROM `posts_%s` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
		$query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
		$query->execute() or error(db_error());
		
		if (!$thread = $query->fetch()) {
			// Non-existant
			error($config['error']['nonexistant']);
		}
	}
	
	// Check for an embed field
	if ($config['enable_embedding'] && isset($_POST['embed']) && !empty($_POST['embed'])) {
		// yep; validate it
		$value = $_POST['embed'];
		foreach ($config['embedding'] as $embed) {
			if (preg_match($embed[0], $value)) {
				// Valid link
				$post['embed'] = $value;
				// This is bad, lol.
				$post['no_longer_require_an_image_for_op'] = true;
				break;
			}
		}
		if (!isset($post['embed'])) {
			error($config['error']['invalid_embed']);
		}
	}
	
	if (!hasPermission($config['mod']['bypass_field_disable'], $board['uri'])) {
		if ($config['field_disable_name'])
			$_POST['name'] = $config['anonymous']; // "forced anonymous"
	
		if ($config['field_disable_email'])
			$_POST['email'] = '';
	
		if ($config['field_disable_password'])
			$_POST['password'] = '';
	
		if ($config['field_disable_subject'] || (!$post['op'] && $config['field_disable_reply_subject']))
			$_POST['subject'] = '';
	}
	
	// Check for a file
	if ($post['op'] && $config['force_image_op'] && !isset($post['no_longer_require_an_image_for_op'])) {
		if (!isset($_FILES['file']['tmp_name']) || $_FILES['file']['tmp_name'] == '')
			error($config['error']['noimage']);
	}
	
	$post['ip'] = $_SERVER['REMOTE_ADDR'];
	$post['userid'] = $userid;
	$post['name'] = $_POST['name'] != '' ? $_POST['name'] : $config['anonymous'];
	$post['subject'] = $_POST['subject'];
	$post['email'] = str_replace(' ', '%20', htmlspecialchars($_POST['email']));
	$post['body'] = $_POST['body'];
	$post['password'] = $_POST['password'];
	$post['has_file'] = !isset($post['embed']) && (($post['op'] && !isset($post['no_longer_require_an_image_for_op']) && $config['force_image_op']) || (isset($_FILES['file']) && $_FILES['file']['tmp_name'] != ''));
	$post['thumb_included'] = $post['has_file'] && (isset($_POST['thumbdurl']) || (isset($_FILES['thumbfile']) && $_FILES['thumbfile']['tmp_name'] != ''));
	$post['thumb_time'] = isset($_POST['thumbtime']) ? $_POST['thumbtime'] : null;

	if ($post['has_file'])
		$post['filename'] = utf8tohtml(get_magic_quotes_gpc() ? stripslashes($_FILES['file']['name']) : $_FILES['file']['name']);
	
	if (!($post['has_file'] || isset($post['embed'])) || (($post['op'] && $config['force_body_op']) || (!$post['op'] && $config['force_body']))) {
		$stripped_whitespace = preg_replace('/[\s]/u', '', $post['body']);
		if ($stripped_whitespace == '') {
			error($config['error']['tooshort_body']);
		}
	}
	
	if (!$post['op']) {
		// Check if thread is locked
		// but allow mods to post
		if ($thread['locked'] && !hasPermission($config['mod']['postinlocked'], $board['uri']))
			error($config['error']['locked']);
		
		$thread['cyclic'] = (stripos($thread['body'], '<span class="hashtag">#cyclic</span>') !== FALSE);

		if ($thread['cyclic']) {
			cyclicThreadCleanup($post['thread']);
			
			// this gets used later elsewhere
			$numposts = numPosts($post['thread']);
		} else {
			$numposts = numPosts($post['thread']);
			
			if ($config['reply_hard_limit'] != 0 && $config['reply_hard_limit'] <= $numposts['replies'])
				error($config['error']['reply_hard_limit']);
			
			if ($post['has_file'] && $config['image_hard_limit'] != 0 && $config['image_hard_limit'] <= $numposts['images'])
				error($config['error']['image_hard_limit']);
		}
	}
		
	if ($post['has_file']) {
		$size = $_FILES['file']['size'];
		if ($size > $config['max_filesize'])
			error(sprintf3($config['error']['filesize'], array(
				'sz' => number_format($size),
				'filesz' => number_format($size),
				'maxsz' => number_format($config['max_filesize'])
			)));
	}
	
	
	$post['capcode'] = false;
	
	if ($mod && preg_match('/^((.+) )?## (.+)$/', $post['name'], $matches)) {
		$name = $matches[2] != '' ? $matches[2] : $config['anonymous'];
		$cap = $matches[3];
		
		if (isset($config['mod']['capcode'][$mod['type']])) {
			if (	$config['mod']['capcode'][$mod['type']] === true ||
				(is_array($config['mod']['capcode'][$mod['type']]) &&
					in_array($cap, $config['mod']['capcode'][$mod['type']])
				)) {
				
				$post['capcode'] = utf8tohtml($cap);
				$post['name'] = $name;
			}
		}
	}
	
	$trip = generate_tripcode($post['name']);
	$post['name'] = $trip[0];
	$post['trip'] = isset($trip[1]) ? $trip[1] : '';
	
	if (strtolower($post['email']) == 'noko') {
		$post['noko'] = true;
		if ($config['hide_noko'])
			$post['email'] = '';
	} else $post['noko'] = false;
	
	if (strtolower($post['email']) == 'sage') {
		$post['sage'] = true;
		if ($config['hide_sage'])
			$post['email'] = '';
	} else $post['sage'] = false;
	
	$post['mature'] = $post['op'] ? false : $thread['mature'];
	
	if (isset($_POST['mature'])) {
		if (!$post['op']) {
			undoImage($post);
			error("Only threads can be set as mature");
		} elseif (!$config['mature_allowed']) {
			undoImage($post);
			error("This board doesn't allow mature content threads");
		} else {
			$post['mature'] = true;
		}
	}
	
	// Allow replies to mature threads to have the #Mature tag
	if (!$post['mature'] && stripos($post['body'], '[#mature]') !== false) {
		if (!$post['op']) {
			undoImage($post);
			error("Only threads can be tagged as mature");
		} elseif (!$config['mature_allowed']) {
			undoImage($post);
			error("This board doesn't allow mature content threads");
		} else {
			$post['mature'] = true;
		}
	}
	
	if ($post['mature'] && $post['op'] && stripos($post['body'], '[#mature]') === false) {
		$post['body'] = "[#Mature]\n" . $post['body'];
	}
	
	if ($post['has_file']) {
		$post['extension'] = strtolower(substr($post['filename'], strrpos($post['filename'], '.') + 1));
		if (isset($config['filename_func']))
			$post['file_id'] = $config['filename_func']($post);
		else
			$post['file_id'] = time() . substr(microtime(), 2, 3);
		
		if ($post['mature'])
			$post['file_id'] = 'mtr_' . $post['file_id'];
		
		$post['file'] = $board['dir'] . $config['dir']['img'] . $post['file_id'] . '.' . $post['extension'];
		$post['thumb'] = $board['dir'] . $config['dir']['thumb'] . $post['file_id'] . '.' . ($config['thumb_ext'] ? $config['thumb_ext'] : $post['extension']);
	}
	
	// Check string lengths
	if (mb_strlen($post['name']) > 75)
		error(sprintf($config['error']['toolong'], 'name'));	
	if (mb_strlen($post['email']) > 254)
		error(sprintf($config['error']['toolong'], 'email'));
	if (mb_strlen($post['subject']) > 100)
		error(sprintf($config['error']['toolong'], 'subject'));
	if (!$mod && mb_strlen($post['body']) > $config['max_body'])
		error($config['error']['toolong_body']);
	if (mb_strlen($post['password']) > 20)
		error(sprintf($config['error']['toolong'], 'password'));
	
	wordfilters($post['body']);
	
	$post['body_nomarkup'] = $post['body'];
	
	if (!($mod && isset($post['raw']) && $post['raw']))
		$post['tracked_cites'] = markup($post['body'], true);
	
	// Check for a flood
	if (!hasPermission($config['mod']['flood'], $board['uri']) && checkFlood($post)) {
		error($config['error']['flood']);
	}
	
	require_once 'inc/filters.php';
	
	do_filters($post);
	
	if ($post['has_file']) {
		if (!in_array($post['extension'], $config['allowed_ext']) && !in_array($post['extension'], $config['allowed_ext_files']))
			error($config['error']['unknownext']);
		
		$is_an_image = !in_array($post['extension'], $config['allowed_ext_files']);
		
		// Truncate filename if it is too long
		$post['filename'] = mb_substr($post['filename'], 0, $config['max_filename_len']);
		
		$upload = $_FILES['file']['tmp_name'];
		
		if (!is_readable($upload))
			error($config['error']['nomove']);
		
		$post['filehash'] = $config['file_hash']($upload);
		$post['filesize'] = filesize($upload);
		
		if ($is_an_image) {
			// Check IE MIME type detection XSS exploit
			$buffer = file_get_contents($upload, null, null, null, 255);
			if (preg_match($config['ie_mime_type_detection'], $buffer)) {
				undoImage($post);
				error($config['error']['mime_exploit']);
			}
			
			require_once 'inc/image.php';
			
			// find dimensions of an image using GD
			if (!$size = @getimagesize($upload)) {
				error($config['error']['invalidimg']);
			}
			if ($size[0] > $config['max_width'] || $size[1] > $config['max_height']) {
				error($config['error']['maxsize']);
			}
			
			
			if ($post['extension'] == 'jpg' || $post['extension'] == 'jpeg') {
				// The following code corrects the image orientation.
				// Currently only works with the 'convert' option selected but it could easily be expanded to work with the rest if you can be bothered.
				if (!($config['redraw_image'] || ($config['strip_exif'] && ($post['extension'] == 'jpg' || $post['extension'] == 'jpeg')))) {
					if ($config['thumb_method'] == 'convert' || $config['thumb_method'] == 'convert+gifsicle') {
						$exif = @exif_read_data($upload);
						if (isset($exif['Orientation']) && $exif['Orientation'] != 1) {
							shell_exec('convert ' . escapeshellarg($upload) . ' -auto-orient ' . escapeshellarg($upload));							
						}
					}
				}
			}
			
			// create image object
			$image = new Image($upload, $post['extension']);
			
			if ($image->size->width > $config['max_width'] || $image->size->height > $config['max_height']) {
				$image->delete();
				error($config['error']['maxsize']);
			}
			
			$post['width'] = $image->size->width;
			$post['height'] = $image->size->height;
			
			if ($config['spoiler_images'] && isset($_POST['spoiler'])) {
				$post['thumb'] = 'spoiler';
				
				$size = @getimagesize($config['spoiler_image']);
				$post['thumbwidth'] = $size[0];
				$post['thumbheight'] = $size[1];
			} elseif (($config['minimum_copy_resize'] && filesize($upload) < $config['max_thumb_filesize']) &&
				$image->size->width <= $config['thumb_width'] &&
				$image->size->height <= $config['thumb_height'] &&
				(!$config['thumb_ext'] || $post['extension'] == $config['thumb_ext'])) {
			
				// Copy, because there's nothing to resize
				copy($upload, $post['thumb']);
			
				$post['thumbwidth'] = $image->size->width;
				$post['thumbheight'] = $image->size->height;
			} else {
				if ($post['thumb_included']) {
					$post['thumb_included'] = false;
					timing_mark('thumb_inc_start');
					if (isset($_POST['thumbdurl'])) {
						if (strlen($_POST['thumbdurl']) < $config['max_thumb_filesize'] &&
						    preg_match('/^data:image\/png;base64,(.*)$/', $_POST['thumbdurl'], $data)) {
							$data = base64_decode($data[1], true);
							if ($data) {
								$fd = fopen($post['thumb'], 'wb');
								if ($fd) {
									fwrite($fd, $data);
									fclose($fd);
								}
							}
						}
					} else {
						$size = $_FILES['thumbfile']['size'];
						if ($size < $config['max_thumb_filesize'])
							copy($_FILES['thumbfile']['tmp_name'], $post['thumb']);
					}
					if (is_readable($post['thumb'])) {
						// find dimensions of an image using GD
						if ($size = @getimagesize($post['thumb'])) {
							$thumb_max_width = $post['op'] ? $config['thumb_op_width'] : $config['thumb_width'];
							$thumb_max_height = $post['op'] ? $config['thumb_op_height'] : $config['thumb_height'];
							if ($size[0] <= $thumb_max_width && $size[1] <= $thumb_max_height) {
								$post['thumb_included'] = true;
								$thumb = new Image($post['thumb'], 'png');
								$thumb = $thumb->image;
							}
						}
					}
					if (!$post['thumb_included']) {
						unlink($post['thumb']);
					}
					timing_mark('thumb_inc_end');
				}
				
				if (!$post['thumb_included']) {
					timing_mark('thumb_resize_start');
					$thumb = $image->resize(
						$config['thumb_ext'] ? $config['thumb_ext'] : $post['extension'],
						$post['op'] ? $config['thumb_op_width'] : $config['thumb_width'],
						$post['op'] ? $config['thumb_op_height'] : $config['thumb_height']
					);
					timing_mark('thumb_resize_end');
					
					$thumb->to($post['thumb']);
				}
			
				$post['thumbwidth'] = $thumb->width;
				$post['thumbheight'] = $thumb->height;
			
				$thumb->_destroy();
			}
			
			if ($config['redraw_image'] || ($config['strip_exif'] && ($post['extension'] == 'jpg' || $post['extension'] == 'jpeg'))) {
				$image->to($post['file']);
				$dont_copy_file = true;
			}
			$image->destroy();
		} else {
			// not an image
			//copy($config['file_thumb'], $post['thumb']);
			$post['thumb'] = 'file';
			
			$size = @getimagesize($config['file_thumb']);
			$post['thumbwidth'] = $size[0];
			$post['thumbheight'] = $size[1];
		}
		
		if (!isset($dont_copy_file) || !$dont_copy_file) {
			if (!@move_uploaded_file($_FILES['file']['tmp_name'], $post['file']))
				error($config['error']['nomove']);
		}
	}
	
	if ($post['has_file']) {
		if ($config['image_reject_repost']) {
			if ($p = getPostByHash($post['filehash'])) {
				undoImage($post);
				error(sprintf($config['error']['fileexists'], 
					( $post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root'] ) .
					$board['dir'] . $config['dir']['res'] .
						($p['thread'] ?
							$p['thread'] . '.html#' . $p['id']
						:
							$p['id'] . '.html'
						)
				));
			}
		} else if (!$post['op'] && $config['image_reject_repost_in_thread']) {
			if ($p = getPostByHashInThread($post['filehash'], $post['thread'])) {
				undoImage($post);
				error(sprintf($config['error']['fileexistsinthread'], 
					( $post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root'] ) .
					$board['dir'] . $config['dir']['res'] .
						($p['thread'] ?
							$p['thread'] . '.html#' . $p['id']
						:
							$p['id'] . '.html'
						)
				));
			}
		}
	}
	
	if (!hasPermission($config['mod']['postunoriginal'], $board['uri']) && $config['robot_enable'] && checkRobot($post['body_nomarkup'])) {
		undoImage($post);
		if ($config['robot_mute']) {
			error(sprintf($config['error']['muted'], mute()));
		} else {
			error($config['error']['unoriginal']);
		}
	}
	
	// Remove board directories before inserting them into the database.
	if ($post['has_file']) {
		$post['file_path'] = $post['file'];
		$post['file'] = substr_replace($post['file'], '', 0, mb_strlen($board['dir'] . $config['dir']['img']));
		if ($is_an_image && $post['thumb'] != 'spoiler')
			$post['thumb'] = substr_replace($post['thumb'], '', 0, mb_strlen($board['dir'] . $config['dir']['thumb']));
	}
	
	$post = (object)$post;
	if ($error = event('post', $post)) {
		undoImage((array)$post);
		error($error);
	}
	$post = (array)$post;
	
	$post['id'] = $id = post($post);
	
	if (isset($post['antispam_hash'])) {
		incrementSpamHash($post['antispam_hash']);
	}
	
	if (isset($post['tracked_cites'])) {
		foreach ($post['tracked_cites'] as $cite) {
			$query = prepare('INSERT INTO `cites` VALUES (:board, :post, :target_board, :target)');
			$query->bindValue(':board', $board['uri']);
			$query->bindValue(':post', $id, PDO::PARAM_INT);
			$query->bindValue(':target_board',$cite[0]);
			$query->bindValue(':target', $cite[1], PDO::PARAM_INT);
			$query->execute() or error(db_error($query));
		}
	}
	
	timing_mark('build_thread_start');
	buildThread($post['op'] ? $id : $post['thread']);
	timing_mark('build_thread_end');
	
	if (!$post['op'] && !$post['sage'] && !$thread['sage'] && ($config['reply_limit'] == 0 || $numposts['replies']+1 < $config['reply_limit'])) {
		bumpThread($post['thread']);
	}
	
	if ($post['op'])
		clean();
	
	event('post-after', $post);
	
	timing_mark('build_index_start');
	buildIndex();
	timing_mark('build_index_end');
	
	if (isset($_SERVER['HTTP_REFERER'])) {
		// Tell Javascript that we posted successfully
		if (isset($_COOKIE[$config['cookies']['js']]))
			$js = json_decode($_COOKIE[$config['cookies']['js']]);
		else
			$js = (object) array();
		// Tell the client it doesn't need to remember the post
		$thread_id = $board['uri'] . ':' . ($post['op'] ? 0 : $post['thread']);
		$js->{$thread_id} = intval($id);
		// Encode and set cookie
		setcookie($config['cookies']['js'], json_encode($js), 0, $config['cookies']['jail'] ? $config['cookies']['path'] : '/', null, false, false);
	}
	
	$root = $post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];
	
	if ($wantjson || $config['always_noko'] || $post['noko']) {
		$redirect = $root . $board['dir'] . $config['dir']['res'] .
			sprintf($config['file_page'], $post['op'] ? $id:$post['thread']) . (!$post['op'] ? '#' . $id : '');

	   	if (!$post['op'] && isset($_SERVER['HTTP_REFERER'])) {
			$regex = array(
				'board' => str_replace('%s', '(\w{1,8})', preg_quote($config['board_path'], '/')),
				'page' => str_replace('%d', '(\d+)', preg_quote($config['file_page'], '/')),
				'page50' => str_replace('%d', '(\d+)', preg_quote($config['file_page50'], '/')),
				'res' => preg_quote($config['dir']['res'], '/'),
			);

			if (preg_match('/\/' . $regex['board'] . $regex['res'] . $regex['page50'] . '([?&#].*)?$/', $_SERVER['HTTP_REFERER'])) {
				$redirect = $root . $board['dir'] . $config['dir']['res'] .
					sprintf($config['file_page50'], $post['op'] ? $id:$post['thread']) . (!$post['op'] ? '#' . $id : '');
			}
		}
	} else {
		$redirect = $root . $board['dir'] . $config['file_index'];
		
	}
	
	if ($config['syslog'])
		_syslog(LOG_INFO, 'New post: /' . $board['dir'] . $config['dir']['res'] .
			sprintf($config['file_page'], $post['op'] ? $id : $post['thread']) . (!$post['op'] ? '#' . $id : ''));
	
	if (isset($config['action_log'])) {
		$logdata = array();
		$logdata['userid'] = $post['userid'];
		$logdata['action'] = 'post';
		$logdata['board'] = $board['uri'];
		$logdata['number'] = intval($id);
		$logdata['time'] = date(DATE_ATOM);
		$logdata['thread'] = $post['op'] ? null : intval($post['thread']);
		$logdata['ip'] = $post['ip'];
		$logdata['name'] = $post['name'];
		if ($post['trip'])
			$logdata['trip'] = $post['trip'];
		if ($post['email'])
			$logdata['email'] = $post['email'];
		if ($post['subject'])
			$logdata['subject'] = $post['subject'];
		if ($post['capcode'])
			$logdata['capcode'] = $post['capcode'];
		if ($post['has_file']) {
			$logdata['filehash'] = $post['filehash'];
			$logdata['filesize'] = $post['filesize'];
			$logdata['filename'] = $post['filename'];
			$logdata['thumb_included'] = $post['thumb_included'];
			if (isset($post['thumb_time']))
				$logdata['thumb_time'] = intval($post['thumb_time']);
		}
		$logdata['commentsimplehash'] = simplifiedHash($post['body_nomarkup']);
		$logline = json_encode($logdata);
		logToFile($config['action_log'], $logline);
	}
	
	rebuildThemes($post['op'] ? 'post-thread' : 'post-reply', $board['uri']);
	
	if ($wantjson) {
		$response = array();
		$response['status'] = 'success';
		$response['postid'] = intval($id);
		$response['threadid'] = $post['op'] ? null : intval($post['thread']);
		$response['board'] = $board['uri'];
		$response['url'] = $redirect;
		
		header('Content-Type: application/json');
		die(json_encode($response));
	}
	
	header('Location: ' . $redirect, true, $config['redirect_http']);
} else {
	if (!file_exists($config['has_installed'])) {
		header('Location: install.php', true, $config['redirect_http']);
	} else {
		// They opened post.php in their browser manually.
		if (isset($config['error_log'])) {
			$logdata = array();
			$logdata['userid'] = $userid;
			$logdata['action'] = 'post-failed-attempt';
			$logdata['time'] = date(DATE_ATOM);
			$logdata['ip'] = $_SERVER['REMOTE_ADDR'];

			if (isset($_POST['name']))
				$_POST['name'] = 'removed';
			if (isset($_POST['password']))
				$_POST['password'] = 'removed';
			$logdata['POST'] = $_POST;
			
			$logline = json_encode($logdata);
			logToFile($config['error_log'], $logline);
		}
		error($config['error']['nopost']);
	}
}

