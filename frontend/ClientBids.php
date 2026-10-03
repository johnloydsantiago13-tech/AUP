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

$projectQuery = $conn->prepare(
    'SELECT project_id, title, category, description, budget, deadline, status
     FROM projects
     WHERE client_email = ?
     ORDER BY project_id DESC'
);
$projectQuery->bind_param('s', $email);
$projectQuery->execute();
$projectResult = $projectQuery->get_result();

$projects = [];
while ($project = $projectResult->fetch_assoc()) {
    $projects[] = $project;
}

$bidQuery = $conn->prepare(
    "SELECT bids.bid_id, bids.project_id, bids.proposed_price, bids.timeline,
            bids.message, bids.status, users.username, bids.developer_email
     FROM bids
     INNER JOIN projects ON projects.project_id = bids.project_id
     LEFT JOIN users ON users.email = bids.developer_email
     WHERE projects.client_email = ?
     ORDER BY bids.created_at DESC"
);
$bidQuery->bind_param('s', $email);
$bidQuery->execute();
$bidResult = $bidQuery->get_result();

$bidsByProject = [];
while ($bid = $bidResult->fetch_assoc()) {
    $bidsByProject[(int) $bid['project_id']][] = $bid;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Projects | AUProject</title>
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
                <a href="ClientBids.php" aria-current="page">My Projects</a>
                <a href="ClientBrowseDevelopers.php">Browse Developers</a>
                <a href="index.php">Logout</a>
            </nav>
        </aside>

        <div class="workspace">
            <nav class="navbar" aria-label="Workspace navigation">
                <span class="navbar-label">Client workspace</span>
            </nav>
            <main class="my-project">
            <header class="page-heading">
                <div>
                    <p class="eyebrow">CLIENT WORKSPACE</p>
                    <h1>My Projects</h1>
                    <p class="page-description">Review your project details and proposals from developers.</p>
                </div>
                <a class="button button-primary" href="ClientPostProject.php">Post a project</a>
            </header>

            <?php if ($projectError !== ''): ?>
                <p class="error" role="alert"><?php echo htmlspecialchars($projectError, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>
            <?php if ($projectSuccess !== ''): ?>
                <p class="success" role="status"><?php echo htmlspecialchars($projectSuccess, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php endif; ?>

            <?php if (count($projects) === 0): ?>
                <section class="empty-state">
                    <span class="empty-state-icon" aria-hidden="true">+</span>
                    <h2>No projects yet</h2>
                    <p>Post your first project to start receiving proposals from developers.</p>
                    <a class="button button-primary" href="ClientPostProject.php">Create your first project</a>
                </section>
            <?php else: ?>
                <div class="project-list">
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $projectId = (int) $project['project_id'];
                        $projectBids = $bidsByProject[$projectId] ?? [];
                        $projectStatus = $project['status'] ?: 'Open';
                        ?>
                        <article class="my-project-box">
                            <header class="project-Head">
                                <div class="project-title">
                                    <p class="eyebrow">PROJECT DETAILS</p>
                                    <h2><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                    <?php if (!empty($project['category'])): ?>
                                        <p class="project-category"><?php echo htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="project-head-actions">
                                    <span class="status-badge status-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $projectStatus)), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($projectStatus, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <form action="../backend/client-project-actions.php" method="POST" onsubmit="return confirm('Delete this project?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                        <button class="icon-button" type="submit" name="delete" aria-label="Delete project">
                                            <span aria-hidden="true">&#128465;</span>
                                        </button>
                                    </form>
                                </div>
                            </header>

                            <div class="project-details">
                                <div class="project-description">
                                    <h3>Description</h3>
                                    <p><?php echo nl2br(htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                </div>
                                <div class="project-meta">
                                    <div class="meta-item">
                                        <span>Budget</span>
                                        <strong>₱<?php echo number_format((float) $project['budget'], 2); ?></strong>
                                    </div>
                                    <div class="meta-item">
                                        <span>Deadline</span>
                                        <strong><?php echo htmlspecialchars(date('F j, Y', strtotime($project['deadline'])), ENT_QUOTES, 'UTF-8'); ?></strong>
                                    </div>
                                </div>
                            </div>

                            <section class="bid-section" aria-label="Project bids">
                                <div class="bid-section-heading">
                                    <h3>Project bids</h3>
                                    <span class="bid-count"><?php echo count($projectBids); ?> <?php echo count($projectBids) === 1 ? 'bid' : 'bids'; ?></span>
                                </div>

                                <?php if (count($projectBids) === 0): ?>
                                    <p class="no-bids">No bids yet. Developer proposals will appear here.</p>
                                <?php else: ?>
                                    <div class="bid-list">
                                        <?php foreach ($projectBids as $bid): ?>
                                            <?php
                                            $bidStatus = ucfirst(strtolower($bid['status']));
                                            $developerName = $bid['username'] ?: $bid['developer_email'];
                                            ?>
                                            <article class="bid-card">
                                                <div class="bid-card-header">
                                                    <div class="developer-identity">
                                                        <span class="developer-avatar" aria-hidden="true">&#128100;</span>
                                                        <div>
                                                            <h4><?php echo htmlspecialchars($developerName, ENT_QUOTES, 'UTF-8'); ?></h4>
                                                            <p>Developer</p>
                                                        </div>
                                                    </div>
                                                    <span class="status-badge status-<?php echo htmlspecialchars(strtolower($bidStatus), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <?php echo htmlspecialchars($bidStatus, ENT_QUOTES, 'UTF-8'); ?>
                                                    </span>
                                                </div>

                                                <div class="bid-meta">
                                                    <div class="meta-item">
                                                        <span>Proposed price</span>
                                                        <strong>₱<?php echo number_format((float) $bid['proposed_price'], 2); ?></strong>
                                                    </div>
                                                    <div class="meta-item">
                                                        <span>Estimated timeline</span>
                                                        <strong><?php echo htmlspecialchars($bid['timeline'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                    </div>
                                                </div>

                                                <?php if (trim($bid['message']) !== ''): ?>
                                                    <div class="bid-message">
                                                        <h5>Message to client</h5>
                                                        <p><?php echo nl2br(htmlspecialchars($bid['message'], ENT_QUOTES, 'UTF-8')); ?></p>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (strtolower($bid['status']) === 'pending' && strtolower($projectStatus) === 'open'): ?>
                                                    <div class="bid-actions">
                                                        <form action="../backend/bid-actions.php" method="POST" onsubmit="return confirm('Accept this bid? Other pending bids for this project will be rejected.');">
                                                            <input type="hidden" name="action" value="accept">
                                                            <input type="hidden" name="bid_id" value="<?php echo (int) $bid['bid_id']; ?>">
                                                            <button class="button button-accept" type="submit">Accept bid</button>
                                                        </form>
                                                        <form action="../backend/bid-actions.php" method="POST" onsubmit="return confirm('Reject this bid?');">
                                                            <input type="hidden" name="action" value="reject">
                                                            <input type="hidden" name="bid_id" value="<?php echo (int) $bid['bid_id']; ?>">
                                                            <button class="button button-secondary" type="submit">Reject</button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
