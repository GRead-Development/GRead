<?php
// index.php
require_once "config.php";

// Redirect user to login page if they are not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
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
        .post-form { margin: 20px auto; padding: 20px; border: 1px solid #ddd; background: #fff; border-radius: 5px; }
        .post { border: 1px solid #ccc; margin-top: 15px; padding: 10px; text-align: left; background: #fff; border-radius: 5px; }
        .post p { margin: 0 0 10px 0; word-wrap: break-word; }
        .post-info { font-size: 0.8em; color: #555; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>
    <div class="wrapper">
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
            // This SQL query joins the 'posts' and 'users' tables to get the username for each post.
            $sql = "SELECT users.username, posts.content, posts.created
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
                        echo "<div class='post-info'>Posted by <strong>" . htmlspecialchars($row['username']) . "</strong> on " . $row['created'] . "</div>";
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
    </div>
</body>
</html>
