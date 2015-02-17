/*
 * styles.js
 *
 * Released under the MIT license
 * Copyright (c) 2013 Macil Tech <maciltech@gmail.com>
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/settings.js';
 *   $config['additional_javascript'][] = 'js/styles.js';
 *
 */

import settings from './settings';

var log_error = require('./logger').log_error;

var styleChoices = {};
$.each(styles, function(name, file) {
	styleChoices[name] = name;
});

settings.newSetting("style", "select", selectedstyle, "Theme", 'pagestyle',
		    {orderhint: 1, selectOptions: styleChoices, defpriority: 0});

function apply(stylename) {
	if (styles[stylename] == null) {
		console.log('Unknown style:', stylename);
		return;
	}
	var $stylesheet = $("#stylesheet");
	if ($stylesheet.length == 0) {
		$stylesheet = $("<link/>")
			.attr("rel", "stylesheet")
			.attr("type", "text/css")
			.attr("id", "stylesheet")
			.appendTo(document.head);
	}
	$stylesheet.attr("href", styles[stylename]);

	$(document).trigger('style_changed', $stylesheet[0]);
}
exports.apply = apply;

function applySelectedStyle() {
	apply(settings.getSetting("style"));
}

applySelectedStyle();
$(document).on("setting_change", function(e, setting) {
	if (setting === "style") {
		applySelectedStyle();
	}
});
