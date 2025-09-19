<?php
// book_search.php
require_once "config.php";

// Redirect user to login page if they are not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Define how many results per page
$resultsPerPage = 20;

// Get current page number from URL, default to 1
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

// Calculate the offset for the API call
$offset = ($currentPage - 1) * $resultsPerPage;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Book Search</title>
    <style>
        body { font-family: sans-serif; text-align: center; background-color: #f4f4f4; }
        .wrapper { width: 800px; margin: 20px auto; }
        .navbar { background-color: #333; overflow: hidden; }
        .navbar a { float: left; display: block; color: white; text-align: center; padding: 14px 20px; text-decoration: none; }
        .navbar a:hover { background-color: #575757; }
        .navbar a.active { background-color: #04AA6D; }
        .content { padding: 20px; }
        .book-result { border: 1px solid #ccc; padding: 10px; margin-bottom: 10px; text-align: left; background: #fff; border-radius: 5px; display: flex; align-items: flex-start; }
        .book-result img { margin-right: 15px; border: 1px solid #eee; }
        .book-info { flex-grow: 1; }
        .book-info h3 { margin-top: 0; margin-bottom: 5px; }
        .pagination { margin-top: 20px; }
        .pagination a { display: inline-block; padding: 8px 16px; text-decoration: none; color: #333; border: 1px solid #ddd; margin: 0 4px; border-radius: 5px; }
        .pagination a.active { background-color: #04AA6D; color: white; border: 1px solid #04AA6D; }
        .pagination a:hover:not(.active) { background-color: #ddd; }
        .pagination span { display: inline-block; padding: 8px 16px; margin: 0 4px; color: #777; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="navbar">
            <a href="index.php">Feed</a>
            <a href="book_search.php" class="active">Book Search</a>
        </div>
        <div class="content">
            <h2>Search for a Book</h2>
            <form action="" method="get">
                <label for="search_query">Book Title:</label>
                <input type="text" id="search_query" name="search_query" value="<?php echo htmlspecialchars($_GET['search_query'] ?? ''); ?>" required>
                <button type="submit">Search</button>
            </form>

            <?php
            if (isset($_GET['search_query']) && !empty($_GET['search_query'])) {
                $searchQuery = urlencode($_GET['search_query']);
                
                // Add limit and offset parameters to the API URL
                $apiUrl = "https://openlibrary.org/search.json?q={$searchQuery}&limit={$resultsPerPage}&offset={$offset}";

                // Initialize cURL session
                $ch = curl_init();
                
                // Set cURL options
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                
                // Execute the cURL request and get the response
                $response = curl_exec($ch);

                // Check for cURL errors
                if (curl_errno($ch)) {
                    echo '<p>Error: ' . curl_error($ch) . '</p>';
                } else {
                    // Decode the JSON response
                    $data = json_decode($response, true);
                    
                    if ($data && isset($data['docs'])) {
                        $books = $data['docs'];
                        $totalResults = $data['numFound'] ?? 0;
                        $totalPages = ceil($totalResults / $resultsPerPage);

                        echo '<h3>Search Results for "' . htmlspecialchars($_GET['search_query']) . '" (' . $totalResults . ' found)</h3>';
                        
                        if (count($books) > 0) {
                            foreach ($books as $book) {
                                $title = htmlspecialchars($book['title'] ?? 'No Title Available');
                                $authors = htmlspecialchars(implode(', ', $book['author_name'] ?? ['No Author Available']));
                                $firstPublishYear = htmlspecialchars($book['first_publish_year'] ?? 'N/A');
                                
                                // Construct cover image URL
                                $coverId = $book['cover_i'] ?? null;
                                $coverUrl = '';
                                if ($coverId) {
                                    // 'M' for medium size, 'S' for small, 'L' for large
                                    $coverUrl = "https://covers.openlibrary.org/b/id/{$coverId}-M.jpg";
                                }

                                echo '<div class="book-result">';
                                if ($coverUrl) {
                                    echo '<img src="' . $coverUrl . '" alt="Book Cover" width="100">';
                                }
                                echo '<div class="book-info">';
                                echo '<h3>' . $title . '</h3>';
                                echo '<p><strong>Author(s):</strong> ' . $authors . '</p>';
                                echo '<p><strong>First Published:</strong> ' . $firstPublishYear . '</p>';
                                echo '</div>';
                                echo '</div>';
                            }

                            // Pagination links
                            echo '<div class="pagination">';
                            $queryString = http_build_query(['search_query' => $_GET['search_query']]);

                            if ($currentPage > 1) {
                                echo '<a href="?' . $queryString . '&page=' . ($currentPage - 1) . '">Previous</a>';
                            } else {
                                echo '<span>Previous</span>';
                            }

                            // Show a few pages around the current page
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);

                            for ($i = $startPage; $i <= $endPage; $i++) {
                                if ($i == $currentPage) {
                                    echo '<a href="?' . $queryString . '&page=' . $i . '" class="active">' . $i . '</a>';
                                } else {
                                    echo '<a href="?' . $queryString . '&page=' . $i . '">' . $i . '</a>';
                                }
                            }

                            if ($currentPage < $totalPages) {
                                echo '<a href="?' . $queryString . '&page=' . ($currentPage + 1) . '">Next</a>';
                            } else {
                                echo '<span>Next</span>';
                            }
                            echo '</div>'; // End pagination
                            
                        } else {
                            echo '<p>No books found. Please try a different search query.</p>';
                        }
                    } else {
                        echo '<p>Failed to retrieve data. Please try again later.</p>';
                    }
                }
                
                // Close the cURL session
                curl_close($ch);
            }
            ?>
        </div>
    </div>
</body>
</html>