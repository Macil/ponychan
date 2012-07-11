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
		.css("z-index", 1)
		.css("color", "black")
		.css("border", "1px solid black")
		.css("height", "400px")
		.css("width", "400px")
		.css("position", "fixed")
		.css("top", "50%")
		.css("left", "50%")
		.css("margin-top", "-200px")
		.css("margin-left", "-200px")
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

	$(".styles").css("float", "right");

	var $settingsDiv = $("<div/>")
		.addClass("settingsButton")
		.css("float", "right")
		.css("clear", "left")
		.css("margin", "0px 15px")
		.insertAfter( $(".styles") );

	var $settingsButton = $("<a/>")
		.text("[ Settings ]")
		.attr("href", "javascript:;")
		.appendTo($settingsDiv);

	var $settingsOverlay = $("<div/>")
		.css("background-color", "black")
		.css("opacity", 0.5)
		.css("z-index", 0)
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
		$settingsOverlay.show();
		$settingsScreen.fadeIn("fast");		
	};

	settings.hideWindow = function() {
		$settingsScreen.hide();
		$settingsOverlay.hide();
	};

	$settingsButton.click(settings.showWindow);
	$settingsOverlay.click(settings.hideWindow);
	$settingsCloseButton.click(settings.hideWindow);

	settings.getProp = function(name, type) {
		var id = "setting_"+name;

		if (localStorage[id] == null)
			return undefined;

		switch(type) {
		case "bool":
			return localStorage[id] == "true";
		}

		console.error("Invalid property type: "+type+", name: "+name);
		return undefined;
	}

	settings.setProp = function(name, type, value) {
		var id = "setting_"+name;

		switch(type) {
		case "bool":
			localStorage[id] = value ? "true" : "false";
			break;
		default:
			console.error("Invalid property type: "+type+", name: "+name);
			return;
		}
		$(document).trigger("setting_change", name)
		return value;
	}

	settings.newProp = function(name, type, defval, description) {
		if (type != "bool")
			return;

		var id = "setting_"+name;
		var changeGuard = false;

		var value = settings.getProp(name, type);
		if (value === undefined) {
			value = settings.setProp(name, type, defval);
		}

		var $settingDiv = $("<div/>")
			.appendTo($settingsScreen);

		var $label = $("<label/>")
			.attr("for", id)
			.text(" "+description)
			.appendTo($settingDiv);
		var $checkbox = $("<input/>")
			.attr("type", "checkbox")
			.attr("id", id)
			.prependTo($label)
			.attr("checked", value)
			.change(function() {
				if(!changeGuard) {
					changeGuard = true;
					settings.setProp(name, "bool", $(this).attr("checked"));
					changeGuard = false;
				}
			});

		$(document).on("setting_change", function(e, setting) {
			if (!changeGuard && name == setting) {
				changeGuard = true;
				$checkbox.attr("checked", settings.getProp(name, "bool"));
				changeGuard = false;
			}
		});
	}
});
