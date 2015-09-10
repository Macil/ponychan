import 'console-polyfill';
import 'babel/polyfill';
import 'webstorage-polyfill';

import './legacy/visibility.min.js';

import './logger.js';
import './legacy/default.js';
import settings from './settings.js';

import './state.js';
import './styles.js';
import './cite-reply';
import './spoiler-toggle.js';
import './spoiler-thread.js';
import './local-time.js';
import './live-post-changes.js';
import './legacy/reloader.js';
import './post-previewer/post-hover.js';
import './post-previewer/post-inline.js';
import './my-posts.js';
import './ajax-edit.js';
import './watcher.js';
import './notifier.js';
import './show-filenames.js';
import './legacy/inline-expanding.js';
import './legacy/image-hover.js';
import './legacy/smartphone-spoiler.js';
import './legacy/show-backlinks.js';
import './navbar.js';
import './permalink.js';
import './post-controls.js';
import './qr.js';
import './tags.js';
import './misc.js';
import './titlebar.js';
import './hide-toggle.js';
import './post-hiding.js';
import './dashboard.js';
import './ips.js';
import './fancy.js';
import './mc.js';
import './embed.js';
import './search.js';
import './desktop-notifier.js';
import './hide-trip.js';

import './settings-screen.jsx';

// for debugging and inline scripts
window.ponychan = {
  _dbg_require: require,
  libs: {
    Kefir: require('kefir'),
    React: require('react/addons'),
    Immutable: require('immutable'),
    RSVP: require('rsvp'),
    $: require('jquery'),
    _: require('lodash'),
    moment: require('moment')
  },
  settings: settings
};

window.$ = window.ponychan.$;
window.settings = window.ponychan.settings;
