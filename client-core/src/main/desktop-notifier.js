const _ = require('lodash');
import $ from 'jquery';
import Kefir from 'kefir';
import settings from './settings';
import {jumpToPost} from './lib/post-utils';
import {get_post_id} from './lib/post-info';
import myPosts from './my-posts';
import {Metadata} from './post-previewer/url-metadata';
import pageHasFocus from './lib/page-has-focus';

settings.newSetting(
  "desktop_notifications",
  "bool",
  false,
  "Enable Reply Desktop Notifications",
  'links', {
    orderhint: 8,
    moredetails: "Shows a desktop notification when you get a reply in an open " +
      "thread while you don't have the thread's window focused.",
    testButton: {label: "Test notification", fn: buttonEvent},
    notSupported: !window.Notification
  }
);

function buttonEvent() {
  Notification.requestPermission(function() {
    if (Notification.permission == "granted") {
      var note = new Notification("Board settings - Ponychan", {
        body: "This is a test",
        tag: "desktop_test",
        icon: SITE_DATA.siteroot + "static/mlpchanlogo.png"
      });
      setTimeout(function() {
        note.close();
      }, 3000);
    }
  });
}

export function canNotify() {
  return window.Notification && Notification.permission == 'granted' &&
    !pageHasFocus() && settings.getSetting("desktop_notifications");
}

function init() {
  if (window.Notification) {
    if (settings.getSetting("desktop_notifications")) {
      Notification.requestPermission();
    }
    $(document).on('setting_change', function(e, setting) {
      if (setting == "desktop_notifications")
        Notification.requestPermission();
    }).on('new_unseen_post', function(e, post) {
      const $post = $(post);
      const postid = get_post_id($post);
      const postLinksToMe = _.any($post.find('> .body a.postlink'), postlink => {
        const m = new Metadata(postlink.getAttribute('href'), global.board_id);
        return myPosts.contains(m.postid);
      });

      if (postLinksToMe && canNotify()) {
        makeNote($post, e.useSkeltal);
      }
    });
  }
}

function makeNote($post, useSkeltal) {
  const iconFn = useSkeltal ? 'mrskeltal.jpg' : 'mlpchanlogo.png';
  const postId = get_post_id($post);
  const note = new Notification(makeHeadLine($post), {
    body: getBody($post),
    tag: "desktop_" + postId,
    icon: SITE_DATA.siteroot + "static/" + iconFn
  });

  const noteClicks = Kefir.fromEvents(note, 'click');

  Kefir.merge([
    Kefir.fromEvents(window, 'focus'),
    noteClicks
  ]).take(1).onValue(() => {
    note.close();
  });

  noteClicks.onValue(() => {
    // sometimes calling window.focus() doesn't trigger the focus event on
    // chrome, hence letting the click event close the note above too.
    window.focus();
    jumpToPost(postId);
  });
}

function makeHeadLine(postEl) {
  var $intro = $('.intro', postEl).first();
  var subject = $intro.find('.subject').first().text();
  if (subject.length)
    subject += ' — ';
  var name = $intro.find('.namepart').first().text();
  return subject + name;
}

function getBody(postEl) {
  // TODO this should be moved to some general function that gets text
  // from an element while attempting to respect newlines.
  var $body = $(".body", postEl).first().clone();
  $body.html($body.html().replace(/<br\b[^>]*>/g, "; "));
  var text = $body.text().replace(/^(; )+/, '');
  if (text.length > 120)
    text = text.substr(0, 120) + '…';
  return text;
}

init();
