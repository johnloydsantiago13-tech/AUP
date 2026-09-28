<?php
session_start();
require_once '../backend/config.php';

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Client') {
    header('Location: index.php');
    exit();
}

$query = $conn->prepare("SELECT username, facebook_url, instagram_url FROM users WHERE role = 'Developer' ORDER BY username ASC");
$query->execute();
$result = $query->get_result();
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
            <a href="ClientBrowseDevelopers.php">Browse Developers</a>
            <a href="../frontend/index.php">Logout</a>
            </div>
        </div>

        <div class="main-topbar">
            <div class="ps">
                <h1>Browse Developers</h1>
                <p>Here are the developers registered on AUProject</p>
            </div>

            <div class="dev-list">
                <?php if ($result->num_rows === 0): ?>
                    <p>No developers registered yet.</p>
                <?php else: ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="dev-card">
                        <h3><?php echo htmlspecialchars($row['username']); ?></h3>

                        <div class="dev-links">
                            <?php if (!empty($row['facebook_url'])): ?>
                                <a href="<?php echo htmlspecialchars($row['facebook_url']); ?>" target="_blank">Facebook</a>
                            <?php endif; ?>

                            <?php if (!empty($row['instagram_url'])): ?>
                                <a href="<?php echo htmlspecialchars($row['instagram_url']); ?>" target="_blank">Instagram</a>
                            <?php endif; ?>

                            <?php if (empty($row['facebook_url']) && empty($row['instagram_url'])): ?>
                                <span>No social links yet</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
