// This is the JavaScript stuff for the search bar

jQuery(document).ready(function($) {
    const search_input = $('#hs-book-search-input');
    const results_container = $('#hs-search-results');
    let typing_timer;
    const typing_interval = 300;

    search_input.on('keyup', function() {
        clearTimeout(typing_timer);
        typing_timer = setTimeout(search, typing_interval);
    });

    // If the search filter is changed, search immediately.
    $('input[name="hs-search-by"]').on('change', search);

    function search() {
        const query = search_input.val();
        const search_by = $('input[name="hs-search-by"]:checked').val();

        if (query.length < 3) {
            results_container.empty().hide();
            return;
        }

        $.ajax({
            url: hs_search_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'hs_search',
                nonce: hs_search_obj.nonce,
                query: query,
                search_by: search_by
            },

            beforeSend: function() {
                results_container.html('<div class="hs-search-loading">Searching...</div>').show();
            },

            success: function(response) {
                results_container.empty();

                if (response.success && response.data.results.length > 0) {
                    const userLibrary = response.data.user_library || [];

                    $.each(response.data.results, function(index, book) {
                        const inLibrary = userLibrary.includes(String(book.id));
                        
                        let buttonHtml;
                        if (inLibrary) {
                            buttonHtml = `<button class="hs-button" disabled>Added</button>`;
                        } else {
                            buttonHtml = `<button class="hs-button hs-add-book" data-book-id="${book.id}">Add to Library</button>`;
                        }

                        const result_item = `
                            <div class="hs-search-result-item">
                                <div class="result-details">
                                    <h3 class="result-title"><a href="${book.permalink}">${book.title}</a></h3>
                                    <div class="result-meta">
                                        <span class="result-author"><strong>Author:</strong> ${book.author || 'N/A'}</span>
                                        <span class="result-isbn"><strong>ISBN:</strong> ${book.isbn || 'N/A'}</span>
                                    </div>
                                </div>
                                <div class="result-action">
                                    ${buttonHtml}
                                </div>
                            </div>
                        `;
                        results_container.append(result_item);
                    });
                } else {
                    results_container.html('<div class="hs-no-results">No results found. :(</div>');
                }

                results_container.show();
            },

            error: function() {
                results_container.html('<div class="hs-search-error">Oops! An error occurred!</div>').show();
            }
        });
    }
});


