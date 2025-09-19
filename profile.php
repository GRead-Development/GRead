<?php
// profile.php
require_once "config.php";

// Redirect user to login page if they are not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Validate that a user ID is present in the URL
if (!isset($_GET["id"]) || empty(trim($_GET["id"])) || !ctype_digit($_GET["id"])) {
    header("location: index.php"); // Redirect if ID is missing or invalid
    exit;
}

$profile_user_id = intval($_GET["id"]);
$profile_username = "";
$post_count = 0;

// ## Query 1: Get user info and post count using your custom 'userid' column ##
$sql_user = "SELECT username, (SELECT COUNT(id) FROM posts WHERE userid = ?) AS post_count FROM users WHERE id = ?";

if ($stmt_user = $mysqli->prepare($sql_user)) {
    $stmt_user->bind_param("ii", $profile_user_id, $profile_user_id);
    if ($stmt_user->execute()) {
        $result_user = $stmt_user->get_result();
        if ($result_user->num_rows == 1) {
            $row_user = $result_user->fetch_assoc();
            $profile_username = $row_user["username"];
            $post_count = $row_user["post_count"];
        } else {
            // No user found with that ID, redirect to the main feed
            header("location: index.php");
            exit;
        }
    }
    $stmt_user->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($profile_username); ?>'s Profile</title>
    <style>
        body { font: 16px sans-serif; text-align: center; background-color: #f4f4f4; }
        .wrapper { width: 800px; margin: 20px auto; }
        .profile-header { padding: 20px; border: 1px solid #ddd; background: #fff; border-radius: 5px; margin-bottom: 20px;}
        .post { border: 1px solid #ccc; margin-top: 15px; padding: 10px; text-align: left; background: #fff; border-radius: 5px; }
        .post p { margin: 0 0 10px 0; word-wrap: break-word; }
        .post-info { font-size: 0.8em; color: #555; border-top: 1px solid #eee; padding-top: 5px; }
        .nav-link { display: inline-block; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <a href="index.php" class="nav-link">&larr; Back to Main Feed</a>
        
        <div class="profile-header">
            <h1><?php echo htmlspecialchars($profile_username); ?></h1>
            <p><strong>Total Posts:</strong> <?php echo $post_count; ?></p>
        </div>

        <div>
            <h2>Posts</h2>
            <?php
            // ## Query 2: Get all posts by this user using your 'userid' and 'created' columns ##
            $sql_posts = "SELECT content, created FROM posts WHERE userid = ? ORDER BY created DESC";
            
            if($stmt_posts = $mysqli->prepare($sql_posts)) {
                $stmt_posts->bind_param("i", $profile_user_id);
                if($stmt_posts->execute()) {
                    $result_posts = $stmt_posts->get_result();
                    if($result_posts->num_rows > 0) {
                        while($row_posts = $result_posts->fetch_assoc()) {
                            echo "<div class='post'>";
                            echo "<p>" . htmlspecialchars($row_posts['content']) . "</p>";
                            echo "<div class='post-info'>Posted on " . $row_posts['created'] . "</div>";
                            echo "</div>";
                        }
                    } else {
                        echo "<p>This user has not made any posts yet.</p>";
                    }
                }
                $stmt_posts->close();
            }
            $mysqli->close();
            ?>
        </div>
    </div>
</body>
</html>
