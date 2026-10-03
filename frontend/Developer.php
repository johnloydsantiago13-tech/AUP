<?php
session_start();
require_once '../backend/config.php';

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Developer') {
    header('Location: index.php');
    exit();
}

$email = $_SESSION['email'];
$userQuery = $conn->prepare('SELECT username, email, role, profile_image, facebook_url, instagram_url FROM users WHERE email = ? LIMIT 1');
$userQuery->bind_param('s', $email);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();

if (!$user) {
    header('Location: index.php');
    exit();
}

$allowedPages = ['dashboard', 'browse', 'bids', 'active', 'profile'];
$page = $_GET['page'] ?? 'dashboard';
if (!is_string($page) || !in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'dashboard';
}
$profileModal = $page === 'profile' && ($_GET['modal'] ?? '') === '1';

$titles = [
    'dashboard' => 'Developer Dashboard',
    'browse' => 'Browse Projects',
    'bids' => 'My Bids',
    'active' => 'My Active Projects',
    'profile' => 'My Profile',
];
$displayName = $user['username'] ?: 'Developer';
$profileImage = !empty($user['profile_image']);
$initial = strtoupper(substr($displayName, 0, 1));

$bidError = $_SESSION['bid_error'] ?? '';
$bidSuccess = $_SESSION['bid_success'] ?? '';
$profileError = $_SESSION['profile_error'] ?? '';
$profileSuccess = $_SESSION['profile_success'] ?? '';
$passwordError = $_SESSION['password_error'] ?? '';
$passwordSuccess = $_SESSION['password_success'] ?? '';
unset(
    $_SESSION['bid_error'],
    $_SESSION['bid_success'],
    $_SESSION['profile_error'],
    $_SESSION['profile_success'],
    $_SESSION['password_error'],
    $_SESSION['password_success']
);

$bidStatsQuery = $conn->prepare(
    "SELECT COUNT(*) AS total_bids,
            COALESCE(SUM(status = 'pending'), 0) AS pending_bids,
            COALESCE(SUM(status = 'accepted'), 0) AS accepted_bids
     FROM bids
     WHERE developer_email = ?"
);
$bidStatsQuery->bind_param('s', $email);
$bidStatsQuery->execute();
$bidStats = $bidStatsQuery->get_result()->fetch_assoc();

$openCountQuery = $conn->prepare(
    "SELECT COUNT(*) AS open_count
     FROM projects
     WHERE LOWER(status) = 'open'
       AND NOT EXISTS (
           SELECT 1 FROM bids
           WHERE bids.project_id = projects.project_id
             AND bids.developer_email = ?
       )"
);
$openCountQuery->bind_param('s', $email);
$openCountQuery->execute();
$openProjectCount = (int) $openCountQuery->get_result()->fetch_assoc()['open_count'];

$projectsQuery = $conn->prepare(
    "SELECT projects.project_id, projects.title, projects.category, projects.description,
            projects.budget, projects.deadline, users.username AS client_name
     FROM projects
     INNER JOIN users ON users.email = projects.client_email
     WHERE LOWER(projects.status) = 'open'
       AND NOT EXISTS (
           SELECT 1 FROM bids
           WHERE bids.project_id = projects.project_id
             AND bids.developer_email = ?
       )
     ORDER BY projects.created_at DESC"
);
$projectsQuery->bind_param('s', $email);
$projectsQuery->execute();
$projectsResult = $projectsQuery->get_result();
$openProjects = $projectsResult->fetch_all(MYSQLI_ASSOC);

$bidsQuery = $conn->prepare(
    'SELECT bids.bid_id, bids.project_id, bids.proposed_price, bids.timeline,
            bids.message, bids.status, bids.created_at,
            projects.title AS project_title, projects.status AS project_status
     FROM bids
     INNER JOIN projects ON projects.project_id = bids.project_id
     WHERE bids.developer_email = ?
     ORDER BY bids.created_at DESC'
);
$bidsQuery->bind_param('s', $email);
$bidsQuery->execute();
$bidsResult = $bidsQuery->get_result();
$myBids = $bidsResult->fetch_all(MYSQLI_ASSOC);

