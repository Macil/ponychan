/* @flow
 * qr.js
 *
 * Released under the MIT license
 * Copyright (c) 2013 Macil Tech <maciltech@gmail.com>
 *
 */

import $ from 'jquery';
import _ from 'lodash';
import Kefir from 'kefir';
import kefirBus from 'kefir-bus';
import config from './config';
import settings from './settings';
import setCss from './set-css';
import {documentReady, newPosts} from './lib/events';
import {get_post_board, get_post_name, get_post_trip, get_post_num} from './lib/post-info';
import {mogrifyHTML} from './mogrify';

settings.newSetting('show_mature', 'bool', false, 'Show mature content threads', 'filters', {
  moredetails:'Only available on certain boards',
  orderhint:1,
  validator(value) {
    if (value) {
      if (!window.localStorage || localStorage.getItem('ofAge') != 'true') {
        if (confirm('You must be at least 18 years of age to continue.\nPush Cancel if you are not.')) {
          try {
            localStorage.setItem('ofAge', 'true');
          } catch (e) {
            //ignore
          }
        } else {
          throw new Error('This setting is restricted.');
        }
      }
    }
  }
});
settings.newSetting(
  'mature_as_spoiler', 'bool', false, 'Treat mature content images as spoilered images', 'filters',
  {
    orderhint:2,
    hider: settings.getSettingStream('show_mature').map(x => !x)
  }
);
settings.newSetting('show_hide_buttons', 'bool', true, 'Show post hiding buttons', 'filters', {orderhint:3});
settings.newSetting('show_hider_stubs', 'bool', true, 'Show stubs for hidden posts', 'filters', {orderhint:4});
settings.newSetting('filtered_names', 'textarea', '', 'Filtered names', 'filters', {orderhint:5});
settings.newSetting('filtered_trips', 'textarea', '', 'Filtered tripcodes', 'filters', {orderhint:6});

const filterStartBus = kefirBus();

export const filterStart = filterStartBus.toProperty();
export const newViewablePosts = kefirBus();

function makeMatcherFromText(text) {
  const filterFns = _.chain(text.split('\n'))
    .map(line => line.trim())
    .filter(Boolean)
    .map(line => {
      // converts a line into a function that checks whether a value matches it.
      const rm = line.match(/^\/(.*)\/([gi]*)$/);
      if (rm) {
        try {
          const regex = new RegExp(rm[1], rm[2]);
          return input => regex.test(input);
        } catch (e) {
          console.error('Failed to parse regex:', line, e); //eslint-disable-line no-console
        }
      }
      return input => input.indexOf(line) !== -1;
    }).value();
  return _.extend(input => _.some(filterFns, fn => fn(input)), {count: filterFns.length});
}

const nameMatcherStream = settings.getSettingStream('filtered_names').map(makeMatcherFromText);
const tripMatcherStream = settings.getSettingStream('filtered_trips').map(makeMatcherFromText);

const postFilterMatcherStream = Kefir.combine([nameMatcherStream, tripMatcherStream])
  .map(([nameMatcher, tripMatcher]) => {
    return $postC => {
      if (nameMatcher.count > 0) {
        if (nameMatcher(get_post_name($postC))) return true;
      }
      if (tripMatcher.count > 0) {
        if (tripMatcher(get_post_trip($postC))) return true;
      }
      return false;
    };
  }).toProperty();

