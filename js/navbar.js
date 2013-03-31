/*
 * navbar.js
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/settings.js';
 *   $config['additional_javascript'][] = 'js/navbar.js';
 *
 */

(function() {
	var defval = true;
	if(navigator.userAgent.match(/iPhone|iPod|Android|Opera Mini|Blackberry|PlayBook/i)) {
		defval = false;
	}
	settings.newSetting("fixed_navbar", "bool", defval, "Navbar stays at the top of the window", 'pagestyle', {orderhint:3});
})();

$(document).ready(function(){
	var navbarApplySettings = function() {
		var $navbar = $(".boardlist.top");
		if (settings.getSetting("fixed_navbar")) {
			$navbar.css("position", "fixed");
		} else {
			$navbar.css("position", "absolute");
		}
		$("header, .top_notice").css("margin-top", (16+$navbar.height())+"px");
	};

	navbarApplySettings();
	$(document).on("setting_change", function(e, setting) {
		if (setting == "fixed_navbar") {
			navbarApplySettings();
		}
	});
});
