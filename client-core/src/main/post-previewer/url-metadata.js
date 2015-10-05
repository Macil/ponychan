/**
 * link-metadata
 *
 * Converts post link urls into objects.
 * /b/res/12.html#34 becomes {board: b, thread: 12, post: 34}
 * #37 becomes {post: 37}
 * /b/res/45.html#45 becomes {board: b, thread: 45, post: 45}
 * /b/res/50.html becomes {board: b, thread: 50}
 *
 */

export class Metadata {
	constructor (url) {
		if ((/^#(\d+)$/).test(url)) {
			this.post = parseInt(url.replace('#', ''));
		} else {
			let parts = (/^\??\/(\w+)\/(?:[^\/?&#]+)\/(?:(\d+)(?:\+50)?.html(?:#(\d+))?)?/).exec(url);
			this.board = parts[1];
			this.thread = parseInt(parts[2]);
			if (parts[3])
				this.post = parseInt(parts[3]);
		}
	}
	toQuerySelector() {
		var start = '.post';
		// What if two posts from two different boards share the same number?
		if (this.board && this.thread && !(this.post)) {
			// For thread selectors
			throw new Error('This method does not support thread selectors.');
		} else if (this.board) {
			// Board and post specific. URL contained a board name here.
			return start+'_'+this.board+'-'+this.post;
		} else {
			// Only post specific. URL was just a hash.
			return start+'_'+this.post;
		}
	}
}
