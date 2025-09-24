<?php
// index.php
require_once "config.php";

// Redirect user to login page if they are not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// ## Handle Post Deletion ##
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_GET["delete_post_id"])) {
    $postId = trim($_GET["delete_post_id"]);

    // Prepare a delete statement
    $sql = "DELETE FROM posts WHERE id = ? AND userid = ?";

    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param("ii", $postId, $_SESSION["id"]);

        if (!$stmt->execute()) {
            die("ERROR: Could not execute delete query. " . $stmt->error);
        }

        $stmt->close();

        // Redirect on success to prevent re-submission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } else {
        die("ERROR: Could not prepare delete query. " . $mysqli->error);
    }
}

// ## Handle Post Submission ##
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty(trim($_POST["content"]))) {
    
    // Prepare an insert statement
    $sql = "INSERT INTO posts (userid, content) VALUES (?, ?)";
    
    if ($stmt = $mysqli->prepare($sql)) {
        $param_content = trim($_POST["content"]);
        $stmt->bind_param("is", $_SESSION["id"], $param_content);
        
        if (!$stmt->execute()) {
            die("ERROR: Could not execute post query. " . $stmt->error);
        }

        $stmt->close();

        // Redirect on success to prevent re-submission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();

    } else {
        die("ERROR: Could not prepare post query. " . $mysqli->error);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Main Feed</title>
    <style>
        body { font: 16px sans-serif; text-align: center; background-color: #f4f4f4; }
        .wrapper { width: 800px; margin: 20px auto; }
        .navbar { background-color: #333; overflow: hidden; }
        .navbar a { float: left; display: block; color: white; text-align: center; padding: 14px 20px; text-decoration: none; }
        .navbar a:hover { background-color: #575757; }
        .navbar a.active { background-color: #04AA6D; }
        .post-form { margin: 20px auto; padding: 20px; border: 1px solid #ddd; background: #fff; border-radius: 5px; }
        .post { border: 1px solid #ccc; margin-top: 15px; padding: 10px; text-align: left; background: #fff; border-radius: 5px; }
        .post p { margin: 0 0 10px 0; word-wrap: break-word; }
        .post-info { font-size: 0.8em; color: #555; border-top: 1px solid #eee; padding-top: 5px; display: flex; justify-content: space-between; align-items: center; }
        .delete-btn { font-size: 0.8em; color: #a94442; text-decoration: none; }
        .version-info { margin-top: 40px; font-size: 0.9em; color: #777; }
        .version-info h4 { margin-bottom: 5px; }
        .version-info ul { list-style-type: none; padding: 0; }
        .version-info li { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="navbar">
            <a href="index.php" class="active">Feed</a>
            <a href="book_search.php">Book Search</a>
        </div>
        <h1>Hi, <b><?php echo htmlspecialchars($_SESSION["username"]); ?></b>. Welcome.</h1>
        <p><a href="logout.php">Sign Out of Your Account</a></p>

        <div class="post-form">
            <h2>Create a New Post</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div>
                    <textarea name="content" rows="4" cols="70" placeholder="What's on your mind?"></textarea>
                </div>
                <div style="margin-top: 10px;">
                    <input type="submit" value="Post">
                </div>
            </form>
        </div>

        <div>
            <h2>Feed</h2>
            <?php
            // ## Display the Feed ##
            // This SQL query joins the 'posts' and 'users' tables to get the username and post ID for each post.
            $sql = "SELECT users.username, users.id AS userid, posts.id AS postid, posts.content, posts.created
                    FROM posts
                    JOIN users ON posts.userid = users.id
                    ORDER BY posts.created DESC";
            
            if ($result = $mysqli->query($sql)) {
                // Check if the query returned any rows
                if ($result->num_rows > 0) {
                    // Loop through the result set and display each post
                    while ($row = $result->fetch_assoc()) {
                        echo "<div class='post'>";
                        // Use htmlspecialchars to prevent XSS attacks
                        echo "<p>" . htmlspecialchars($row['content']) . "</p>";
                        echo "<div class='post-info'>";
                        echo "<span>Posted by <strong><a href='#'>" . htmlspecialchars($row['username']) . "</a></strong> on " . $row['created'] . "</span>";

                        // Show delete button only if the post belongs to the current user
                        if ($row['userid'] == $_SESSION['id']) {
                            echo '<a href="?delete_post_id=' . $row['postid'] . '" class="delete-btn" onclick="return confirm(\'Are you sure you want to delete this post?\');">Delete</a>';
                        }
                        echo "</div>";
                        echo "</div>";
                    }
                    // Free the result set from memory
                    $result->free();
                } else {
                    echo "<p>No posts yet. Be the first!</p>";
                }
            } else {
                // If the query fails, show the database error
                echo "ERROR: Could not able to execute feed query. " . $mysqli->error;
            }

            // Close the database connection
            $mysqli->close();
            ?>
        </div>
        
        <div class="version-info">
            <h4>Version 1.1</h4>
            <h5>Update Log:</h5>
            <ul>
                <li>**Post Deletion**: Users can now delete their own posts.</li>
                <li>**Confirmation Dialog**: A JavaScript confirmation dialog is now used to prevent accidental deletions.</li>
                <li>**Username Links**: Usernames in the feed are now hyperlinked.</li>
				<li>**Search Function**:  Basic Search function for books by title by clicking the link in the banner.</li>
            </ul>
        </div>

    </div>
</body>
</html>