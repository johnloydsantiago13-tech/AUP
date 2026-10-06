<?php
session_start();
require_once '../backend/config.php';
$projectOptions = require '../backend/project-options.php';
$projectCategories = $projectOptions['categories'];
$projectYearLevels = $projectOptions['year_levels'];

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Developer') {
    header('Location: index.php');
    exit();
}

$email = $_SESSION['email'];
$userQuery = $conn->prepare('SELECT id, username, email, role, profile_image, facebook_url, instagram_url, about_me, skills, course, year_level, portfolio_url FROM users WHERE email = ? LIMIT 1');
$userQuery->bind_param('s', $email);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();

if (!$user) {
    header('Location: index.php');
    exit();
}

$requestedPage = $_GET['page'] ?? 'dashboard';
$allowedPages = ['dashboard', 'browse', 'bids', 'active'];
$page = $requestedPage === 'profile' ? 'dashboard' : $requestedPage;
if (!is_string($page) || !in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'dashboard';
}
$titles = [
    'dashboard' => 'Developer Dashboard',
    'browse' => 'Browse Projects',
    'bids' => 'My Bids',
    'active' => 'My Active Projects',
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
            COALESCE(SUM(status = 'accepted'), 0) AS accepted_bids,
            (SELECT COUNT(DISTINCT projects.project_id)
             FROM bids completed_bids
             INNER JOIN projects ON projects.project_id = completed_bids.project_id
             WHERE completed_bids.developer_email = ?
               AND completed_bids.status = 'accepted'
               AND projects.status = 'Completed') AS completed_projects
     FROM bids
     WHERE developer_email = ?"
);
$bidStatsQuery->bind_param('ss', $email, $email);
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
            projects.budget, projects.deadline,
            users.username AS client_name
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

$selectedCategory = $_GET['category'] ?? '';
if (!is_string($selectedCategory) || !in_array($selectedCategory, $projectCategories, true)) {
    $selectedCategory = '';
}
if ($page === 'browse' && $selectedCategory !== '') {
    $openProjects = array_values(array_filter(
        $openProjects,
        static fn(array $project): bool => $project['category'] === $selectedCategory
    ));
}

$bidsQuery = $conn->prepare(
    "SELECT bids.bid_id, bids.project_id, bids.proposed_price, bids.timeline,
            bids.message, bids.status, bids.created_at,
            projects.title AS project_title, projects.status AS project_status,
            client.username AS client_name,
            CASE WHEN bids.status = 'accepted' THEN client.facebook_url ELSE NULL END AS client_facebook_url,
            CASE WHEN bids.status = 'accepted' THEN client.instagram_url ELSE NULL END AS client_instagram_url
     FROM bids
     INNER JOIN projects ON projects.project_id = bids.project_id
     LEFT JOIN users AS client ON client.email = projects.client_email
     WHERE bids.developer_email = ?
     ORDER BY bids.created_at DESC"
);
$bidsQuery->bind_param('s', $email);
$bidsQuery->execute();
$bidsResult = $bidsQuery->get_result();
$myBids = $bidsResult->fetch_all(MYSQLI_ASSOC);

$activeQuery = $conn->prepare(
    "SELECT projects.project_id, projects.title, projects.category, projects.description,
            projects.budget, projects.deadline, users.username AS client_name,
            users.facebook_url AS client_facebook_url, users.instagram_url AS client_instagram_url,
            bids.proposed_price, bids.timeline
     FROM bids
     INNER JOIN projects ON projects.project_id = bids.project_id
     INNER JOIN users ON users.email = projects.client_email
     WHERE bids.developer_email = ? AND bids.status = 'accepted'
       AND projects.status = 'In Progress'
     ORDER BY bids.created_at DESC"
);
$activeQuery->bind_param('s', $email);
$activeQuery->execute();
$activeProjects = $activeQuery->get_result()->fetch_all(MYSQLI_ASSOC);

