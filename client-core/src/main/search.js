/* @flow
 * search.js
 *
 * Released under the MIT license
 * Copyright (c) 2014 Macil Tech <maciltech@gmail.com>
 *
 */

import $ from 'jquery';
import RSVP from 'rsvp';
import _ from 'lodash';
import config from './config';

$(document).ready(function() {
  // don't run inside threads
  if ($('div.banner').length)
    return;

  const $controlsform = $("form[name='postcontrols']");
  let $catalog = $('.catalog');

  // Only run if we're on the catalog or board index
  if (!$catalog.length && !$controlsform.length)
    return;

  const $boardlinks = $('.boardlinks');
  const $textbox = $('<input/>')
    .attr('id', 'threadsearchbox')
    .attr('type', 'text')
    .attr('placeholder', 'Search OPs')
    .attr('maxlength', 75);
  const $status = $('<div/>')
    .addClass('searchstatus');
  $boardlinks.append(' ', $textbox, $status);

  // Stores the string of the currently rendered search.
  // Is falsey if there is no current search.
  let currentSearch = null;

  const queuedSearchDelay = 150;
  let queuedSearchTimer = null;

  // returns an array of search terms
  function searchTermSplitter(text) {
    const terms = [];
    const split = text.split(' ');
    for (let i=0; i<split.length; i++) {
      const s = split[i].toLowerCase().trim();
      if (s.length)
        terms.push(s);
    }
    return terms;
  }

  // Loads the catalog in the background if it's not loaded already.
  // Returns a promise that resolves when the catalog element has been added to the page.
  const initSearch = _.once(function() {
    return new RSVP.Promise(function(resolve, reject) {
      if (!$catalog.length) {
        $.ajax({
          url: config.site.siteroot+global.board_id+'/catalog.html',
          success(data) {
            const $html = $($.parseHTML(data));
            $catalog = $html.filter('.catalog').add( $html.find('.catalog') )
              .first()
              .insertAfter($controlsform)
              .hide();
            resolve();
          },
          error(jqXHR, textStatus, errorThrown) {
            console.error('Failed to load catalog. textStatus:', textStatus, 'errorThrown:', errorThrown); //eslint-disable-line no-console
            reject(new Error(errorThrown));
          }
        });
      } else {
        resolve();
      }
    });
  });

  // queues up a call to search
  function queueSearch() {
    if (queuedSearchTimer) return;
    queuedSearchTimer = setTimeout(search, queuedSearchDelay);
  }

  // does the search and shows threads
  function search() {
    clearTimeout(queuedSearchTimer);
    queuedSearchTimer = null;

    initSearch().then(() => {
      const text = $textbox.val();
      if (text == currentSearch) return;
      let $nofound = $('#searchnofound');
      if (text.length == 0) {
        $('.searchhidden').removeClass('searchhidden');
        $nofound.hide();
        if ($controlsform.length) {
          $catalog.hide();
          $controlsform.show();
          $('.pages').show();
        }
      } else {
        if ($controlsform.length) {
          $controlsform.hide();
          $('.pages').hide();
          $catalog.show();
        }
        const terms = searchTermSplitter(text);
        let countfound = 0;
        $('.catathread').each(function() {
          const $this = $(this);
          const thistext = $this.text().toLowerCase();
          let matchfound = true;
          for (let i=0; i<terms.length; i++) {
            if (terms[i][0] == '-') {
              const t = terms[i].slice(1);
              if (t.length && thistext.indexOf(t) != -1) {
                matchfound = false;
                break;
              }
            } else {
              if (thistext.indexOf(terms[i]) == -1) {
                matchfound = false;
                break;
              }
            }
          }
          if (matchfound) {
            $this.removeClass('searchhidden');
            countfound++;
          } else {
            $this.addClass('searchhidden');
          }
        });
        if (countfound == 0) {
          if (!$nofound.length) {
            $nofound = $('<div/>')
              .attr('id', 'searchnofound')
              .text('Nothing found')
              .appendTo($catalog);
          }
          $nofound.show();
        } else {
          $nofound.hide();
        }
      }
      currentSearch = text;
    }, () => {
      $status.text('Failed to retrieve catalog, try refreshing the page');
    });
  }

  $textbox
    .click(() => {
      initSearch().then($.noop, $.noop);
    })
    .on('input', queueSearch)
    .change(search);
});
