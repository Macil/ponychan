/*
 * Adds " (OP)" to >>X links when the OP is quoted.
 * Adds " (You)" to >>X links when the user is quoted.
 *
 * Released under the MIT license
 * Copyright (c) 2015 Macil Tech <maciltech@gmail.com>
 */

import _ from 'lodash';
import $ from 'jquery';
import settings from './settings';
import setCss from './set-css';
import {log_error} from './logger';
import {get_post_num, get_post_board} from './post-info';

const REMEMBER_LIMIT = 1000;
let myposts = [];

const myPosts = {
	contains(id) {
		// TODO use a map
		return !!_.find(myposts, post => post.id === id);
	}
};
export default myPosts;

settings.newSetting("link_show_you", "bool", true, 'Show "(You)" on links to your posts', 'links', {orderhint:6});

function normalizePosts(posts) {
	return _.chain(posts)
		.uniq(post => post.id)
		.sortBy(post => post.timestamp)
		.takeRight(REMEMBER_LIMIT)
		.value();
}

function loadMyPosts() {
	try {
		const parts = [myposts];

		const smyposts = window.sessionStorage && sessionStorage.getItem('myposts');
		if (smyposts) {
			parts.push(JSON.parse(smyposts).map(post => {
				// little bit of backwards-compatibility
				if (typeof post === 'string') {
					return {id: post, timestamp: Date.now()-1};
				} else {
					return post;
				}
			}));
		}

		const lmyposts = window.localStorage && localStorage.getItem('myposts');
		if (lmyposts) {
			parts.push(JSON.parse(lmyposts));
		}

		myposts = normalizePosts(_.flatten(parts));
	} catch(e) {
		console.error('myposts load failure', e);
	}
}
function saveMyPosts() {
	try {
		if (window.localStorage && !localStorage.POLYFILLED) {
			localStorage.setItem('myposts', JSON.stringify(myposts));
			sessionStorage.removeItem('myposts');
		} else if (window.sessionStorage) {
			sessionStorage.setItem('myposts', JSON.stringify(myposts));
		}
	} catch(e) {
		console.error('myposts save failure', e);
	}
}
loadMyPosts();

function descLinks() {
	var $thread;
	if ($(this).hasClass('thread'))
		$thread = $(this);
	else
		$thread = $(this).parents('.thread').first();

	if (!$thread.length) {
		if ($('.thread').length == 1)
			$thread = $('.thread');
		else
			return;
	}

	var $OP = $thread.find('div.post.op');
	var OP = get_post_num($OP);
	var board = get_post_board($OP);

	$(this).find('a.bodylink.postlink').each(function() {
		var match = $(this).text().match(/^>>(\d+)/);
		if (match) {
			var postnum = parseInt(match[1]);

			if (postnum == OP && !$(this).children(".opnote").length) {
				$(this).append( $('<span/>').addClass('opnote').text(' (OP)') );
			}

			var postid = board+':'+postnum;
			if (myPosts.contains(postid) && !$(this).children(".younote").length) {
				$(this).append( $('<span/>').addClass('younote').text(' (You)') );
			}
		}
	});
}

function updateLinkInfo() {
	if (settings.getSetting("link_show_you"))
		setCss("linkinfo", "");
	else
		setCss("linkinfo", ".younote { display: none; }");
}

$(document).on('post_submitted', function(e, info) {
	loadMyPosts();
	myposts.push({id: `${info.board}:${info.postid}`, timestamp: Date.now()});
	saveMyPosts();
}).ready(function() {
	updateLinkInfo();

	$('.thread').each(descLinks);

	$(document).on('new_post', function(e, post) {
		$(post).each(descLinks);
	}).on("setting_change", function(e, setting) {
		if (setting == "link_show_you")
			updateLinkInfo();
	});
});
