settings.newProp("mod_obscure_ips", "bool", false, "Obscure user IP addresses");

$(document).ready(function() {
	var obscure_ips = settings.getProp("mod_obscure_ips");
	
	function getIPfromlink($a) {
		var m = /^\?\/IP\/(.*)$/.exec($a.attr('href'));
		return m[1];
	}

	var processIPs = function(context) {
		if (obscure_ips) {
			$(".posterip a", context)
				.text("IP")
				.addClass('ip-hidden')
				.on('mouseenter.iphider', function() {
					var $this = $(this);
					$this.text(getIPfromlink($this));
				})
				.on('mouseleave.iphider', function() {
					var $this = $(this);
					$this.text("IP");
				});
		} else {
			$("a.ip-hidden", context).each(function() {
				var $this = $(this);
				$this
					.text(getIPfromlink($this))
					.removeClass('ip-hidden')
					.off('.iphider');
			});
		}
	}

	processIPs(document);

	$(document).on("setting_change", function(e, setting) {
		if (setting === "mod_obscure_ips") {
			obscure_ips = settings.getProp("mod_obscure_ips");
			processIPs(document);
		}
	});

	$(document).on('new_post', function(e, post) {
		processIPs(post);
	});
});
