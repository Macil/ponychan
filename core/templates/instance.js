{% raw %}

window.SITE_DATA = {
	selectedstyle: '{% endraw %}{{ config.default_stylesheet.0|e('js') }}{% raw %}',
	styles: {
		{% endraw %}
		{% for stylesheet in stylesheets %}{% raw %}'{% endraw %}{{ stylesheet.name|e('js') }}{% raw %}' : '{% endraw %}{{ stylesheet.uri|e('js') }}{{ stylesheet.version|e('js') }}{% raw %}',
		{% endraw %}{% endfor %}{% raw %}
	},
	cookiename: '{% endraw %}{{ config.cookies.js|e('js') }}{% raw %}',
	cookiepath: '{% endraw %}{% if config.cookies.jail %}{{ config.cookies.path|e('js') }}{% else %}/{% endif %}{% raw %}',
	genpassword_chars: '{% endraw %}{{ config.genpassword_chars|e('js') }}{% raw %}',
	siteroot: '{% endraw %}{{ config.root|e('js') }}{% raw %}'
};

{% endraw %}{% if config.google_analytics %}{% raw %}

window._gaq = window._gaq || [];
_gaq.push(['_setAccount', '{% endraw %}{{ config.google_analytics|e('js') }}{% raw %}']);
{% endraw %}{% if config.google_analytics_domain %}{% raw %}
_gaq.push(['_setDomainName', '{% endraw %}{{ config.google_analytics_domain|e('js') }}{% raw %}']);
{% endraw %}{% endif %}{% if not config.google_analytics_domain %}{% raw %}
_gaq.push(['_setDomainName', 'none']);
{% endraw %}{% endif %}{% raw %}
_gaq.push(['_trackPageview']);
(function() {
	var ga = document.createElement('script');
	ga.type = 'text/javascript';
	ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0];
	s.parentNode.insertBefore(ga, s);
})();

{% endraw %}{% endif %}
