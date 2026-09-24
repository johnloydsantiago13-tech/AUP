<?php
session_start();
require_once '../backend/config.php';

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Client') {
    header('Location: index.php');
    exit();
}

$name = htmlspecialchars($_SESSION['username'] ?? 'Client');
$email = $_SESSION['email'];

$query = $conn->prepare("SELECT title, category, budget, deadline FROM projects WHERE client_email = ? ORDER BY project_id DESC");
$query->bind_param('s', $email);
$query->execute();
$result = $query->get_result();

$projectCount = $result->num_rows;
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
            <h1>Welcome, <?php echo $name; ?>!</h1>
            <p>This is your dashboard.</p>
                <div class="main-boxes">
                    <div class="first-box">
                        <div>
                            <h2>Active Projects</h2>
                            <p><?php echo $projectCount; ?></p>
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
                    <div class="Second-top">
                        <div class="Second-left">
                            <h2>My Projects</h2>
                        </div>
                        <div class="Second-right">
                            <a href="ClientBids.php" id="ButtonP">View Project &#128065;</a>
                        </div>
                    </div>

                    <div class="Second-box-bottom">
                        <?php if ($projectCount == 0) { ?>
                        <p id="p1">No projects yet.</p>
                        <a href="ClientPostProject.php" id="ButtonP"><b>Create your First Project</b></a>
                        <?php }
                        else {
                        while ($row = $result->fetch_assoc()) {
                        ?>
                        <div class="dash-project">
                            <h3><?php echo htmlspecialchars($row['title']); ?></h3>
                            <p><?php echo htmlspecialchars($row['category']); ?></p>
                            <p>Budget: $<?php echo $row['budget']; ?></p>
                            <p>Deadline: <?php echo date('F j, Y', strtotime($row['deadline'])); ?></p>
                        </div>
                        <?php }} ?>
                    </div>
                </div>
        </div>
    </div>
</body>
</html>