$completedProjectsQuery = $conn->prepare(
    "SELECT DISTINCT projects.title, projects.category, projects.description
     FROM bids
     INNER JOIN projects ON projects.project_id = bids.project_id
     WHERE bids.developer_email = ? AND bids.status = 'accepted'
       AND projects.status = 'Completed'
     ORDER BY projects.project_id DESC"
);
$completedProjectsQuery->bind_param('s', $email);
$completedProjectsQuery->execute();
$completedProjects = $completedProjectsQuery->get_result()->fetch_all(MYSQLI_ASSOC);

$facebookUrl = $user['facebook_url'] ?? '';
$instagramUrl = $user['instagram_url'] ?? '';
$portfolioUrl = $user['portfolio_url'] ?? '';
$aboutMe = $user['about_me'] ?? '';
$skills = $user['skills'] ?? '';
$course = $user['course'] ?: 'BSIT';
$yearLevel = $user['year_level'] ?: '2nd Year';
$hasSocialLinks = trim($facebookUrl) !== '' || trim($instagramUrl) !== '';
$selectedSkills = array_filter(array_map('trim', explode(',', $skills)));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($titles[$page], ENT_QUOTES, 'UTF-8'); ?> | AUProject</title>
    <link rel="stylesheet" href="Dashboard.css">
</head>

