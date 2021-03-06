/*
 * inline-expanding.js
 *
 * Released under the MIT license
 * Copyright (c) 2012 Michael Save <savetheinternet@tinyboard.org>
 * Copyright (c) 2012 Alyssa Rowan <alyssa.rowan@gmail.com>
 * Copyright (c) 2013 Macil Tech <maciltech@gmail.com>
 *
 */

import $ from 'jquery';
import settings from '../settings';

settings.newSetting("image_expand_enabled", "bool", true, "Expand image on click", 'links', {orderhint:3});

$(document).ready(function(){
	var image_expand_enabled = settings.getSetting("image_expand_enabled");
	$(document).on("setting_change", function(e, setting) {
		if (setting == "image_expand_enabled")
			image_expand_enabled = settings.getSetting("image_expand_enabled");
	});

	function remove_inlined_content($img) {
		if ($img.parent().hasClass('expanded')) {
			$img
				.attr({src: $img.attr('data-old-src')})
				.removeAttr('data-old-src')
				.removeClass('expanded').removeClass('loading');
			$img.parent().show().removeClass('expanded');
			$img.parent().next('video').each(function() {
				if (this.pause) this.pause();
				this.removeAttribute("src");
			}).remove();
		}
	}

	function init_expand_image() {
		const $img = $(this);
		const $a = $img.parent();
		remove_inlined_content($img);

		$a.click(function(e) {
			if(!image_expand_enabled || e.which == 2 || e.ctrlKey || e.altKey)
				return true;

			const $a = $(this);
			const $img = $a.children('img');

			if (!$a.hasClass('expanded')) {
				$a.addClass('expanded');
				if ($img.parent().hasClass('video') || $img.parent().hasClass('silentvideo')) {
					$a.hide();
					$('<video/>')
						.addClass('postimg')
						.addClass('expanded')
						.prop({
							src: $a.attr('href'),
							loop: true,
							controls: true,
							volume: settings.getSetting('file_default_volume') / 10,
							autoplay: true
						})
						.click(function(e) {
							if (e.offsetY < 40 || this.offsetHeight - e.offsetY > 40) {
								e.preventDefault();
								if (this.pause) this.pause();
								this.removeAttribute("src");
								$(this).remove();
								$a.click();
							}
						})
						.insertAfter($img.parent());
				} else {
					$img
						.attr({
							'data-old-src': $img.attr('src'),
							src: $a.attr('href'),
						})
						.addClass('expanded')
						.addClass('loading')
						.on('load', function() {
							$(this).removeClass('loading');
						});
				}
			} else {
				$img
					.attr({src: $img.attr('data-old-src')})
					.removeAttr('data-old-src')
					.removeClass('expanded').removeClass('loading');
				$img.parent().show().removeClass('expanded');
				$img.next('video').remove();
			}
			e.stopPropagation();
			e.preventDefault();
			return false;
		});
	}

	$('a:not([class="file"]) > img.postimg').each(init_expand_image);
	$(document).on('new_post', (e, post) => {
		$(post).find('> a:not([class="file"]) > img.postimg').each(init_expand_image);
	});
	$(document).on('removing_post', (e) => {
		$('a:not([class="file"]) > img.postimg', e.target).each(function() {
			remove_inlined_content($(this));
		});
	});
});
