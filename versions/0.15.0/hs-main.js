// The main JavaScript file for HotSoup's book tracking

jQuery(document).ready(function($) {

    // Handles adding books to users' libraries
    $(document).on('click', '.hs-add-book', function(e) {
        e.preventDefault();
        const button = $(this);
        const bookID = button.data('book-id');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_add_book',
                nonce: hs_ajax.nonce,
                book_id: bookID,
            },
            beforeSend: function() {
                button.text('Adding...').prop('disabled', true);
            },
            success: function(response) {
                if (response.success) {
                    // Update the button to show it's been added successfully
                    button.text('Added').css('background-color', '#28a745').prop('disabled', true);
                } else {
                    button.text(response.data.message || 'Error').css('background-color', '#dc3545');
                }
            },
            error: function() {
                button.text('Request Failed').prop('disabled', false).css('background-color', '#dc3545');
            }
        });
    }); // <-- End of hs-add-book

    // Handles removing books from a user's library
    $('.hs-my-book-list').on('click', '.hs-remove-book', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure that you want to remove this book from your library?')) {
            return;
        }

        const button = $(this);
        const book_id = button.data('book-id');
        const list_item = button.closest('li[data-list-book-id="' + book_id + '"]');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_remove_book',
                nonce: hs_ajax.nonce,
                book_id: book_id,
            },
            success: function(response) {
                if (response.success) {
                    list_item.fadeOut(400, function() {
                        $(this).remove();
                    });
                } else {
                    alert('ERROR: ' + response.data.message);
                }
            },
            error: function() {
                alert('BIG PROBLEMO!');
            }
        });
    }); // <-- End of hs-remove-book

    // Handles actually updating progress
    $('.hs-progress-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const feedbackSpan = form.find('.hs-feedback');
        const progressbar = form.siblings('.hs-progress-bar-container').find('.hs-progress-bar');
        const progresstext = form.siblings('p');
        const booklist_item = form.closest('.hs-my-book');

        $.ajax({
            url: hs_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_update_progress',
                nonce: hs_ajax.nonce,
                book_id: form.find('input[name="book_id"]').val(),
                current_page: form.find('input[name="current_page"]').val(),
            },
            beforeSend: function() {
                feedbackSpan.text('Saving').css('color', '#666');
            },
            success: function(response) {
                if (response.success) {
                    progressbar.css('width', response.data.progress_percent + '%');
                    progresstext.text(response.data.progress_html);

                    if (response.data.completed) {
                        feedbackSpan.text('Book Completed!').css('color', '#D4AF37');
                        progressbar.addClass('golden');
                        progressbar.addClass('completed');
                        booklist_item.addClass('hs-book-achievement');

                        setTimeout(function() {
                            booklist_item.removeClass('hs-book-achievement');
                        }, 1000);
                    } else {
                        feedbackSpan.text('Saved.').css('color', 'green');
                        progressbar.removeClass('golden');
                        booklist_item.removeClass('completed');
                    }
                } else {
                    feedbackSpan.text(response.data.message).css('color', 'red');
                }
                setTimeout(() => feedbackSpan.text(''), 2000);
            },
            error: function() {
                feedbackSpan.text('Yikes!').css('color', 'red');
            }
        });
    }); // <-- End of hs-progress-form

    // --- Report Inaccuracy Modal ---
    const report_modal = $('#hs-report-modal');
    const close_btn = $('#hs-close-report-modal');
    const submit_btn = $('#hs-submit-report-button');

    // Use event delegation for the open button
    $(document).on('click', '#hs-open-report-modal', function() {
        report_modal.show();
    });

    // Close button
    if (close_btn.length) {
        close_btn.on('click', function() {
            report_modal.hide();
        });
    }

    // Click outside to close
    $(window).on('click', function(e) {
        if ($(e.target).is(report_modal)) {
            report_modal.hide();
        }
    });

    // Submit report
    if (submit_btn.length) {
        submit_btn.on('click', function() {
            const button = $(this);
            const feedback_div = $('#hs-report-feedback');

            $.ajax({
                url: hs_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'hs_submit_report',
                    nonce: hs_ajax.nonce,
                    book_id: button.data('book-id'),
                    report_text: $('#hs-report-textarea').val()
                },
                beforeSend: function() {
                    button.text('Submitting report...').prop('disabled', true);
                    feedback_div.text('');
                },
                success: function(response) {
                    if (response.success) {
                        feedback_div.text(response.data.message).css('color', 'green');
                        setTimeout(() => {
                            report_modal.hide();
                            button.text('Submit Report').prop('disabled', false);
                            $('#hs-report-textarea').val('');
                            feedback_div.text('');
                        }, 2000);
                    } else {
                        feedback_div.text(response.data.message).css('color', 'red');
                        button.text('Submit Report').prop('disabled', false);
                    }
                },
                error: function() {
                    feedback_div.text('Oops! Error!').css('color', 'red');
                    button.text('Submit Report').prop('disabled', false);
                }
            });
        });
    } // <-- End of submit_btn.length

}); // <-- This is the one, final closing bracket for jQuery(document).ready()
