<?php

/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

if (realpath($_SERVER['SCRIPT_FILENAME']) == str_replace('\\', '/', __FILE__)) {
	// You cannot request this file directly.
	exit;
}

class Filter {
	private $condition;
	
	public function __construct(array $arr) {
		foreach ($arr as $key => $value)
			$this->$key = $value;		
	}
	
	public function match(array $post, $condition, $match) {
		$condition = strtolower($condition);
		
		switch($condition) {
			case 'custom':
				if (!is_callable($match))
					error('Custom condition for filter is not callable!');
				return $match($post);
			case 'name':
				return preg_match($match, $post['name']);
			case 'trip':
				return $match === $post['trip'];
			case 'email':
				return preg_match($match, $post['email']);
			case 'subject':
				return preg_match($match, $post['subject']);
			case 'body':
				return preg_match($match, $post['body']);
			case 'filename':
				if (!$post['has_file'])
					return false;
				return preg_match($match, $post['filename']);
			case 'extension':
				if (!$post['has_file'])
					return false;
				return preg_match($match, $post['body']);
			case 'ip':
				return preg_match($match, $_SERVER['REMOTE_ADDR']);
			case 'op':
				return $post['op'] == $match;
			case 'has_file':
				return $post['has_file'] == $match;
			default:
				error('Unknown filter condition: ' . $condition);
		}
	}
	
	public function action() {
		global $board;
		
		switch($this->action) {
			case 'reject':
				error(isset($this->message) ? $this->message : 'Posting throttled by flood filter.');
			case 'ban':
				if (!isset($this->reason))
					error('The ban action requires a reason.');
				
				$reason = $this->reason;
				
				if (isset($this->expires))
					$expires = time() + $this->expires;
				else
					$expires = 0; // Ban indefinitely
				
				if (isset($this->reject))
					$reject = $this->reject;
				else
					$reject = true;
				
				if (isset($this->all_boards))
					$all_boards = $this->all_boards;
				else
					$all_boards = false;
				
				$range = parse_mask($_SERVER['REMOTE_ADDR']);
				
				$query = prepare("INSERT INTO `bans` (`ip`,`ip_type`,`range_type`,`range_start`,`range_end`,`mod`,`set`,`expires`,`reason`,`board`) VALUES (:ip, :type, :range_type, INET6_ATON(:range_start), INET6_ATON(:range_end), :mod, :set, :expires, :reason, :board)");
				$query->bindValue(':ip', $_SERVER['REMOTE_ADDR']);
				$query->bindValue(':type', 0);
				$query->bindValue(':range_type', $range['range_type'], PDO::PARAM_INT);
				$query->bindValue(':range_start', $range['range_start']);
				$query->bindValue(':range_end', $range['range_end']);
				$query->bindValue(':mod', -1);
				$query->bindValue(':set', time(), PDO::PARAM_INT);
				
				if ($expires)
					$query->bindValue(':expires', $expires);
				else
					$query->bindValue(':expires', null, PDO::PARAM_NULL);
				
				if ($reason)
					$query->bindValue(':reason', $reason);
				else
					$query->bindValue(':reason', null, PDO::PARAM_NULL);
				
				
				if ($all_boards)
					$query->bindValue(':board', null, PDO::PARAM_NULL);
				else
					$query->bindValue(':board', $board['uri']);
				
				$query->execute() or error(db_error($query));
				
				if ($reject) {
					if (isset($this->message))
						error($message);
					
					checkBan($board['uri']);
					exit;
				}
				
				break;
			default:
				error('Unknown filter action: ' . $this->action);
		}
	}
	
	public function check(array $post) {
		foreach ($this->condition as $condition => $value) {
			if (!$this->match($post, $condition, $value))
				return false;
		}
		
		/* match */
		return true;
	}
}

function do_filters(array $post) {
	global $config;
	
	if (!isset($config['flood_filters']))
		return;
	
	foreach ($config['flood_filters'] as $arr) {
		$filter = new Filter($arr);
		if ($filter->check($post))
			$filter->action();
	}
}

