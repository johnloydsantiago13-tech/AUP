<?php
session_start();

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Client') {
    header('Location: index.php');
    exit();
}

$projectError = $_SESSION['project_error'] ?? '';
$projectSuccess = $_SESSION['project_success'] ?? '';
unset($_SESSION['project_error'], $_SESSION['project_success']);
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
        <div class="Project-box">
            <div class="prop">
                <h1>Post Project</h1>
                <p>Here You Can Post Project U Wanted </p>
                <?php if ($projectError): ?><p class="error"><?= htmlspecialchars($projectError, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
                <?php if ($projectSuccess): ?><p class="success"><?= htmlspecialchars($projectSuccess, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            </div>
            <form action="../backend/project-actions.php" method="POST" class ="probox">
                <label for="title">Post Project</label>
                <input type="text" name="title" placeholder="Project title" required>
                <label for="category">Category</label>
                <input type="text" name="category" placeholder="e.g. Web development" required>
                <label for="description">Description</label>
                <textarea name="description" placeholder="Project description" required></textarea>
                <label for="budget">Budget</label>
                <input type="number" name="budget" placeholder="Budget" min="0" step="0.01" required>
                <label for="deadline">Deadline</label>
                <input type="date" name="deadline" required>
                <br>
                <button type="submit" name="create_project" id="button2">Post Project</button>
            </form>
        </div>
    </div>
</body>
</html>