$activeQuery = $conn->prepare(
    "SELECT projects.project_id, projects.title, projects.category, projects.description,
            projects.budget, projects.deadline, users.username AS client_name,
            bids.proposed_price, bids.timeline
     FROM bids
     INNER JOIN projects ON projects.project_id = bids.project_id
     INNER JOIN users ON users.email = projects.client_email
     WHERE bids.developer_email = ? AND bids.status = 'accepted'
     ORDER BY bids.created_at DESC"
);
$activeQuery->bind_param('s', $email);
$activeQuery->execute();
$activeProjects = $activeQuery->get_result()->fetch_all(MYSQLI_ASSOC);

$facebookUrl = $user['facebook_url'] ?? '';
$instagramUrl = $user['instagram_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titles[$page], ENT_QUOTES, 'UTF-8'); ?> | AUProject</title>
    <link rel="stylesheet" href="Dashboard.css">
</head>

<body class="<?php echo $profileModal ? 'has-profile-modal' : ''; ?>">
    <div class="main-content">
        <aside class="sidebar" aria-label="Developer navigation">
            <a class="logo-group" href="Developer.php" aria-label="AUProject developer dashboard">
                <span class="logo-box" aria-hidden="true">AUP</span>
                <span class="logo-name">AUProject</span>
            </a>
            <nav class="nav-links">
                <a href="Developer.php" <?php echo $page === 'dashboard' ? 'aria-current="page"' : ''; ?>>Dashboard</a>
                <a href="Developer.php?page=browse" <?php echo $page === 'browse' ? 'aria-current="page"' : ''; ?>>Browse Projects</a>
                <a href="Developer.php?page=bids" <?php echo $page === 'bids' ? 'aria-current="page"' : ''; ?>>My Bids</a>
                <a href="Developer.php?page=active" <?php echo $page === 'active' ? 'aria-current="page"' : ''; ?>>My Active Projects</a>
                <a href="Developer.php?page=profile" <?php echo $page === 'profile' ? 'aria-current="page"' : ''; ?>>Profile</a>
            </nav>
        </aside>

        <div class="workspace">
            <nav class="navbar" aria-label="Workspace navigation">
                <span class="navbar-label">Developer workspace</span>
                <div class="account-menu" data-account-menu>
                    <button class="account-menu-toggle" type="button" aria-label="Open profile and logout menu" aria-expanded="false" aria-controls="developer-account-dropdown" data-menu-toggle>
                        <?php if ($profileImage): ?>
                            <img class="account-avatar" src="../backend/profile-actions.php?action=profile_image" alt="">
                        <?php else: ?>
                            <span class="account-avatar account-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <span class="account-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="account-menu-icon" aria-hidden="true"><i></i><i></i><i></i></span>
                    </button>
                    <div class="account-dropdown" id="developer-account-dropdown" hidden>
                        <a href="Developer.php?page=profile&amp;modal=1">Profile</a>
                        <a href="index.php">Logout</a>
                    </div>
                </div>
            </nav>

            <main class="main-topbar developer-dashboard">
                <?php if ($bidError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($bidError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <?php if ($bidSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($bidSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

                <?php if ($page === 'dashboard'): ?>
                    <header class="page-heading">
                        <div>
                            <p class="eyebrow">DEVELOPER DASHBOARD</p>
                            <h1>Welcome, <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>!</h1>
                            <p class="page-description">Discover projects and keep track of your proposals.</p>
                        </div>
                    </header>
                    <div class="main-boxes">
                        <a class="first-box developer-stat-link" href="Developer.php?page=browse">
                            <h2>Available projects</h2>
                            <p><?php echo $openProjectCount; ?></p>
                        </a>
                        <a class="first-box1 developer-stat-link" href="Developer.php?page=bids">
                            <h2>My bids</h2>
                            <p><?php echo (int) $bidStats['total_bids']; ?></p>
                        </a>
                    </div>
                    <section class="Second-box developer-section">
                        <div class="Second-top">
                            <div>
                                <p class="eyebrow">START HERE</p>
                                <h2>Browse open projects</h2>
                            </div><a class="button button-secondary" href="Developer.php?page=browse">Browse all</a>
                        </div>
                        <?php if (!$openProjects): ?>
                            <p class="no-bids">No new open projects are available right now.</p>
                        <?php else: ?>
                            <div class="developer-opportunities">
                                <?php foreach (array_slice($openProjects, 0, 3) as $project): ?>
                                    <article class="my-project-box opportunity-card">
                                        <header class="project-Head">
                                            <div class="project-title">
                                                <p class="eyebrow">OPEN PROJECT</p>
                                                <h3><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                                <p class="project-category"><?php echo htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            </div>
                                            <span class="status-badge status-open">Open</span>
                                        </header>
                                        <div class="project-details">
                                            <div class="project-description">
                                                <h3>Description</h3>
                                                <p><?php echo nl2br(htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                            </div>
                                            <div class="project-meta">
                                                <div class="meta-item"><span>Client</span><strong><?php echo htmlspecialchars($project['client_name'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                                <div class="meta-item"><span>Budget</span><strong>₱<?php echo number_format((float) $project['budget'], 2); ?></strong></div>
                                                <div class="meta-item"><span>Deadline</span><strong><?php echo htmlspecialchars(date('F j, Y', strtotime($project['deadline'])), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    <section class="Second-box developer-section">
                        <div class="Second-top">
                            <div>
                                <p class="eyebrow">YOUR WORK</p>
                                <h2>Recent bids</h2>
                            </div><a class="button button-secondary" href="Developer.php?page=bids">View all bids</a>
                        </div>
                        <?php if (!$myBids): ?>
                            <p class="no-bids">You have not sent a proposal yet.</p>
                        <?php else: ?>
                            <div class="developer-opportunities">
                                <?php foreach (array_slice($myBids, 0, 3) as $bid): ?>
                                    <?php $bidStatus = ucfirst(strtolower($bid['status'])); ?>
                                    <article class="my-project-box developer-bid-card">
                                        <header class="project-Head">
                                            <div class="project-title">
                                                <p class="eyebrow">YOUR PROPOSAL</p>
                                                <h3><?php echo htmlspecialchars($bid['project_title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            </div>
                                            <span class="status-badge status-<?php echo htmlspecialchars(strtolower($bidStatus), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($bidStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                        </header>
                                        <div class="project-details">
                                            <div class="project-meta">
                                                <div class="meta-item"><span>Proposed price</span><strong>₱<?php echo number_format((float) $bid['proposed_price'], 2); ?></strong></div>
                                                <div class="meta-item"><span>Timeline</span><strong><?php echo htmlspecialchars($bid['timeline'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php elseif ($page === 'browse'): ?>
                    <header class="page-heading">
                        <div>
                            <p class="eyebrow">FIND WORK</p>
                            <h1>Browse Projects</h1>
                            <p class="page-description">Explore open client projects and send a proposal that fits.</p>
                        </div>
                    </header>
                    <?php if (!$openProjects): ?>
                        <section class="empty-state">
                            <h2>No projects available</h2>
                            <p>New projects will appear here when clients post them.</p>
                        </section>
                    <?php else: ?>
                        <div class="developer-opportunities">
                            <?php foreach ($openProjects as $project): ?>
                                <article class="my-project-box opportunity-card">
                                    <header class="project-Head">
                                        <div class="project-title">
                                            <p class="eyebrow">OPEN PROJECT</p>
                                            <h3><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <p class="project-category"><?php echo htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <span class="status-badge status-open">Open</span>
                                    </header>
                                    <div class="project-details">
                                        <div class="project-description">
                                            <h3>Description</h3>
                                            <p><?php echo nl2br(htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                        </div>
                                        <div class="project-meta">
                                            <div class="meta-item"><span>Client</span><strong><?php echo htmlspecialchars($project['client_name'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <div class="meta-item"><span>Budget</span><strong>₱<?php echo number_format((float) $project['budget'], 2); ?></strong></div>
                                            <div class="meta-item"><span>Deadline</span><strong><?php echo htmlspecialchars(date('F j, Y', strtotime($project['deadline'])), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                        </div>
                                        <details class="proposal-details">
                                            <summary class="button button-primary">Submit a proposal</summary>
                                            <form class="proposal-form" action="../backend/developer-bid-actions.php" method="POST">
                                                <input type="hidden" name="project_id" value="<?php echo (int) $project['project_id']; ?>">
                                                <div class="project-form-row">
                                                    <div class="profile-field"><label for="proposed-price-<?php echo (int) $project['project_id']; ?>">Proposed price (₱)</label><input type="number" id="proposed-price-<?php echo (int) $project['project_id']; ?>" name="proposed_price" min="0.01" step="0.01" required></div>
                                                    <div class="profile-field"><label for="timeline-<?php echo (int) $project['project_id']; ?>">Estimated timeline</label><input type="text" id="timeline-<?php echo (int) $project['project_id']; ?>" name="timeline" maxlength="100" placeholder="e.g. 7 days" required></div>
                                                </div>
                                                <div class="profile-field"><label for="message-<?php echo (int) $project['project_id']; ?>">Message to client</label><textarea id="message-<?php echo (int) $project['project_id']; ?>" name="message" maxlength="2000" required></textarea></div>
                                                <button class="button button-primary" type="submit">Send proposal</button>
                                            </form>
                                        </details>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'bids'): ?>
                    <header class="page-heading">
                        <div>
                            <p class="eyebrow">YOUR PROPOSALS</p>
                            <h1>My Bids</h1>
                            <p class="page-description">Check status and details for proposals you have sent.</p>
                        </div>
                    </header>
                    <?php if (!$myBids): ?>
                        <section class="empty-state">
                            <h2>No proposals yet</h2>
                            <p>Browse open projects to send your first proposal.</p><a class="button button-primary" href="Developer.php?page=browse">Browse projects</a>
                        </section>
                    <?php else: ?>
                        <div class="developer-opportunities">
                            <?php foreach ($myBids as $bid): ?>
                                <?php $bidStatus = ucfirst(strtolower($bid['status'])); ?>
                                <article class="my-project-box developer-bid-card">
                                    <header class="project-Head">
                                        <div class="project-title">
                                            <p class="eyebrow">YOUR PROPOSAL</p>
                                            <h3><?php echo htmlspecialchars($bid['project_title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                        </div>
                                        <span class="status-badge status-<?php echo htmlspecialchars(strtolower($bidStatus), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($bidStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </header>
                                    <div class="project-details">
                                        <div class="project-meta">
                                            <div class="meta-item"><span>Proposed price</span><strong>₱<?php echo number_format((float) $bid['proposed_price'], 2); ?></strong></div>
                                            <div class="meta-item"><span>Timeline</span><strong><?php echo htmlspecialchars($bid['timeline'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <div class="meta-item"><span>Project status</span><strong><?php echo htmlspecialchars($bid['project_status'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                        </div>
                                        <?php if (trim($bid['message']) !== ''): ?><div class="bid-message">
                                                <h5>Your message</h5>
                                                <p><?php echo nl2br(htmlspecialchars($bid['message'], ENT_QUOTES, 'UTF-8')); ?></p>
                                            </div><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'active'): ?>
                    <header class="page-heading">
                        <div>
                            <p class="eyebrow">IN PROGRESS</p>
                            <h1>My Active Projects</h1>
                            <p class="page-description">Projects where your proposal has been accepted.</p>
                        </div>
                    </header>
                    <?php if (!$activeProjects): ?>
                        <section class="empty-state">
                            <h2>No active projects yet</h2>
                            <p>Accepted proposals will show up here.</p>
                        </section>
                    <?php else: ?>
                        <div class="developer-opportunities">
                            <?php foreach ($activeProjects as $project): ?>
                                <article class="my-project-box opportunity-card">
                                    <header class="project-Head">
                                        <div class="project-title">
                                            <p class="eyebrow">ACTIVE PROJECT</p>
                                            <h3><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <p class="project-category"><?php echo htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <span class="status-badge status-in-progress">In Progress</span>
                                    </header>
                                    <div class="project-details">
                                        <div class="project-description">
                                            <h3>Description</h3>
                                            <p><?php echo nl2br(htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                        </div>
                                        <div class="project-meta">
                                            <div class="meta-item"><span>Client</span><strong><?php echo htmlspecialchars($project['client_name'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <div class="meta-item"><span>Agreed price</span><strong>₱<?php echo number_format((float) $project['proposed_price'], 2); ?></strong></div>
                                            <div class="meta-item"><span>Timeline</span><strong><?php echo htmlspecialchars($project['timeline'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <div class="meta-item"><span>Deadline</span><strong><?php echo htmlspecialchars(date('F j, Y', strtotime($project['deadline'])), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <?php if ($profileModal): ?>
                        <div class="profile-modal" role="dialog" aria-modal="true" aria-labelledby="developer-profile-title" tabindex="-1" data-profile-modal>
                        <?php endif; ?>
                        <section class="profile-page">
                            <header class="profile-card-heading">
                                <div class="profile-summary">
                                    <?php if ($profileImage): ?>
                                        <img class="profile-avatar profile-avatar-large" src="../backend/profile-actions.php?action=profile_image" alt="Profile picture">
                                    <?php else: ?>
                                        <span class="profile-avatar profile-avatar-large profile-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <div>
                                        <h1 id="developer-profile-title"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h1>
                                        <p><?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?> · AUProject</p>
                                    </div>
                                </div>
                                <?php if ($profileModal): ?>
                                    <a class="profile-modal-close" href="Developer.php" aria-label="Close profile dialog" data-modal-close>&times;</a>
                                <?php endif; ?>
                            </header>
                            <?php if ($profileError !== ''): ?><p class="error profile-flash" role="alert"><?php echo htmlspecialchars($profileError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                            <?php if ($profileSuccess !== ''): ?><p class="success profile-flash" role="status"><?php echo htmlspecialchars($profileSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                            <?php if ($passwordError !== ''): ?><p class="error profile-flash" role="alert"><?php echo htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                            <?php if ($passwordSuccess !== ''): ?><p class="success profile-flash" role="status"><?php echo htmlspecialchars($passwordSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                            <div class="profile-card-body">
                                <section class="profile-photo-panel" aria-labelledby="photo-title">
                                    <?php if ($profileImage): ?><img class="profile-avatar profile-photo-preview" src="../backend/profile-actions.php?action=profile_image" alt=""><?php else: ?><span class="profile-avatar profile-photo-preview profile-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                                    <div class="profile-photo-copy">
                                        <h3 id="photo-title">Profile picture</h3>
                                        <p>PNG, JPG, or WebP. Maximum 2 MB.</p>
                                        <div class="profile-photo-actions">
                                            <form action="../backend/profile-actions.php" method="POST" enctype="multipart/form-data"><input type="hidden" name="action" value="upload_profile_image"><label class="button button-primary upload-button" for="developer-profile-image">Choose photo</label><input class="visually-hidden" type="file" id="developer-profile-image" name="profile_image" accept="image/png,image/jpeg,image/webp" required><button class="button button-secondary upload-submit" type="submit">Upload</button></form>
                                            <?php if ($profileImage): ?><form action="../backend/profile-actions.php" method="POST"><input type="hidden" name="action" value="remove_profile_image"><button class="text-button text-button-danger" type="submit">Remove</button></form><?php endif; ?>
                                        </div>
                                    </div>
                                </section>
                                <form class="profile-details-form" action="../backend/profile-actions.php" method="POST">
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="profile-field"><label for="developer-display-name">Display name</label><input type="text" id="developer-display-name" name="display_name" maxlength="255" value="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                                    <div class="profile-field"><label for="developer-profile-role">Role</label><input type="text" id="developer-profile-role" value="<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                                    <div class="profile-field"><label for="developer-profile-email">Email</label><input type="email" id="developer-profile-email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                                    <div class="profile-field"><label for="developer-facebook">Facebook link <span>Optional</span></label><input type="url" id="developer-facebook" name="facebook_url" value="<?php echo htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://facebook.com/username"></div>
                                    <div class="profile-field"><label for="developer-instagram">Instagram link <span>Optional</span></label><input type="url" id="developer-instagram" name="instagram_url" value="<?php echo htmlspecialchars($instagramUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://instagram.com/username"></div>
                                    <button class="button button-primary" type="submit">Save changes</button>
                                </form>
                                <section class="password-panel" aria-labelledby="developer-password-title">
                                    <div class="password-panel-heading">
                                        <h3 id="developer-password-title">Change password</h3>
                                    </div>
                                    <form class="password-form" action="../backend/profile-actions.php" method="POST">
                                        <div class="profile-field"><label for="developer-current-password">Current password</label><input type="password" id="developer-current-password" name="current_password" autocomplete="current-password" required></div>
                                        <div class="profile-field"><label for="developer-new-password">New password</label><input type="password" id="developer-new-password" name="new_password" autocomplete="new-password" minlength="8" required></div>
                                        <div class="profile-field"><label for="developer-confirm-password">Confirm new password</label><input type="password" id="developer-confirm-password" name="confirm_password" autocomplete="new-password" minlength="8" required></div>
                                        <button class="button button-primary" type="submit" name="update_password">Update password</button>
                                    </form>
                                </section>
                            </div>
                        </section>
                        <?php if ($profileModal): ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </main>
        </div>
    </div>
    <script src="script.js"></script>
</body>

</html>