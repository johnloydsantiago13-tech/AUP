<?php
session_start();
require_once '../backend/config.php';

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Client') {
    header('Location: index.php');
    exit();
}

$name = htmlspecialchars($_SESSION['username'] ?? 'Client');
$email = $_SESSION['email'];

$query = $conn->prepare("SELECT title, category, description, budget, deadline, status FROM projects WHERE client_email = ? ORDER BY project_id DESC");
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
    <div class="main-content">
        <aside class="sidebar" aria-label="Client navigation">
            <a class="logo-group" href="Client.php" aria-label="AUProject client dashboard">
                <span class="logo-box" aria-hidden="true">AUP</span>
                <span class="logo-name">AUProject</span>
            </a>
            <nav class="nav-links">
                <a href="Client.php">Dashboard</a>
                <a href="ClientProfile.php">Profile</a>
                <a href="ClientPostProject.php">Post Projects</a>
                <a href="ClientBids.php">My Projects</a>
                <a href="ClientBrowseDevelopers.php">Browse Developers</a>
                <a href="index.php">Logout</a>
            </nav>
        </aside>

        <div class="workspace">
            <nav class="navbar" aria-label="Workspace navigation">
                <span class="navbar-label">Client workspace</span>
            </nav>
            <main class="main-topbar">
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

            <section class="Second-box">
                <div class="Second-top">
                    <div>
                        <p class="eyebrow">YOUR WORK</p>
                        <h2>My Projects</h2>
                    </div>
                    <a class="button button-secondary" href="ClientBids.php">View all Projects &#128065;</a>
                </div>

                <div class="Second-box-bottom">
                    <?php if ($projectCount === 0): ?>
                        <div class="empty-state">
                            <span class="empty-state-icon" aria-hidden="true">+</span>
                            <h3>No projects yet</h3>
                            <p>Post your first project to start working with developers.</p>
                            <a class="button button-primary" href="ClientPostProject.php">Create your first project</a>
                        </div>
                    <?php else: ?>
                        <div class="project-list">
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <article class="my-project-box dashboard-project-card">
                                    <header class="project-Head">
                                        <div class="project-title">
                                            <p class="eyebrow">PROJECT DETAILS</p>
                                            <h3><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <?php if (!empty($row['category'])): ?>
                                                <p class="project-category"><?php echo htmlspecialchars($row['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <?php $projectStatus = $row['status'] ?: 'Open'; ?>
                                        <span class="status-badge status-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $projectStatus)), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($projectStatus, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </header>
                                    <div class="project-details">
                                        <div class="project-description">
                                            <h3>Description</h3>
                                            <p><?php echo nl2br(htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                        </div>
                                        <div class="project-meta">
                                            <div class="meta-item">
                                                <span>Budget</span>
                                                <strong>₱<?php echo number_format((float) $row['budget'], 2); ?></strong>
                                            </div>
                                            <div class="meta-item">
                                                <span>Deadline</span>
                                                <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($row['deadline'])), ENT_QUOTES, 'UTF-8'); ?></strong>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            </main>
        </div>
    </div>
</body>

</html>