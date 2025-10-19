jQuery(document).ready(function($) {

    // Target the main BuddyPress activity update textarea
    var $activityTextarea = $('#whats-new-textarea');

    if ($activityTextarea.length) {
        
        // --- Core Autocomplete Initialization ---
        $activityTextarea.autocomplete({
            
            // Define the source function to handle the AJAX search
            source: function(request, response) {
                
                var input = request.term;
                
                // 1. Find the current word/term being typed after a '#'
                // The regex finds the last occurrence of '#' followed by non-space characters
                var lastHashRegex = /#([a-zA-Z0-9\s-]+)$/; 
                var match = input.match(lastHashRegex);
                
                var searchTerm = (match && match[1]) ? match[1] : '';

                if (searchTerm.length < 2) { 
                    // Don't search if the term is too short or no '#' is present
                    return response([]);
                }

                // 2. Make AJAX request to the server
                $.ajax({
                    url: hs_ajax_bp.ajax_url, 
                    dataType: "json",
                    data: {
                        action: 'hs_book_autocomplete', // PHP AJAX hook
                        term: searchTerm               // The extracted search term
                    },
                    success: function(data) {
                        response(data);
                    }
                });
            },
            
            // Positioning the autocomplete menu correctly
            position: { my: "left bottom", at: "left top", collision: "flip" },
            
            // --- Selection Logic ---
            select: function(event, ui) {
                var terms = this.value;
                
                // Find the starting position of the hash tag to replace it
                var lastHashIndex = terms.lastIndexOf('#');
                if (lastHashIndex > -1) {
                    // Get the text *before* the current hash tag
                    var preText = terms.substring(0, lastHashIndex);
                    
                    // Replace the whole '#searchterm' with '#Book Title '
                    this.value = preText + '#' + ui.item.value + ' ';
                } else {
                    // Fallback: just insert the tag if logic fails
                    this.value = terms + ' #' + ui.item.value + ' ';
                }
                
                // Keep the focus on the textarea
                return false;
            }
        });
    }
});