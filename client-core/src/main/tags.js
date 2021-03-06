/* @flow
 * tags.js
 *
 * Released under the MIT license
 * Copyright (c) 2013 Macil Tech <maciltech@gmail.com>
 *
 * Puts bbcode at the cursor position on specific key combos.
 *
 */

import $ from 'jquery';

$(document).ready(function() {
  $(document).keydown(function(event) {
    if (event.target.nodeName !== 'TEXTAREA')
      return true;

    if (!event.ctrlKey || event.shiftKey)
      return true;

    let tag;
    switch (event.which) {
    case 66: tag = 'b'; break; // b
    case 72: tag = 'h'; break; // h
    case 73: tag = 'i'; break; // i
    case 75: tag = 'rcv'; break; // k
    case 82: tag = 's'; break; // r
    case 83: tag = '?'; break; // s
    case 85: tag = 'u'; break; // u
    default:
      return true;
    }

    if (event.target.selectionStart == null)
      return true;

    const text = $(event.target).val();
    const start = event.target.selectionStart;
    const end = event.target.selectionEnd;

    if (document.execCommand) {
      try {
        document.execCommand('insertText', false, '['+tag+']' + text.slice(start,end) + '[/'+tag+']');
      } catch (err) {
        // browser doesn't support it, ignore
      }
    }

    if ($(event.target).val() === text) {
      // insertText did nothing. This case happens in browsers other than Chrome.
      const newText = text.slice(0,start) + '['+tag+']' + text.slice(start,end) + '[/'+tag+']' + text.slice(end);
      $(event.target).val(newText);
    }

    const afterInsert = end + ('['+tag+']').length;
    event.target.setSelectionRange(afterInsert, afterInsert);

    return false;
  });
});