<body>
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
                    <div class="account-dropdown" id="developer-account-dropdown" data-account-dropdown hidden>
                        <button type="button" data-open-developer-profile>Profile</button>
                        <button type="button" data-open-developer-settings>Settings</button>
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
                        <a class="first-box developer-stat-link" href="Developer.php?page=active">
                            <h2>Projects completed</h2>
                            <p><?php echo (int) $bidStats['completed_projects']; ?></p>
                        </a>
                    </div>
                    <div class="developer-dashboard-panels">
                    <section class="Second-box developer-section">
                        <div class="Second-top">
                            <div>
                                <h2>Browse projects</h2>
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
                                                <p class="eyebrow">Client Project</p>
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
                                        <div class="project-detail-actions">
                                            <button class="button button-secondary" type="button" data-open-dialog="developer-project-details-<?php echo (int) $project['project_id']; ?>">Project details</button>
                                        </div>
                                    </article>
                                    <dialog class="action-dialog" id="developer-project-details-<?php echo (int) $project['project_id']; ?>" aria-labelledby="developer-project-details-title-<?php echo (int) $project['project_id']; ?>" data-action-dialog>
                                        <header class="action-dialog-header">
                                            <div><p class="eyebrow">OPEN PROJECT</p><h2 id="developer-project-details-title-<?php echo (int) $project['project_id']; ?>"><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h2></div>
                                            <button class="profile-modal-close" type="button" aria-label="Close project details" data-close-dialog>&times;</button>
                                        </header>
                                        <div class="action-dialog-body">
                                            <p class="project-category"><?php echo htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <section><h3>Description</h3><p><?php echo nl2br(htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8')); ?></p></section>
                                            <div class="project-meta">
                                                <div class="meta-item"><span>Client</span><strong><?php echo htmlspecialchars($project['client_name'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                                <div class="meta-item"><span>Budget</span><strong>₱<?php echo number_format((float) $project['budget'], 2); ?></strong></div>
                                                <div class="meta-item"><span>Deadline</span><strong><?php echo htmlspecialchars(date('F j, Y', strtotime($project['deadline'])), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            </div>
                                        </div>
                                        <div class="action-dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Close</button></div>
                                    </dialog>
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
                                            <?php if (strtolower($bid['status']) === 'accepted'): ?>
                                                <section class="accepted-contact-panel accepted-contact-compact" aria-label="Accepted client contact information">
                                                    <div class="contact-panel-heading">
                                                        <h4>Client links</h4>
                                                        <span class="contact-confirmed">Bid accepted</span>
                                                    </div>
                                                    <div class="dev-links">
                                                        <?php if (!empty($bid['client_facebook_url'])): ?><a href="<?php echo htmlspecialchars($bid['client_facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
                                                        <?php if (!empty($bid['client_instagram_url'])): ?><a href="<?php echo htmlspecialchars($bid['client_instagram_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
                                                        <?php if (empty($bid['client_facebook_url']) && empty($bid['client_instagram_url'])): ?><span class="muted-text">The client has not shared social links.</span><?php endif; ?>
                                                    </div>
                                                </section>
                                            <?php endif; ?>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    </div>

                <?php elseif ($page === 'browse'): ?>
                    <header class="page-heading">
                        <div>
                            <p class="eyebrow">FIND WORK</p>
                            <h1>Browse Projects</h1>
                            <p class="page-description">Explore open client projects and send a proposal that fits.</p>
                        </div>
                    </header>
                    <?php if (!$hasSocialLinks): ?>
                        <p class="error" role="alert">Add a Facebook or Instagram link to your profile before submitting proposals. <a href="Developer.php?profile=1">Update your public profile</a></p>
                    <?php endif; ?>
                    <form class="project-filter-form" action="Developer.php" method="GET">
                        <input type="hidden" name="page" value="browse">
                        <div class="profile-field">
                            <label for="filter-category">Category</label>
                            <select id="filter-category" name="category">
                                <option value="">All categories</option>
                                <?php foreach ($projectCategories as $category): ?>
                                    <option value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedCategory === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="project-filter-actions">
                            <button class="button button-primary" type="submit">Apply filters</button>
                            <a class="button button-secondary" href="Developer.php?page=browse">Clear</a>
                        </div>
                    </form>
                    <?php if (!$openProjects): ?>
                        <section class="empty-state">
                            <h2><?php echo $selectedCategory !== '' ? 'No projects match this category' : 'No projects available'; ?></h2>
                            <p><?php echo $selectedCategory !== '' ? 'Try another category or clear the filter.' : 'New projects will appear here when clients post them.'; ?></p>
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
                                        <div class="project-detail-actions">
                                            <button class="button button-secondary" type="button" data-open-dialog="developer-project-details-<?php echo (int) $project['project_id']; ?>">Project details</button>
                                            <button class="button button-primary" type="button" data-open-dialog="developer-submit-bid-<?php echo (int) $project['project_id']; ?>" <?php echo !$hasSocialLinks ? 'disabled' : ''; ?>>Submit a proposal</button>
                                        </div>
                                    </div>
                                </article>
                                <dialog class="action-dialog" id="developer-project-details-<?php echo (int) $project['project_id']; ?>" aria-labelledby="developer-project-details-title-<?php echo (int) $project['project_id']; ?>" data-action-dialog>
                                    <header class="action-dialog-header">
                                        <div><p class="eyebrow">OPEN PROJECT</p><h2 id="developer-project-details-title-<?php echo (int) $project['project_id']; ?>"><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h2></div>
                                        <button class="profile-modal-close" type="button" aria-label="Close project details" data-close-dialog>&times;</button>
                                    </header>
                                    <div class="action-dialog-body">
                                        <p class="project-category"><?php echo htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        <section><h3>Description</h3><p><?php echo nl2br(htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8')); ?></p></section>
                                        <div class="project-meta">
                                            <div class="meta-item"><span>Client</span><strong><?php echo htmlspecialchars($project['client_name'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <div class="meta-item"><span>Budget</span><strong>₱<?php echo number_format((float) $project['budget'], 2); ?></strong></div>
                                            <div class="meta-item"><span>Deadline</span><strong><?php echo htmlspecialchars(date('F j, Y', strtotime($project['deadline'])), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                        </div>
                                    </div>
                                    <div class="action-dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Close</button></div>
                                </dialog>
                                <dialog class="action-dialog" id="developer-submit-bid-<?php echo (int) $project['project_id']; ?>" aria-labelledby="developer-submit-bid-title-<?php echo (int) $project['project_id']; ?>" data-action-dialog>
                                    <header class="action-dialog-header">
                                        <div><p class="eyebrow">YOUR PROPOSAL</p><h2 id="developer-submit-bid-title-<?php echo (int) $project['project_id']; ?>">Submit a proposal</h2><p><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                                        <button class="profile-modal-close" type="button" aria-label="Close proposal form" data-close-dialog>&times;</button>
                                    </header>
                                    <form class="proposal-form" action="../backend/developer-bid-actions.php" method="POST">
                                                <input type="hidden" name="project_id" value="<?php echo (int) $project['project_id']; ?>">
                                                <div class="project-form-row">
                                                    <div class="profile-field"><label for="proposed-price-<?php echo (int) $project['project_id']; ?>">Proposed price (₱)</label><input type="number" id="proposed-price-<?php echo (int) $project['project_id']; ?>" name="proposed_price" min="0.01" step="0.01" required></div>
                                                    <div class="profile-field"><label for="timeline-<?php echo (int) $project['project_id']; ?>">Estimated timeline</label><input type="text" id="timeline-<?php echo (int) $project['project_id']; ?>" name="timeline" maxlength="100" placeholder="e.g. 5 or 5 days" required></div>
                                                </div>
                                                <div class="profile-field"><label for="message-<?php echo (int) $project['project_id']; ?>">Message to client</label><textarea id="message-<?php echo (int) $project['project_id']; ?>" name="message" maxlength="2000" required></textarea></div>
                                                <div class="action-dialog-actions">
                                                    <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
                                                    <button class="button button-primary" type="submit" <?php echo !$hasSocialLinks ? 'disabled' : ''; ?>>Send proposal</button>
                                                </div>
                                            </form>
                                </dialog>
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
                                        <div class="project-detail-actions">
                                            <button class="button button-secondary" type="button" data-open-dialog="developer-bid-status-<?php echo (int) $bid['bid_id']; ?>">Bid status</button>
                                        </div>
                                    </div>
                                </article>
                                <dialog class="action-dialog" id="developer-bid-status-<?php echo (int) $bid['bid_id']; ?>" aria-labelledby="developer-bid-status-title-<?php echo (int) $bid['bid_id']; ?>" data-action-dialog>
                                    <header class="action-dialog-header">
                                        <div><p class="eyebrow">BID STATUS</p><h2 id="developer-bid-status-title-<?php echo (int) $bid['bid_id']; ?>"><?php echo htmlspecialchars($bid['project_title'], ENT_QUOTES, 'UTF-8'); ?></h2></div>
                                        <button class="profile-modal-close" type="button" aria-label="Close bid status" data-close-dialog>&times;</button>
                                    </header>
                                    <div class="action-dialog-body">
                                        <p>Your proposal is <span class="status-badge status-<?php echo htmlspecialchars(strtolower($bidStatus), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($bidStatus, ENT_QUOTES, 'UTF-8'); ?></span>.</p>
                                        <div class="project-meta">
                                            <div class="meta-item"><span>Project status</span><strong><?php echo htmlspecialchars($bid['project_status'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                            <div class="meta-item"><span>Proposed price</span><strong>₱<?php echo number_format((float) $bid['proposed_price'], 2); ?></strong></div>
                                            <div class="meta-item"><span>Timeline</span><strong><?php echo htmlspecialchars($bid['timeline'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                        </div>
                                        <?php if (trim($bid['message']) !== ''): ?><div class="bid-message"><h5>Your message</h5><p><?php echo nl2br(htmlspecialchars($bid['message'], ENT_QUOTES, 'UTF-8')); ?></p></div><?php endif; ?>
                                        <?php if (strtolower($bid['status']) === 'accepted'): ?>
                                            <section class="accepted-contact-panel accepted-contact-compact" aria-label="Accepted client contact information">
                                                <div class="contact-panel-heading">
                                                    <h4><?php echo htmlspecialchars($bid['client_name'] ?: 'Client', ENT_QUOTES, 'UTF-8'); ?> · Client links</h4>
                                                    <span class="contact-confirmed">Bid accepted</span>
                                                </div>
                                                <div class="dev-links">
                                                    <?php if (!empty($bid['client_facebook_url'])): ?><a href="<?php echo htmlspecialchars($bid['client_facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
                                                    <?php if (!empty($bid['client_instagram_url'])): ?><a href="<?php echo htmlspecialchars($bid['client_instagram_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
                                                    <?php if (empty($bid['client_facebook_url']) && empty($bid['client_instagram_url'])): ?><span class="muted-text">The client has not shared social links.</span><?php endif; ?>
                                                </div>
                                            </section>
                                        <?php endif; ?>
                                    </div>
                                    <div class="action-dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Close</button></div>
                                </dialog>
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
                                    <section class="accepted-contact-panel" aria-label="Accepted client contact information">
                                        <div class="contact-panel-heading">
                                            <h4>Client Information</h4>
                                            <span class="contact-confirmed">Project connection confirmed</span>
                                        </div>
                                        <div class="accepted-contact-card">
                                            <div class="developer-identity">
                                                <span class="account-avatar account-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($project['client_name'] ?: 'C', 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                                <div><h4><?php echo htmlspecialchars($project['client_name'], ENT_QUOTES, 'UTF-8'); ?></h4><p>Client · AUProject</p></div>
                                            </div>
                                            <div class="dev-links">
                                                <?php if (!empty($project['client_facebook_url'])): ?><a href="<?php echo htmlspecialchars($project['client_facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
                                                <?php if (!empty($project['client_instagram_url'])): ?><a href="<?php echo htmlspecialchars($project['client_instagram_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
                                                <?php if (empty($project['client_facebook_url']) && empty($project['client_instagram_url'])): ?><span class="muted-text">No contact links shared.</span><?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="contact-access-note">Contact links are visible only to you and the client who accepted your proposal.</p>
                                    </section>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'profile'): ?>
                    <header class="page-heading developer-profile-page-heading">
                        <div><p class="eyebrow">YOUR PUBLIC PROFILE</p><h1>Profile</h1><p class="page-description">Share your background and work with clients.</p></div>
                    </header>
                    <?php if ($profileError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($profileError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php if ($profileSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($profileSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <div class="developer-profile-workspace">
                        <section class="developer-profile-editor">
                            <header class="developer-public-heading">
                                <?php if ($profileImage): ?>
                                    <img class="profile-avatar profile-avatar-large" src="../backend/profile-actions.php?action=profile_image" alt="">
                                <?php else: ?>
                                    <span class="profile-avatar profile-avatar-large profile-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <div><h2><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h2><p><?php echo htmlspecialchars(trim($course . ($course && $yearLevel ? ' • ' : '') . ($yearLevel ?: 'Developer')), ENT_QUOTES, 'UTF-8'); ?></p></div>
                            </header>
                            <div class="developer-profile-body">
                                <section class="profile-photo-panel developer-profile-photo-panel" aria-labelledby="photo-title">
                                    <?php if ($profileImage): ?>
                                        <img class="profile-avatar developer-photo-preview" src="../backend/profile-actions.php?action=profile_image" alt="">
                                    <?php else: ?>
                                        <span class="profile-avatar profile-avatar-fallback developer-photo-preview" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                    <div class="profile-photo-copy">
                                        <h3 id="photo-title">Profile picture</h3><p>Use a clear photo so clients can recognize your profile. PNG, JPG, or WebP up to 2 MB.</p>
                                        <div class="profile-photo-actions">
                                            <form action="../backend/profile-actions.php" method="POST" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="upload_profile_image">
                                                <label class="button button-primary upload-button" for="developer-profile-image">Choose photo</label>
                                                <input class="visually-hidden" type="file" id="developer-profile-image" name="profile_image" accept="image/png,image/jpeg,image/webp" required>
                                                <button class="button button-secondary upload-submit" type="submit">Upload</button>
                                            </form>
                                            <?php if ($profileImage): ?><form action="../backend/profile-actions.php" method="POST"><input type="hidden" name="action" value="remove_profile_image"><button class="text-button text-button-danger" type="submit">Remove</button></form><?php endif; ?>
                                        </div>
                                    </div>
                                </section>
                                <form class="developer-profile-form" action="../backend/profile-actions.php" method="POST">
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="profile-field"><label for="developer-display-name">Name</label><input type="text" id="developer-display-name" name="display_name" maxlength="255" value="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                                    <div class="profile-field"><label for="developer-course">Course</label><select id="developer-course" name="course" required><?php foreach ($projectOptions['courses'] as $courseOption): ?><option value="<?php echo htmlspecialchars($courseOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $course === $courseOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($courseOption, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                                    <div class="profile-field"><label for="developer-year-level">Year level</label><select id="developer-year-level" name="year_level" required><?php foreach ($projectYearLevels as $yearOption): ?><option value="<?php echo htmlspecialchars($yearOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $yearLevel === $yearOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($yearOption, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                                    <div class="profile-field"><label for="developer-portfolio">Portfolio link <span>Optional</span></label><input type="url" id="developer-portfolio" name="portfolio_url" value="<?php echo htmlspecialchars($portfolioUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://"></div>
                                    <div class="profile-field profile-field-full"><label for="developer-about">About Me</label><textarea id="developer-about" name="about_me" maxlength="3000" rows="5"><?php echo htmlspecialchars($aboutMe, ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                                    <fieldset class="profile-field profile-field-full skill-options-field">
                                        <legend>Skills</legend>
                                        <div class="skill-option-list">
                                            <?php foreach ($projectOptions['skills'] as $skillOption): ?>
                                                <label class="skill-option">
                                                    <input type="checkbox" name="skills[]" value="<?php echo htmlspecialchars($skillOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($skillOption, $selectedSkills, true) ? 'checked' : ''; ?>>
                                                    <span><?php echo htmlspecialchars($skillOption, ENT_QUOTES, 'UTF-8'); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                    <p class="profile-field-full social-link-note">Add at least one social link so you can send proposals and appear in Browse Developers.</p>
                                    <div class="profile-field"><label for="developer-facebook">Facebook <span>Optional if Instagram is set</span></label><input type="url" id="developer-facebook" name="facebook_url" value="<?php echo htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://facebook.com/username"></div>
                                    <div class="profile-field"><label for="developer-instagram">Instagram <span>Optional if Facebook is set</span></label><input type="url" id="developer-instagram" name="instagram_url" value="<?php echo htmlspecialchars($instagramUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://instagram.com/username"></div>
                                    <button class="button button-primary" type="submit">Save public profile</button>
                                </form>
                            </div>
                        </section>
                    </div>

                <?php endif; ?>
            </main>
            <dialog class="developer-profile-modal developer-settings-modal" aria-labelledby="developer-profile-title" data-developer-profile-modal>
                <header class="developer-settings-modal-heading">
                    <div><p class="eyebrow">YOUR PUBLIC PROFILE</p><h2 id="developer-profile-title">Edit profile</h2><p>This information is visible to logged-in clients in Browse Developers.</p></div>
                    <button class="profile-modal-close" type="button" aria-label="Close profile" data-close-developer-profile>&times;</button>
                </header>
                <?php if ($profileError !== ''): ?><p class="error developer-settings-message" role="alert"><?php echo htmlspecialchars($profileError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <?php if ($profileSuccess !== ''): ?><p class="success developer-settings-message" role="status"><?php echo htmlspecialchars($profileSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <div class="developer-settings-card">
                    <section class="profile-photo-panel developer-profile-photo-panel" aria-labelledby="developer-photo-title">
                        <?php if ($profileImage): ?>
                            <img class="profile-avatar developer-photo-preview" src="../backend/profile-actions.php?action=profile_image" alt="">
                        <?php else: ?>
                            <span class="profile-avatar profile-avatar-fallback developer-photo-preview" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <div class="profile-photo-copy">
                            <h3 id="developer-photo-title">Profile picture</h3>
                            <p>Use a clear photo so clients can recognize your profile. PNG, JPG, or WebP up to 2 MB.</p>
                            <div class="profile-photo-actions">
                                <form action="../backend/profile-actions.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_profile_image">
                                    <label class="button button-primary upload-button" for="developer-profile-image-modal">Choose photo</label>
                                    <input class="visually-hidden" type="file" id="developer-profile-image-modal" name="profile_image" accept="image/png,image/jpeg,image/webp" required>
                                    <button class="button button-secondary upload-submit" type="submit">Upload</button>
                                </form>
                                <?php if ($profileImage): ?><form action="../backend/profile-actions.php" method="POST"><input type="hidden" name="action" value="remove_profile_image"><button class="text-button text-button-danger" type="submit">Remove</button></form><?php endif; ?>
                            </div>
                        </div>
                    </section>
                    <form class="developer-profile-form" action="../backend/profile-actions.php" method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="profile-field"><label for="developer-display-name-modal">Name</label><input type="text" id="developer-display-name-modal" name="display_name" maxlength="255" value="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" required></div>
                        <div class="profile-field"><label for="developer-course-modal">Course</label><select id="developer-course-modal" name="course" required><?php foreach ($projectOptions['courses'] as $courseOption): ?><option value="<?php echo htmlspecialchars($courseOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $course === $courseOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($courseOption, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                        <div class="profile-field"><label for="developer-year-level-modal">Year level</label><select id="developer-year-level-modal" name="year_level" required><?php foreach ($projectYearLevels as $yearOption): ?><option value="<?php echo htmlspecialchars($yearOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $yearLevel === $yearOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($yearOption, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></div>
                        <div class="profile-field">
                            <div class="profile-field-heading"><label for="developer-portfolio-modal">Portfolio link <span>Optional</span></label><button class="profile-field-edit" type="button" data-toggle-link-editor aria-controls="developer-portfolio-modal" aria-expanded="false" aria-label="Edit portfolio link"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M11.6 1.4a1.4 1.4 0 0 1 2 2L5.1 11.9l-3 .8.8-3z"></path><path d="M9.9 3.1l3 3"></path></svg></button></div>
                            <?php if ($portfolioUrl !== ''): ?><a class="profile-link-value" href="<?php echo htmlspecialchars($portfolioUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" data-link-value><?php echo htmlspecialchars($portfolioUrl, ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?><span class="profile-link-value profile-link-empty" data-link-value>No portfolio link added</span><?php endif; ?>
                            <input class="profile-link-input" type="url" id="developer-portfolio-modal" name="portfolio_url" value="<?php echo htmlspecialchars($portfolioUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://" hidden>
                        </div>
                        <div class="profile-field profile-field-full"><label for="developer-about-modal">About Me</label><textarea id="developer-about-modal" name="about_me" maxlength="3000" rows="5"><?php echo htmlspecialchars($aboutMe, ENT_QUOTES, 'UTF-8'); ?></textarea></div>
                        <fieldset class="profile-field profile-field-full skill-options-field">
                            <legend>Skills</legend>
                            <div class="skill-option-list">
                                <?php foreach ($projectOptions['skills'] as $skillOption): ?>
                                    <label class="skill-option">
                                        <input type="checkbox" name="skills[]" value="<?php echo htmlspecialchars($skillOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo in_array($skillOption, $selectedSkills, true) ? 'checked' : ''; ?>>
                                        <span><?php echo htmlspecialchars($skillOption, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <p class="profile-field-full social-link-note">Add at least one social link so you can send proposals and appear in Browse Developers.</p>
                        <div class="profile-field">
                            <div class="profile-field-heading"><label for="developer-facebook-modal">Facebook <span>Optional if Instagram is set</span></label><button class="profile-field-edit" type="button" data-toggle-link-editor aria-controls="developer-facebook-modal" aria-expanded="false" aria-label="Edit Facebook link"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M11.6 1.4a1.4 1.4 0 0 1 2 2L5.1 11.9l-3 .8.8-3z"></path><path d="M9.9 3.1l3 3"></path></svg></button></div>
                            <?php if ($facebookUrl !== ''): ?><a class="profile-link-value" href="<?php echo htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" data-link-value><?php echo htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?><span class="profile-link-value profile-link-empty" data-link-value>No Facebook link added</span><?php endif; ?>
                            <input class="profile-link-input" type="url" id="developer-facebook-modal" name="facebook_url" value="<?php echo htmlspecialchars($facebookUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://facebook.com/username" hidden>
                        </div>
                        <div class="profile-field">
                            <div class="profile-field-heading"><label for="developer-instagram-modal">Instagram <span>Optional if Facebook is set</span></label><button class="profile-field-edit" type="button" data-toggle-link-editor aria-controls="developer-instagram-modal" aria-expanded="false" aria-label="Edit Instagram link"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M11.6 1.4a1.4 1.4 0 0 1 2 2L5.1 11.9l-3 .8.8-3z"></path><path d="M9.9 3.1l3 3"></path></svg></button></div>
                            <?php if ($instagramUrl !== ''): ?><a class="profile-link-value" href="<?php echo htmlspecialchars($instagramUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" data-link-value><?php echo htmlspecialchars($instagramUrl, ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?><span class="profile-link-value profile-link-empty" data-link-value>No Instagram link added</span><?php endif; ?>
                            <input class="profile-link-input" type="url" id="developer-instagram-modal" name="instagram_url" value="<?php echo htmlspecialchars($instagramUrl, ENT_QUOTES, 'UTF-8'); ?>" placeholder="https://instagram.com/username" hidden>
                        </div>
                        <button class="button button-primary" type="submit">Save public profile</button>
                    </form>
                </div>
            </dialog>
            <dialog class="developer-settings-modal" aria-labelledby="developer-settings-title" data-developer-settings-modal>
                <header class="developer-settings-modal-heading">
                    <div><p class="eyebrow">PRIVATE ACCOUNT</p><h2 id="developer-settings-title">Settings</h2><p>Email and password are private and are never shown to clients.</p></div>
                    <button class="profile-modal-close" type="button" aria-label="Close settings" data-close-developer-settings>&times;</button>
                </header>
                <?php if ($passwordError !== ''): ?><p class="error developer-settings-message" role="alert"><?php echo htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <?php if ($passwordSuccess !== ''): ?><p class="success developer-settings-message" role="status"><?php echo htmlspecialchars($passwordSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <div class="developer-settings-card">
                    <div class="developer-settings-email">
                        <div><p class="eyebrow">SIGN-IN EMAIL</p><h3>Email address</h3><p>This address is used to sign in and is never visible to clients.</p></div>
                        <div class="profile-field"><label for="developer-settings-email">Email address</label><input type="email" id="developer-settings-email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                    </div>
                    <section class="password-panel" aria-labelledby="developer-password-title">
                        <div class="password-panel-heading"><div><p class="eyebrow">SECURITY</p><h3 id="developer-password-title">Change password</h3><p>Use at least 8 characters. Your current password is required to save a new one.</p></div></div>
                        <form class="password-form" action="../backend/profile-actions.php" method="POST">
                            <div class="profile-field"><label for="developer-current-password">Current password</label><input type="password" id="developer-current-password" name="current_password" autocomplete="current-password" required></div>
                            <div class="profile-field"><label for="developer-new-password">New password</label><input type="password" id="developer-new-password" name="new_password" autocomplete="new-password" minlength="8" required></div>
                            <div class="profile-field"><label for="developer-confirm-password">Confirm new password</label><input type="password" id="developer-confirm-password" name="confirm_password" autocomplete="new-password" minlength="8" required></div>
                            <button class="button button-primary" type="submit" name="update_password">Update password</button>
                        </form>
                    </section>
                </div>
            </dialog>
        </div>
    </div>
    <script src="script.js"></script>
</body>

</html>