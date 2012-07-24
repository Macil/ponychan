/*
 * settings.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/settings.js';
 *
 */

$(document).ready(function(){
	var $settingsScreen = $("<div/>")
		.attr("id", "settingsScreen")
		.css("z-index", 20)
		.css("clear", "both")
		.css("color", "black")
		.css("border", "1px solid black")
		.css("height", "400px")
		.css("width", "400px")
		.css("background-color", "rgb(128,150,150)")
		.appendTo(document.body)
		.hide();

	var $settingsTitle = $("<h1/>")
		.text("Board Settings")
		.appendTo($settingsScreen);

	var $settingsCloseButton = $("<a/>")
		.css("float", "right")
		.css("margin", "2px")
		.css("text-decoration", "none")
		.text("X")
		.attr("href", "javascript:;")
		.appendTo($settingsTitle);

	$("<hr/>").appendTo($settingsScreen);

	var $settingsButton = $("<a/>")
		.text("settings")
		.attr("href", "javascript:;");

	var $settingsSection = $("<span/>")
		.addClass("settingsButton")
		.addClass("boardlistpart")
		.append('[ ', $settingsButton, ' ]')
		.appendTo( $(".boardlist") );

	var $settingsOverlay = $("<div/>")
		.css("background-color", "black")
		.css("opacity", 0.5)
		.css("z-index", 19)
		.css("position", "fixed")
		.css("top", "0px")
		.css("left", "0px")
		.css("width", "100%")
		.css("height", "100%")
		.appendTo(document.body)
		.hide();

	// DOM setup over

	settings = {};

	settings.showWindow = function() {
		$settingsScreen
			.css("position", "absolute")
			.css("top", ( $(window).scrollTop()+$(window).height()/2 ) + "px")
			.css("left", "50%")
			.css("margin-top", "-200px")
			.css("margin-left", "-200px");

		$settingsOverlay.show();
		$settingsScreen.fadeIn("fast");
	};

	settings.hideWindow = function() {
		$settingsScreen.hide();
		$settingsOverlay.hide();
	};

	$(".settingsButton a").click(settings.showWindow);
	$settingsOverlay.click(settings.hideWindow);
	$settingsCloseButton.click(settings.hideWindow);

	var settingTypes = {};
	var defaultValues = {};
	var settingSelectOptions = {};

	settings.getProp = function(name) {
		var id = "setting_"+name;

		var localVal = localStorage[id];
		if (localVal == null)
			return defaultValues[name];

		var type = settingTypes[name];
		switch(type) {
		case "bool":
			return localVal == "true";
		case "select":
			return localVal;
		}

		console.error("Invalid property type: "+type+", name: "+name);
		return undefined;
	}

	settings.setProp = function(name, value) {
		var id = "setting_"+name;

		var type = settingTypes[name];
		switch(type) {
		case "bool":
			localStorage[id] = value ? "true" : "false";
			break;
		case "select":
			localStorage[id] = value;
			break;
		default:
			console.error("Invalid property type: "+type+", name: "+name);
			return;
		}
		$(document).trigger("setting_change", name)
		return value;
	}

	settings.bindPropCheckbox = function($checkbox, name) {
		var changeGuard = false;
		if (settingTypes[name] !== "bool") {
			console.error("Can not bind checkbox to non-bool setting ("+name+", type:"+settingTypes[name]+")");
			return;
		}
		var value = settings.getProp(name);
		
		$checkbox
			.attr("checked", value)
			.change(function() {
				if(!changeGuard) {
					changeGuard = true;
					settings.setProp(name, $(this).attr("checked"));
					changeGuard = false;
				}
			});

		$(document).on("setting_change", function(e, setting) {
			if (!changeGuard && name == setting) {
				changeGuard = true;
				$checkbox.attr("checked", settings.getProp(name));
				changeGuard = false;
			}
		});
	};

	settings.bindPropSelect = function($select, name) {
		var changeGuard = false;
		if (settingTypes[name] !== "select") {
			console.error("Can not bind select to non-select setting ("+name+", type:"+settingTypes[name]+")");
			return;
		}
		var value = settings.getProp(name);
		var choices = settingSelectOptions[name];
		
		$.each(choices, function(key, text) {
			$("<option/>").attr("value", key).text(text).appendTo($select);
		});

		$select
			.val(settings.getProp(name))
			.change(function() {
				if(!changeGuard) {
					changeGuard = true;
					settings.setProp(name, $(this).val());
					changeGuard = false;
				}
			});

		$(document).on("setting_change", function(e, setting) {
			if (!changeGuard && name == setting) {
				changeGuard = true;
				$select.val(settings.getProp(name));
				changeGuard = false;
			}
		});
	};

	settings.newProp = function(name, type, defval, description) {
		var id = "setting_"+name;

		settingTypes[name] = type;
		defaultValues[name] = defval;

		if (type==="bool") {
			var $settingDiv = $("<div/>")
				.appendTo($settingsScreen);

			var $label = $("<label/>")
				.attr("for", id)
				.text(" "+description)
				.appendTo($settingDiv);
			var $checkbox = $("<input/>")
				.attr("type", "checkbox")
				.attr("id", id)
				.prependTo($label);

			settings.bindPropCheckbox($checkbox, name);
		} else if (type==="select") {
			settingSelectOptions[name] = description[0];
			description = description[1];
			var $settingDiv = $("<div/>")
				.text(" "+description)
				.appendTo($settingsScreen);
			var $settingSelect = $("<select/>").prependTo($settingDiv);
			settings.bindPropSelect($settingSelect, name);
		} else {
			console.error("Unknown setting type ("+name+", type:"+type+")");
			return;
		}
	};
});
