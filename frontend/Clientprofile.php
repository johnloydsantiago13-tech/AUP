<?php
session_start();
require_once '../backend/config.php';

if (empty($_SESSION['email'])) {
    header('Location: index.php');
    exit();
}

$email = $_SESSION['email'];

$query = $conn->prepare("SELECT facebook_url, instagram_url FROM users WHERE email = ?");
$query->bind_param('s', $email);
$query->execute();

$user = $query->get_result()->fetch_assoc();

$facebookUrl = $user['facebook_url'] ?? '';
$instagramUrl = $user['instagram_url'] ?? '';
$profileError = $_SESSION['profile_error'] ?? '';
$profileSuccess = $_SESSION['profile_success'] ?? '';
unset($_SESSION['profile_error'], $_SESSION['profile_success']);

function showError($message) {
    return !empty($message)
    ? "<p class='error'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>": '';
}

function showSuccess($message) {
    return !empty($message)
    ? "<p class='success'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>": '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client</title>
    <link rel="stylesheet" href="Dashboard.css">
</head>
<body>
    <nav class="navbar">
        <div class="logo-group">
            <div class="logo-box">AUP</div>
            <h2>AUProject</h2>
        </div>
    </nav>
    <div class="main-content">
        <div class="sidebar">
            <div class="nav-links">
            <a href="Client.php">Dashboard</a>
            <a href="ClientProfile.php">Profile</a>
            <a href="ClientPostProject.php">Post Projects</a>
            <a href="ClientBids.php">My Projects</a>
            <a href="../frontend/index.php">Logout</a>
            </div>
        </div>

        <div class="main-topbar">
            <div class="ps">
                <h1>My Profile</h1> 
                <p>You Can Edit Your Profile</p>
            </div>
            <form action="../backend/profile-actions.php" method="POST" class="pf1">
                <div class="pf-left">    
                    <label for="facebook_url"><b>Facebook URL : </b></label>
                    <input type="text" id="facebook_url" name="facebook_url" placeholder="https://facebook.com/username" value="<?= htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class ="pf-right">
                    <label for="instagram_url"><b>Instagram URL : </b></label>
                    <input type="text" id="instagram_url" name="instagram_url" placeholder="https://instagram.com/username" value="<?= htmlspecialchars($instagramUrl, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <button type="submit" name="update_profile" id="button1">Save Profile</button>
                    <?= showError($profileError); ?>
                    <?= showSuccess($profileSuccess); ?>
            </form>
            
        </div>
    </div>
</body>
</html>