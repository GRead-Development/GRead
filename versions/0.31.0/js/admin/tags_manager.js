/**
 * Book Tags Manager - Admin Interface JavaScript
 */

jQuery(function($) {
	let searchTimeout;
	let selectedBookId = null;
	let selectedBookTitle = null;

	/**
	 * Book search with autocomplete
	 */
	$('#hs-book-search').on('input', function() {
		clearTimeout(searchTimeout);
		const query = $(this).val();

		if (query.length < 2) {
			$('#hs-search-results').html('').hide();
			return;
		}

		searchTimeout = setTimeout(() => {
			$.ajax({
				url: hsBTA.ajaxurl,
				type: 'POST',
				data: {
					action: 'hs_search_books',
					nonce: hsBTA.nonce,
					q: query
				},
				success: function(response) {
					if (response.success && response.data.length > 0) {
						displaySearchResults(response.data);
					} else {
						$('#hs-search-results').html('<p class="hs-no-results">No books found</p>');
					}
				},
				error: function() {
					$('#hs-search-results').html('<p class="hs-error">Error searching books</p>');
				}
			});
		}, 300);
	});

	/**
	 * Display search results
	 */
	function displaySearchResults(results) {
		let html = '<div class="hs-search-dropdown">';

		results.forEach(book => {
			html += `
				<div class="hs-search-result-item" data-book-id="${book.id}" data-book-title="${escapeHtml(book.title)}">
					<div class="hs-result-title">${escapeHtml(book.title)}</div>
					<div class="hs-result-author">${escapeHtml(book.author)}</div>
				</div>
			`;
		});

		html += '</div>';
		$('#hs-search-results').html(html).show();

		$('.hs-search-result-item').on('click', function() {
			selectBook($(this).data('book-id'), $(this).data('book-title'));
		});
	}

	/**
	 * Select a book from search results
	 */
	function selectBook(bookId, bookTitle) {
		selectedBookId = bookId;
		selectedBookTitle = bookTitle;

		$('#hs-book-id').val(bookId);
		$('#hs-book-search').val(bookTitle);
		$('#hs-search-results').html('').hide();
		$('#hs-form-message').html('').removeClass('success error');

		// Load existing tags for this book
		loadBookTags(bookId);
	}

	/**
	 * Load existing tags for the selected book
	 */
	function loadBookTags(bookId) {
		$.ajax({
			url: hsBTA.ajaxurl,
			type: 'POST',
			data: {
				action: 'hs_get_book_tags',
				nonce: hsBTA.nonce,
				book_id: bookId
			},
			success: function(response) {
				if (response.success) {
					displayBookTags(response.data);
				} else {
					$('#hs-current-tags').html('<p class="hs-error">' + response.data.message + '</p>');
				}
			},
			error: function() {
				$('#hs-current-tags').html('<p class="hs-error">Error loading tags</p>');
			}
		});
	}

	/**
	 * Display current tags for the selected book
	 */
	function displayBookTags(data) {
		const tags = data.tags;
		let html = '';

		if (tags.length === 0) {
			html = '<p class="hs-no-tags">No tags assigned to this book yet.</p>';
			$('#hs-current-tags').html(html);
			$('#hs-book-tags').val('');
			return;
		}

		html = '<div class="hs-tags-list">';

		tags.forEach(tag => {
			html += `
				<div class="hs-tag-item">
					<span class="hs-tag-name">${escapeHtml(tag.tag_name)}</span>
					<span class="hs-tag-usage">(used ${tag.usage_count} time${tag.usage_count !== 1 ? 's' : ''})</span>
					<button type="button" class="hs-tag-delete-btn" data-tag-id="${tag.id}" title="Delete this tag">
						<span class="hs-tag-delete-icon">×</span>
					</button>
				</div>
			`;
		});

		html += '</div>';
		$('#hs-current-tags').html(html);

		// Populate the textarea with current tags
		const tagNames = tags.map(t => t.tag_name).join(', ');
		$('#hs-book-tags').val(tagNames);

		// Bind delete button clicks
		$('.hs-tag-delete-btn').on('click', function(e) {
			e.preventDefault();
			const tagId = $(this).data('tag-id');
			deleteTag(tagId);
		});
	}

	/**
	 * Delete a specific tag
	 */
	function deleteTag(tagId) {
		if (!confirm('Are you sure you want to delete this tag?')) {
			return;
		}

		$.ajax({
			url: hsBTA.ajaxurl,
			type: 'POST',
			data: {
				action: 'hs_delete_book_tag',
				nonce: hsBTA.nonce,
				tag_id: tagId
			},
			success: function(response) {
				if (response.success) {
					showMessage('Tag deleted successfully', 'success');
					// Reload tags for the current book
					if (selectedBookId) {
						loadBookTags(selectedBookId);
					}
				} else {
					showMessage(response.data.message || 'Error deleting tag', 'error');
				}
			},
			error: function() {
				showMessage('Error deleting tag', 'error');
			}
		});
	}

	/**
	 * Handle form submission
	 */
	$('#hs-book-tags-form').on('submit', function(e) {
		e.preventDefault();

		if (!selectedBookId) {
			showMessage('Please select a book first', 'error');
			return;
		}

		const tags = $('#hs-book-tags').val();

		$.ajax({
			url: hsBTA.ajaxurl,
			type: 'POST',
			data: {
				action: 'hs_save_book_tags',
				nonce: hsBTA.nonce,
				book_id: selectedBookId,
				tags: tags
			},
			success: function(response) {
				if (response.success) {
					showMessage(response.data.message || 'Tags saved successfully', 'success');
					// Reload tags to refresh the display
					loadBookTags(selectedBookId);
				} else {
					const errorMsg = response.data.message || 'Error saving tags';
					const errors = response.data.errors || [];
					const fullMessage = errors.length > 0
						? errorMsg + '\n' + errors.join('\n')
						: errorMsg;
					showMessage(fullMessage, 'error');
				}
			},
			error: function() {
				showMessage('Error saving tags', 'error');
			}
		});
	});

	/**
	 * Load and display tag statistics
	 */
	function loadTagStatistics() {
		$.ajax({
			url: hsBTA.ajaxurl,
			type: 'POST',
			data: {
				action: 'hs_get_tag_statistics',
				nonce: hsBTA.nonce
			},
			success: function(response) {
				if (response.success) {
					displayTagStatistics(response.data);
				}
			}
		});
	}

	/**
	 * Display tag statistics
	 */
	function displayTagStatistics(stats) {
		let html = '<div class="hs-stats-grid">';

		html += `
			<div class="hs-stat-card">
				<div class="hs-stat-number">${stats.total_tags}</div>
				<div class="hs-stat-label">Total Unique Tags</div>
			</div>
			<div class="hs-stat-card">
				<div class="hs-stat-number">${stats.total_tagged_books}</div>
				<div class="hs-stat-label">Books with Tags</div>
			</div>
		`;

		html += '</div>';

		if (stats.most_used_tags && stats.most_used_tags.length > 0) {
			html += '<div class="hs-most-used">';
			html += '<h3>Top Tags</h3>';
			html += '<ul>';

			stats.most_used_tags.forEach(tag => {
				html += `
					<li>
						<strong>${escapeHtml(tag.tag_name)}</strong>
						<span class="hs-usage-count">${tag.usage_count} book${tag.usage_count !== 1 ? 's' : ''}</span>
					</li>
				`;
			});

			html += '</ul>';
			html += '</div>';
		}

		$('#hs-tag-stats').html(html);
	}

	/**
	 * Show message to user
	 */
	function showMessage(message, type) {
		const $messageEl = $('#hs-form-message');
		$messageEl.html(message).removeClass('success error').addClass(type);

		// Auto-hide success messages
		if (type === 'success') {
			setTimeout(() => {
				$messageEl.fadeOut(300, function() {
					$messageEl.html('').show().removeClass('success');
				});
			}, 3000);
		}
	}

	/**
	 * Escape HTML special characters
	 */
	function escapeHtml(text) {
		const map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};
		return text.replace(/[&<>"']/g, m => map[m]);
	}

	// Load statistics on page load
	loadTagStatistics();

	// Refresh statistics every 30 seconds
	setInterval(loadTagStatistics, 30000);
});
