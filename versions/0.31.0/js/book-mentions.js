// Book Mentions Integration for BuddyPress
(function($) {
	'use strict';

	// Wait for BP mentions to be ready
	$(document).ready(function() {
		
		// Add book search function to global scope for At.js
		if (typeof window.hsMentions === 'undefined') {
			window.hsMentions = {};
		}
		
		window.hsMentions.searchBooks = function(query, callback) {
			$.ajax({
				url: hsMentions.ajaxurl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'hs_ajax_book_search',
					nonce: hsMentions.nonce,
					q: query
				},
				success: function(response) {
					if (response.success && response.data) {
						callback(response.data);
					} else {
						callback([]);
					}
				},
				error: function() {
					callback([]);
				}
			});
		};
		
		// Initialize book mentions on activity textareas
		function initBookMentions() {
			// Target the activity textarea
			var $textarea = $('#whats-new, .ac-textarea');
			
			if ($textarea.length && typeof $.fn.atwho !== 'undefined') {
				$textarea.atwho({
					at: '#',
					displayTpl: '<li><strong>${title}</strong> <span style="color: #999; font-size: 0.9em;">(${author})</span></li>',
					insertTpl: '#[book-id-${id}:${title}]',
					searchKey: 'title',
					limit: 10,
					startWithSpace: false,
					acceptSpaceBar: true,
					highlightFirst: true,
					callbacks: {
						remoteFilter: function(query, callback) {
							window.hsMentions.searchBooks(query, callback);
						},
						matcher: function(flag, subtext) {
							var match, regexp;
							regexp = new RegExp('(^|\\s)' + flag + '([^\\]\\n]*)', 'gi');
							match = regexp.exec(subtext);
							if (match) {
								return match[2];
							}
							return null;
						},
						beforeInsert: function(value, $li, e) {
							return value;
						}
					}
				});
			}
		}
		
		// Initialize on page load
		initBookMentions();
		
		// Re-initialize when activity form is loaded (for infinite scroll, etc.)
		$(document).on('bp_activity_ajax_request', function() {
			setTimeout(initBookMentions, 500);
		});
	});
	
})(jQuery);
