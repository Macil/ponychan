/*
 * image-hover.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/image-hover.js';
 *
 */

$(document).ready(function(){
	settings.newProp("image_hover_enabled", "bool", true, "Expand image on hover");
	settings.newProp("image_spoiler_hover_enabled", "bool", false, "Expand spoiler image on hover");

	var image_hover_enabled = settings.getProp("image_hover_enabled");
	var image_spoiler_hover_enabled = settings.getProp("image_spoiler_hover_enabled");
	$(document).on("setting_change", function(e, setting) {
		if (setting == "image_hover_enabled")
			image_hover_enabled = settings.getProp("image_hover_enabled");
		else if (setting == "image_spoiler_hover_enabled")
			image_spoiler_hover_enabled = settings.getProp("image_spoiler_hover_enabled");
	});

	init_image_hover = function() {
		var $image = $(this);
		var is_spoilered = /\/static\/spoiler\.\w+$/.test($image.attr("src"));
		var imageurl = $image.parent().attr("href");
		var hovered_at;
		$image.hover(function(e) {
			if(is_spoilered) {
				if(!image_spoiler_hover_enabled)
					return;
			} else {
				if(!image_hover_enabled)
					return;
			}
			if($image.parent().attr('data-old-src'))
				return;

			hovered_at = {'x': e.pageX, 'y': e.pageY};
			
			var $newImage = $("<img/>");
			$newImage
				.addClass('image-hover')
				.attr('src', imageurl)
				.css('position', 'absolute')
				.css('margin', '0')
				.css('padding', '0')
				.css('maxWidth', '75%')
				.css('maxHeight', '95%')
				.css('z-index', 10)
				.insertAfter($image.parent())
				.load(function() {
					$(this).trigger('mousemove');
				});
			$image.trigger('mousemove');
		}, function() {
			$('.image-hover').remove();
		}).mousemove(function(e) {
			var $hover = $('.image-hover');
			if($hover.length == 0)
				return;
			
			var top = (e.pageY ? e.pageY : hovered_at['y']) - 10;
			
			if(e.pageY < $(window).scrollTop() + 15) {
				top = $(window).scrollTop();
			} else if(e.pageY > $(window).scrollTop() + $(window).height() - $hover.height() - 30) {
				top = $(window).scrollTop() + $(window).height() - $hover.height() - 30;
			}
			
			$hover.css('left', (e.pageX ? e.pageX : hovered_at['x']) + 20).css('top', top);
		}).click(function() {
			$image.trigger('mouseleave');
		});
	};
	
	$('form[name="postcontrols"]>div>a:not([class="file"])>img').each(init_image_hover);
	$('div.post>a:not([class="file"])>img').each(init_image_hover);
	$(document).bind('new_post', function(e, post) {
		$(post).find('>a:not([class="file"])>img').each(init_image_hover);
	});
});
