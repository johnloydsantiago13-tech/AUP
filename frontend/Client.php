<?php
session_start();

$username = htmlspecialchars($_SESSION['username'] ?? 'Client');
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
            <p id="mainC"><b>CLIENT DASHBOARD</b></p>
            <h1>Welcome, <?php echo $username; ?>!</h1>
            <p>This is your dashboard.</p>
                <div class="main-boxes">
                    <div class="first-box">
                        <div>
                            <h2>Active Projects</h2>
                            <p>0</p>
                        </div>
                    </div>
                    <div class="first-box1">
                        <div>
                            <h2>Pending Bids</h2>
                            <p>0</p>
                        </div>
                    </div>
                </div>

                <div class="Second-box">
                    <div class="Second-left">
                        <h2>My Projects</h2>
                        <p id ="p1">No projects yet.</p>
                        <a href="ClientPostProject.php" id ="ButtonP"><b>Create your First Project</a>
                    </div>
                    <div class="Second-right">
                        <a href="ClientBids.php" id ="ButtonP">View Project</b></a>
                    </div>
                </div>
        </div>
    </div>
</body>
</html>