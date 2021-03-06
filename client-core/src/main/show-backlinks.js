/* @flow */

import $ from 'jquery';
import sortBy from 'lodash/sortBy';
import asap from 'asap';
import {Metadata} from './post-previewer/url-metadata';
import {filterStart, newViewablePosts} from './post-hiding';
import udKefir from 'ud-kefir';
import {get_post_id} from './lib/post-info';
import {newPosts} from './lib/events';
import {markParentLinks} from './post-previewer/link-utils';

const update = udKefir(module, null).changes().take(1).toProperty();

// keys are postids, values are sets of postids that should be shown as
// backlinks on the key postid
const postsToBacklinks = new Map();

let renderIsScheduled = true;
const postsThatNeedBacklinksRendered = new Set();

function start() {
  // Possible room for improvement: incrementally process the page.
  parsePage();
  renderUpdatedBacklinks(true);
}

function parsePage() {
  $('.thread > .postContainer > .post').each(function() {
    parsePost(this);
  });
}

function parsePost(post) {
  const $post = $(post);
  if ($post.attr('data-filtered')) return;
  const postid = get_post_id($post);
  $post.find('> .body a.postlink').each(function() {
    const metadata = new Metadata($(this).attr('href'), global.board_id);
    addBacklinkToPost(postid, metadata.postid);
  });
}

function addBacklinkToPost(backlinkid, postid) {
  let backlinks = postsToBacklinks.get(postid);
  if (!backlinks) {
    backlinks = new Set();
    postsToBacklinks.set(postid, backlinks);
  }
  if (!backlinks.has(backlinkid)) {
    backlinks.add(backlinkid);
    postsThatNeedBacklinksRendered.add(postid);
    if (!renderIsScheduled) {
      renderIsScheduled = true;
      asap(renderUpdatedBacklinks);
    }
  }
}

function renderUpdatedBacklinks(everyPost=false) {
  let selector;
  if (everyPost) {
    selector = '.post';
  } else {
    const selectorParts = [];
    postsThatNeedBacklinksRendered.forEach(postid => {
      const [board, postnum] = postid.split(':');
      selectorParts.push(`.post_${board}-${postnum}`);
    });
    selector = selectorParts.join(',');
  }
  const posts = document.querySelectorAll(selector);
  Array.prototype.forEach.call(posts, renderBacklinksOnPost);
  postsThatNeedBacklinksRendered.clear();
  renderIsScheduled = false;
}

function renderBacklinksOnPost(post) {
  const $post = $(post);
  const postid = get_post_id($post);
  const backlinks = postsToBacklinks.get(postid);
  if (!backlinks || !backlinks.size) return;

  const $intro = $post.find('> .intro, > .opMain > .intro');
  let $mentioned = $intro.find('.mentioned').first();
  let needToInsertMentioned = false;
  if (!$mentioned.length) {
    $mentioned = $('<span/>')
      .addClass('mentioned');
    needToInsertMentioned = true;
  }
  const newLinkElements = [];
  const sortedBacklinks = sortBy(Array.from(backlinks), [
    s => s.split(':')[0],
    s => +s.split(':')[1]
  ]);
  sortedBacklinks.forEach(backlinkid => {
    const [backlinkboard, backlinknum] = backlinkid.split(':');
    const href = `#${backlinknum}`;
    if (
      global.board_id !== backlinkboard ||
      (!needToInsertMentioned && $mentioned.find(`a.backlink[href="${href}"]`).length>0)
    ) {
      return;
    }
    newLinkElements.push(
      $('<a/>')
        .addClass('postlink backlink')
        .attr('href', href)
        .text(`>>${backlinknum}`)
    );
  });
  $mentioned.append(newLinkElements);
  if (needToInsertMentioned) {
    $mentioned.appendTo($intro);
  }
  const parentid = $post.attr('data-parentid');
  if (parentid) {
    markParentLinks($post, parentid);
  }
}

filterStart
  .takeUntilBy(update)
  .onValue(start);

newViewablePosts
  .takeUntilBy(update)
  .onValue(parsePost);

newPosts
  .takeUntilBy(update)
  .onValue(renderBacklinksOnPost);
