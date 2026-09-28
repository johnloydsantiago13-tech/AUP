<?php
session_start();
require_once '../backend/config.php';

$name = htmlspecialchars($_SESSION['username'] ?? 'Developer');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer</title>
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
            <a href="Developer.php">Dashboard</a>
            <a href="DeveloperProfile.php">Profile</a>
            <a href="../frontend/index.php">Logout</a>
            </div>
        </div>
    
    <div class="main-topbar">
            <p id="mainC"><b>DEVELOPER DASHBOARD</b></p>
            <h1>Welcome, <?php echo $name; ?>!</h1>
            <p>This is your dashboard.</p>
    </div>
</body>
</html>