documentReady.onValue(() => {
  settings.getSettingStream('show_hide_buttons').onValue(hideButton => {
    setCss('hide_button', hideButton ? '' : '.postHider { display: none; }');
  });
  settings.getSettingStream('show_hider_stubs').onValue(show_hider_stubs => {
    setCss('hider_stubs', show_hider_stubs ? '' : '.postStub { display: none; }');
  });

  Kefir.combine([
    settings.getSettingStream('show_mature'),
    settings.getSettingStream('mature_as_spoiler')
  ])
    .onValue(([show_mature]) => {
      if (show_mature) {
        prep_mature_images(document.body);
      }
    });

  settings.getSettingStream('show_mature').onValue(show_mature => {
    const expires = new Date();
    if (show_mature) {
      expires.setTime((new Date()).getTime()+60480000000);
      document.cookie = 'show_mature=true; expires='+expires.toUTCString()+'; path='+config.site.siteroot;

      $('.mature_warning').hide();
      $('.mature_thread, .mature_post_button').show();
    } else {
      expires.setTime((new Date()).getTime()-50000);
      document.cookie = 'show_mature=false; expires='+expires.toUTCString()+'; path='+config.site.siteroot;

      $('.mature_warning').show();
      $('.mature_thread, .mature_post_button').hide();
    }
  });

  function prep_mature_images(context) {
    $('img[data-mature-src]', context).each(function() {
      const $img = $(this);

      if (!settings.getSetting('mature_as_spoiler')) {
        if ($img.attr('data-spoiler-src') == undefined)
          $img.attr('data-spoiler-src', $img.attr('src'));
        $img.attr('src', $img.attr('data-mature-src'));

        if ($img.attr('data-mature-style') != undefined) {
          if ($img.attr('data-spoiler-style') == undefined)
            $img.attr('data-spoiler-style', $img.attr('style'));
          $img.attr('style', $img.attr('data-mature-style'));
        }
      } else {
        if ($img.attr('data-spoiler-src') != undefined)
          $img.attr('src', $img.attr('data-spoiler-src'));
        if ($img.attr('data-spoiler-style') != undefined)
          $img.attr('style', $img.attr('data-spoiler-style'));
      }
    });
  }

  let hidden_posts = [];
  function load_hidden_posts() {
    if (typeof localStorage != 'undefined' && localStorage && !localStorage.POLYFILLED) {
      const try_hidden_posts = JSON.parse(localStorage.getItem('hidden_posts') || 'null');
      if (try_hidden_posts)
        hidden_posts = try_hidden_posts;
    }
  }
  load_hidden_posts();

  function save_hidden_posts() {
    if (typeof localStorage != 'undefined' && localStorage && !localStorage.POLYFILLED) {
      try {
        localStorage.setItem('hidden_posts', JSON.stringify(hidden_posts));
      } catch (e) {
        //ignore
      }
    }
  }

  function do_hide_post($postC) {
    let prefix = '';
    if ($postC.hasClass('opContainer')) {
      const $thread = $postC.parent('.thread');
      $thread.find('.replyContainer, .omitted').hide();
      prefix = 'Thread hidden - ';
    }

    $postC.children('.post, .postSide').hide();
    $postC.find('.postStub').remove();
    const $intro = $postC.find('.intro:first');
    const name = $intro.find('.name').text() + $intro.find('.trip').text();

    const $stub = $('<div/>')
      .addClass('postStub')
      .appendTo($postC);
    $('<a/>')
      .attr('href', 'javascript:void(0);')
      .prependTo($stub)
      .text('[ + ] '+prefix+name)
      .click(show_this_post);
    $postC.addClass('hidden-post');
  }

  function hide_post(board, postnum) {
    const postid = board+':'+postnum;
    load_hidden_posts();
    if (hidden_posts.indexOf(postid) == -1) {
      hidden_posts.unshift(postid);
      save_hidden_posts();
    }

    const $postC = $('#replyC_'+postnum);
    do_hide_post($postC);
  }

  function do_show_post($postC) {
    if (!$postC.hasClass('hidden-post')) return;
    if ($postC.hasClass('opContainer')) {
      const $thread = $postC.parent('.thread');
      $thread.find('.replyContainer, .omitted').show();
    }

    $postC.find('.postStub').remove();
    $postC.children('.post, .postSide').show();
    $postC.removeClass('hidden-post');
  }

  function show_post(board, postnum) {
    const postid = board+':'+postnum;
    load_hidden_posts();
    const hidden_index = hidden_posts.indexOf(postid);
    if (hidden_index != -1) {
      hidden_posts.splice(hidden_index, 1);
      save_hidden_posts();
    }

    const $postC = $('#replyC_'+postnum);
    do_show_post($postC);
  }

  function is_post_hidden(board, postnum) {
    const postid = board+':'+postnum;
    return hidden_posts.indexOf(postid) != -1;
  }

  function hide_this_post() {
    const $pc = $(this).closest('.postContainer');
    const postnum = /replyC_(\d+)/.exec($pc.attr('id'))[1];
    hide_post(get_post_board($pc.children('.post')), postnum);
  }

  function show_this_post() {
    const $pc = $(this).closest('.postContainer');
    const postnum = /replyC_(\d+)/.exec($pc.attr('id'))[1];
    show_post(get_post_board($pc.children('.post')), postnum);
  }

  function process_posts(context, postFilter) {
    let threads_needed = 0;
    const $posts = $([...$(context).filter('.post'), ...$('.post', context)]);
    const isThreadPage = $('div.banner').length > 0;
    $posts.each(function() {
      const $post = $(this);

      if ($post.hasClass('mature_post') && settings.getSetting('show_mature')) {
        prep_mature_images($post);
      }

      // Don't hide a thread if we're trying to view
      // it specifically.
      if ($post.hasClass('op') && isThreadPage)
        return;

      // Everything after this relies on the post
      // being a non-previewed post that has an ID
      // itself.
      if (!$post.attr('id'))
        return;

      // Everything following is for regular post
      // hiding functionality, which requires a
      // .postContainer element.
      const $pc = $post.parent();
      if (!$pc.length || !$pc.hasClass('postContainer'))
        return;

      const $thread = $pc.closest('.thread');

      place_button($post);
      const postnum = get_post_num($post);
      const shouldHidePost = is_post_hidden(get_post_board($post), postnum) || postFilter($post);

      if (shouldHidePost) {
        $post.attr('data-filtered', 'true');
        if ($pc.hasClass('opContainer'))
          threads_needed++;
        do_hide_post($pc);
      } else {
        $post.removeAttr('data-filtered');
        do_show_post($pc);
        if ($pc.hasClass('opContainer') && $thread.hasClass('mature_thread') && !settings.getSetting('show_mature'))
          threads_needed++;
        else if ($pc.hasClass('opContainer') && $thread.attr('data-loaded-late'))
          threads_needed--;
      }
    });

    // Load more threads onto the page to make up for
    // hidden threads if this is an index page. Only load
    // more threads on page 1 and if a page 2 exists
    const $selected = $('.pages .selected');
    const $nextpage = $selected.next();
    if (threads_needed > 0 && $selected.text() == '1' && $nextpage.text() == '2' && $nextpage.attr('href')) {
      $.ajax({
        url: $nextpage.attr('href'),
        success(data) {
          data = mogrifyHTML(data);
          const $threads = $('.thread', data);
          $threads.each(function() {
            const $thread = $(this);

            // If this thread already exists on
            // the current page, ignore it.
            if ($('#'+$thread.attr('id')).not('.preview-hidden').length > 0)
              return;

            // It would be silly if we just loaded hidden threads onto this
            // page to make up for hidden threads.
            const postnum = get_post_num($thread.find('.op'));
            if (is_post_hidden(global.board_id, postnum))
              return;

            // If it's a mature thread and we can't view those, skip it.
            if ($thread.hasClass('mature_thread') && !settings.getSetting('show_mature'))
              return;

            $thread.attr('data-loaded-late', true);
            $('.thread').not('.preview-hidden').last().after($thread);
            $thread.find('.post').each(function() {
              $(document).trigger('new_post', this);
            });
            threads_needed--;
            if (threads_needed <= 0)
              return false;
          });
        }
      });
    }
  }

  function place_button($post) {
    let $hider = $post.find('.postHider');
    if (!$hider.length) {
      $hider = $('<span/>')
        .addClass('postHider');
      if ($post.hasClass('op')) {
        const $post_fi = $post.find('.fileinfo');
        const $target = ($post_fi.length) ? $post_fi : $post.find('.intro');
        $target.prepend($hider, ' ');
      } else {
        $post.prepend($hider);
      }
    }
    let $hide_button = $hider.find('a');
    if (!$hide_button.length) {
      $hide_button = $('<a/>')
        .attr('href', 'javascript:void(0);')
        .prependTo($hider);
    }
    $hide_button
      .text('[ - ]')
      .click(hide_this_post);
  }

  postFilterMatcherStream
    .onValue(postFilter => {
      process_posts(document, postFilter);
      filterStartBus.emit(null);
      filterStartBus.end();
    });
  Kefir.combine(
      [newPosts],
      [postFilterMatcherStream]
    )
    .onValue(([post, postFilter]) => {
      process_posts(post, postFilter);
      if (!postFilter($(post))) {
        newViewablePosts.emit(post);
      }
    });
});
