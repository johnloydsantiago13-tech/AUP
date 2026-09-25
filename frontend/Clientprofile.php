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

$passwordError = $_SESSION['password_error'] ?? '';
$passwordSuccess = $_SESSION['password_success'] ?? '';
unset($_SESSION['password_error'], $_SESSION['password_success']);

function showError($message) {
    return !empty($message) ? "<p class='error'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>": '';
}

function showSuccess($message) {
    return !empty($message) ? "<p class='success'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p>": '';
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

    <div class="pf1">
        <form action="../backend/profile-actions.php" method="POST">
            <div class="pf-left">
                <div class="field-view" id="fb-view">
                    <label><b>Facebook Name :</b> <button type="button" onclick="editField('fb')">&#9998;</button></label>
                    <p><?php echo $facebookUrl !== '' ? htmlspecialchars($facebookUrl) : ''; ?></p>
                    </div>
                <div class="field-edit" id="fb-edit" style="display: none;">
                    <label for="facebook_url"><b>Facebook Name :</b></label>
                    <input type="text" id="facebook_url" name="facebook_url" placeholder="https://facebook.com/username" value="<?php echo htmlspecialchars($facebookUrl); ?>">
                </div>
            </div>

            <div class="pf-right">
                <div class="field-view" id="ig-view">
                    <label><b>Instagram Name :</b> <button type="button" onclick="editField('ig')">&#9998;</button></label>
                    <p><?php echo $instagramUrl !== '' ? htmlspecialchars($instagramUrl) : ''; ?></p>
             </div>
                <div class="field-edit" id="ig-edit" style="display: none;">
                    <label for="instagram_url"><b>Instagram Name :</b></label>
                    <input type="text" id="instagram_url" name="instagram_url" placeholder="https://instagram.com/username" value="<?php echo htmlspecialchars($instagramUrl); ?>">
                </div>
            </div>

            <button type="submit" name="update_profile" id="button1">Save Profile</button>
            <?php echo showError($profileError); ?>
            <?php echo showSuccess($profileSuccess); ?>
        </form>
    <div class="pf2">
        <h2>Change Password</h2>
        <form action="../backend/profile-actions.php" method="POST">
            <div class="pf-left">
                <label for="current_password"><b>Current Password :</b></label>
                <input type="password" id="current_password" name="current_password" placeholder="Current password" required>
            </div>
            <div class="pf-right">
                <label for="new_password"><b>New Password :</b></label>
                <input type="password" id="new_password" name="new_password" placeholder="New password" required>
            </div>
            <div class="pf-left">
                <label for="confirm_password"><b>Confirm Password :</b></label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
            </div>

            <button type="submit" name="update_password" id="button1">Update Password</button>
            <?php echo showError($passwordError); ?>
            <?php echo showSuccess($passwordSuccess); ?>
        </form>
        </div>
    </div>
</div>

    <script src="script.js"></script>

</body>
</html>