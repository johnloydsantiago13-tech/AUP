<?php
session_start();
require_once '../backend/config.php';

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Client') {
    header('Location: index.php');
    exit();
}

$email = $_SESSION['email'];

$projectError = $_SESSION['project_error'] ?? '';
$projectSuccess = $_SESSION['project_success'] ?? '';
unset($_SESSION['project_error'], $_SESSION['project_success']);

$query = $conn->prepare("SELECT project_id, title, description, budget, deadline FROM projects WHERE client_email = ? ORDER BY project_id DESC");
$query->bind_param('s', $email);
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
            <a href="../frontend/index.php">Logout</a>
            </div>
        </div>

        <div class="my-project">
            <h2>My Project</h2>
            <p>Here u gonna check Project/bids</p>

            <?php if ($projectError) { echo "<p class='error'>" . htmlspecialchars($projectError) . "</p>"; } ?>
            <?php if ($projectSuccess) { echo "<p class='success'>" . htmlspecialchars($projectSuccess) . "</p>"; } ?>
            <?php if ($result->num_rows == 0) { ?>

            <div class="my-project-box">
                <div class="project-Head">
                    <div class="pcard-right"><h2>No projects yet</h2></div>
                </div>

                <div class="project-Details">
                    <div class="pro-Details">
                        <h2>Description</h2>
                        <strong>-</strong>
                    </div>
                    <div class="PBD">
                        <div class="project-budget">
                            <h3>Budget</h3>
                            <strong>-</strong>
                        </div>
                        <div class="project-deadline">
                            <h3>Deadline</h3>
                            <strong>-</strong>
                        </div>
                    </div>
                    <div class="pro-post">
                        <h5>Not posted</h5>
                    </div>
                </div>

                <div class="bid-auction">
                    <h3>Project Bids</h3>
                </div>
            </div>

            <?php } else { ?>
            <?php while ($row = $result->fetch_assoc()) { ?>
            <div class="my-project-box">
                <div class="project-Head">
                    <div class="pcard-right">
                        <h2><?php echo htmlspecialchars($row['title']); ?></h2>
                    </div>
                    <div class="pcard-left">
                        <form action="../backend/client-project-actions.php" method="POST" onsubmit="return confirm('Delete this project?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="project_id" value="<?php echo $row['project_id']; ?>">
                            <button type="submit" name="delete">&#x1F5D1;</button>
                        </form>
                    </div>
                </div>

                <div class="project-Details">
                    <div class="pro-Details">
                        <h2>Description</h2>
                        <p><?php echo htmlspecialchars($row['description']); ?></p>
                    </div>
                    <div class="PBD">
                        <div class="project-budget">
                            <h3>Budget</h3>
                            <strong>$<?php echo $row['budget']; ?></strong>
                        </div>
                        <div class="project-deadline">
                            <h3>Deadline</h3>
                            <strong><?php echo date('m-d-Y', strtotime($row['deadline'])); ?></strong>
                        </div>
                    </div>
                    <div class="pro-post">
                        <h5>Project Posted</h5>
                    </div>
                </div>

                <div class="bid-auction">
                    <h3>Project Bids</h3>
                </div>
            </div>
            <?php } ?>
            <?php } ?>
        </div>
    </div>
</body>
</html>