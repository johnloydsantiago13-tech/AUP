<?php
session_start();
require_once '../backend/config.php';

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Client') {
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

$allowedPages = ['dashboard', 'profile', 'post-project', 'projects', 'developers'];
$page = $_GET['page'] ?? 'dashboard';
if (!is_string($page) || !in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'dashboard';
}

$pageTitles = [
    'dashboard' => 'Dashboard',
    'profile' => 'My Profile',
    'post-project' => 'Post a Project',
    'projects' => 'My Projects',
    'developers' => 'Browse Developers',
];
$pageTitle = $pageTitles[$page];
$profileModal = $page === 'profile' && ($_GET['modal'] ?? '') === '1';
$displayName = $user['username'] ?: 'Client';
$profileImage = !empty($user['profile_image']);
$initial = strtoupper(substr($displayName, 0, 1));

$profileError = $_SESSION['profile_error'] ?? '';
$profileSuccess = $_SESSION['profile_success'] ?? '';
$passwordError = $_SESSION['password_error'] ?? '';
$passwordSuccess = $_SESSION['password_success'] ?? '';
$projectError = $_SESSION['project_error'] ?? '';
$projectSuccess = $_SESSION['project_success'] ?? '';
unset(
    $_SESSION['profile_error'],
    $_SESSION['profile_success'],
    $_SESSION['password_error'],
    $_SESSION['password_success'],
    $_SESSION['project_error'],
    $_SESSION['project_success']
);

$projects = [];
$bidsByProject = [];
$projectCount = 0;
$pendingBidCount = 0;
$developers = [];

if (in_array($page, ['dashboard', 'projects'], true)) {
    $projectQuery = $conn->prepare(
        'SELECT project_id, title, category, description, budget, deadline, status
         FROM projects
         WHERE client_email = ?
         ORDER BY project_id DESC'
    );
    $projectQuery->bind_param('s', $email);
    $projectQuery->execute();
    $projectResult = $projectQuery->get_result();

    while ($project = $projectResult->fetch_assoc()) {
        $projects[] = $project;
    }
    $projectCount = count($projects);

    $pendingQuery = $conn->prepare(
        "SELECT COUNT(*) AS pending_count
         FROM bids
         INNER JOIN projects ON projects.project_id = bids.project_id
         WHERE projects.client_email = ? AND bids.status = 'pending'"
    );
    $pendingQuery->bind_param('s', $email);
    $pendingQuery->execute();
    $pendingBidCount = (int) $pendingQuery->get_result()->fetch_assoc()['pending_count'];

    if ($page === 'projects') {
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

        while ($bid = $bidResult->fetch_assoc()) {
            $bidsByProject[(int) $bid['project_id']][] = $bid;
        }
    }
}

if ($page === 'developers') {
    $developerQuery = $conn->prepare(
        "SELECT username, facebook_url, instagram_url
         FROM users
         WHERE role = 'Developer'
         ORDER BY username ASC"
    );
    $developerQuery->execute();
    $developerResult = $developerQuery->get_result();

    while ($developer = $developerResult->fetch_assoc()) {
        $developers[] = $developer;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> | AUProject</title>
    <link rel="stylesheet" href="Dashboard.css">
</head>

<body class="<?php echo $profileModal ? 'has-profile-modal' : ''; ?>">
    <div class="main-content">
        <aside class="sidebar" aria-label="Client navigation">
            <a class="logo-group" href="Client.php" aria-label="AUProject client dashboard">
                <span class="logo-box" aria-hidden="true">AUP</span>
                <span class="logo-name">AUProject</span>
            </a>
            <nav class="nav-links">
                <a href="Client.php" <?php echo $page === 'dashboard' ? 'aria-current="page"' : ''; ?>>Dashboard</a>
                <a href="Client.php?page=post-project" <?php echo $page === 'post-project' ? 'aria-current="page"' : ''; ?>>Post Projects</a>
                <a href="Client.php?page=projects" <?php echo $page === 'projects' ? 'aria-current="page"' : ''; ?>>My Projects</a>
                <a href="Client.php?page=developers" <?php echo $page === 'developers' ? 'aria-current="page"' : ''; ?>>Browse Developers</a>
            </nav>
        </aside>

        <div class="workspace">
            <nav class="navbar" aria-label="Workspace navigation">
                <span class="navbar-label">Client workspace</span>
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
                        <a href="Client.php?page=profile&amp;modal=1">Profile</a>
                        <a href="index.php">Logout</a>
                    </div>
                </div>
            </nav>

            <?php if ($page === 'dashboard'): ?>
                <main class="main-topbar">
                    <p class="eyebrow">CLIENT DASHBOARD</p>
                    <h1>Welcome, <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>!</h1>
                    <p class="page-description">Track your projects and review developer proposals.</p>
                    <div class="main-boxes">
                        <section class="first-box">
                            <h2>Active Projects</h2>
                            <p><?php echo $projectCount; ?></p>
                        </section>
                        <section class="first-box1">
                            <h2>Pending Bids</h2>
                            <p><?php echo $pendingBidCount; ?></p>
                        </section>
                    </div>

                    <section class="Second-box">
                        <div class="Second-top">
                            <div>
                                <p class="eyebrow">YOUR WORK</p>
                                <h2>My Projects</h2>
                            </div>
                            <a href="Client.php?page=projects" id="ButtonP">View Project &#128065;</a>
                        </div>
                        <div class="Second-box-bottom">
                            <?php if ($projectCount === 0): ?>
                                <div class="empty-state">
                                    <span class="empty-state-icon" aria-hidden="true">+</span>
                                    <h3>No projects yet</h3>
                                    <p>Post your first project to start working with developers.</p>
                                    <a class="button button-primary" href="Client.php?page=post-project">Create your first project</a>
                                </div>
                            <?php else: ?>
                                <div class="project-list">
                                    <?php foreach (array_slice($projects, 0, 4) as $project): ?>
                                        <?php $projectStatus = $project['status'] ?: 'Open'; ?>
                                        <article class="my-project-box dashboard-project-card">
                                            <header class="project-Head">
                                                <div class="project-title">
                                                    <p class="eyebrow">PROJECT DETAILS</p>
                                                    <h3><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                                    <p class="project-category"><?php echo htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                </div>
                                                <span class="status-badge status-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $projectStatus)), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($projectStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                            </header>
                                            <div class="project-details">
                                                <div class="project-description">
                                                    <h3>Description</h3>
                                                    <p><?php echo nl2br(htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                                </div>
                                                <div class="project-meta">
                                                    <div class="meta-item"><span>Budget</span><strong>₱<?php echo number_format((float) $project['budget'], 2); ?></strong></div>
                                                    <div class="meta-item"><span>Deadline</span><strong><?php echo htmlspecialchars(date('F j, Y', strtotime($project['deadline'])), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                </main>

            <?php elseif ($page === 'profile'): ?>
                <?php if ($profileModal): ?>
                    <div class="profile-modal" role="dialog" aria-modal="true" aria-labelledby="profile-modal-title" tabindex="-1" data-profile-modal>
                    <?php endif; ?>
                    <main class="profile-page">
                        <header class="profile-card-heading">
                            <div class="profile-summary">
                                <?php if ($profileImage): ?>
                                    <img class="profile-avatar profile-avatar-large" src="../backend/profile-actions.php?action=profile_image" alt="Profile picture">
                                <?php else: ?>
                                    <span class="profile-avatar profile-avatar-large profile-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <div>
                                    <h1 id="profile-modal-title"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></h1>
                                    <p><?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?> · AUProject</p>
                                </div>
                            </div>
                            <?php if ($profileModal): ?>
                                <a class="profile-modal-close" href="Client.php" aria-label="Close profile dialog" data-modal-close>&times;</a>
                            <?php endif; ?>
                        </header>
                        <?php if ($profileError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($profileError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                        <?php if ($profileSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($profileSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

                        <div class="profile-card-body">
                            <section class="profile-photo-panel" aria-labelledby="photo-title">
                                <?php if ($profileImage): ?>
                                    <img class="profile-avatar profile-photo-preview" src="../backend/profile-actions.php?action=profile_image" alt="">
                                <?php else: ?>
                                    <span class="profile-avatar profile-photo-preview profile-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <div class="profile-photo-copy">
                                    <h3 id="photo-title">Profile picture</h3>
                                    <p>PNG, JPG, or WebP. Maximum 2 MB.</p>
                                    <div class="profile-photo-actions">
                                        <form action="../backend/profile-actions.php" method="POST" enctype="multipart/form-data">
                                            <input type="hidden" name="action" value="upload_profile_image">
                                            <label class="button button-primary upload-button" for="profile-image">Choose photo</label>
                                            <input class="visually-hidden" type="file" id="profile-image" name="profile_image" accept="image/png,image/jpeg,image/webp" required>
                                            <button class="button button-secondary upload-submit" type="submit">Upload</button>
                                        </form>
                                        <?php if ($profileImage !== ''): ?>
                                            <form action="../backend/profile-actions.php" method="POST">
                                                <input type="hidden" name="action" value="remove_profile_image">
                                                <button class="text-button text-button-danger" type="submit">Remove</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </section>

                            <form class="profile-details-form" action="../backend/profile-actions.php" method="POST">
                                <input type="hidden" name="update_profile" value="1">
                                <div class="profile-field">
                                    <label for="display_name">Display name</label>
                                    <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" required>
                                </div>
                                <div class="profile-field">
                                    <label for="profile_role">Role</label>
                                    <input type="text" id="profile_role" value="<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                                <div class="profile-field">
                                    <label for="profile_email">Email</label>
                                    <input type="email" id="profile_email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                </div>
                                <div class="profile-field">
                                    <label for="facebook_url">Facebook link <span>Optional</span></label>
                                    <input type="url" id="facebook_url" name="facebook_url" placeholder="https://facebook.com/username" value="<?php echo htmlspecialchars($user['facebook_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div class="profile-field">
                                    <label for="instagram_url">Instagram link <span>Optional</span></label>
                                    <input type="url" id="instagram_url" name="instagram_url" placeholder="https://instagram.com/username" value="<?php echo htmlspecialchars($user['instagram_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <button class="button button-primary" type="submit">Save changes</button>
                            </form>

                            <section class="password-panel" aria-labelledby="password-title">
                                <div class="password-panel-heading">
                                    <h3 id="password-title">Change password</h3>
                                </div>
                                <form class="password-form" action="../backend/profile-actions.php" method="POST">
                                    <div class="profile-field"><label for="current_password">Current password</label><input type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
                                    <div class="profile-field"><label for="new_password">New password</label><input type="password" id="new_password" name="new_password" autocomplete="new-password" minlength="8" required></div>
                                    <div class="profile-field"><label for="confirm_password">Confirm new password</label><input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" minlength="8" required></div>
                                    <button class="button button-primary" type="submit" name="update_password">Update password</button>
                                </form>
                                <?php if ($passwordError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                                <?php if ($passwordSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($passwordSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                            </section>
                        </div>
                    </main>
                    <?php if ($profileModal): ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($page === 'post-project'): ?>
                <main class="Project-box">
                    <header class="prop">
                        <p class="eyebrow">GET STARTED</p>
                        <h1>Post a Project</h1>
                        <p>Share what you need and start receiving proposals from developers.</p>
                    </header>
                    <?php if ($projectError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($projectError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php if ($projectSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($projectSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <form action="../backend/project-actions.php" method="POST" class="probox">
                        <label for="title">Project title</label>
                        <input type="text" id="title" name="title" placeholder="e.g. Student management system" maxlength="150" required>
                        <label for="category">Category</label>
                        <input type="text" id="category" name="category" placeholder="e.g. Web development" maxlength="100" required>
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Describe the project, requirements, and expected outcome" required></textarea>
                        <div class="project-form-row">
                            <div class="profile-field">
                                <label for="budget">Budget (₱)</label>
                                <input type="number" id="budget" name="budget" placeholder="2000" min="0.01" step="0.01" required>
                            </div>
                            <div class="profile-field">
                                <label for="deadline">Deadline</label>
                                <input type="date" id="deadline" name="deadline" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <button type="submit" name="create_project" class="button button-primary">Post project</button>
                    </form>
                </main>

            <?php elseif ($page === 'projects'): ?>
                <main class="my-project">
                    <header class="page-heading">
                        <div>
                            <p class="eyebrow">CLIENT WORKSPACE</p>
                            <h1>My Projects</h1>
                            <p class="page-description">Review project details and proposals from developers.</p>
                        </div>
                        <a class="button button-primary" href="Client.php?page=post-project">Post a project</a>
                    </header>
                    <?php if ($projectError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($projectError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php if ($projectSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($projectSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php if ($projectCount === 0): ?>
                        <section class="empty-state"><span class="empty-state-icon" aria-hidden="true">+</span>
                            <h2>No projects yet</h2>
                            <p>Post your first project to start receiving proposals from developers.</p><a class="button button-primary" href="Client.php?page=post-project">Create your first project</a>
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
                                            <p class="project-category"><?php echo htmlspecialchars($project['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <div class="project-head-actions">
                                            <span class="status-badge status-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $projectStatus)), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($projectStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <form action="../backend/client-project-actions.php" method="POST" onsubmit="return confirm('Delete this project?');">
                                                <input type="hidden" name="action" value="delete"><input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                                <button class="icon-button" type="submit" name="delete" aria-label="Delete project"><span aria-hidden="true">&#128465;</span></button>
                                            </form>
                                        </div>
                                    </header>
                                    <div class="project-details">
                                        <div class="project-description">
                                            <h3>Description</h3>
                                            <p><?php echo nl2br(htmlspecialchars($project['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                        </div>
                                        <div class="project-meta">
                                            <div class="meta-item"><span>Budget</span><strong>₱<?php echo number_format((float) $project['budget'], 2); ?></strong></div>
                                            <div class="meta-item"><span>Deadline</span><strong><?php echo htmlspecialchars(date('F j, Y', strtotime($project['deadline'])), ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                        </div>
                                    </div>
                                    <section class="bid-section" aria-label="Project bids">
                                        <div class="bid-section-heading">
                                            <h3>Project bids</h3><span class="bid-count"><?php echo count($projectBids); ?> <?php echo count($projectBids) === 1 ? 'bid' : 'bids'; ?></span>
                                        </div>
                                        <?php if (!$projectBids): ?>
                                            <p class="no-bids">No bids yet. Developer proposals will appear here.</p>
                                        <?php else: ?>
                                            <div class="bid-list">
                                                <?php foreach ($projectBids as $bid): ?>
                                                    <?php $bidStatus = ucfirst(strtolower($bid['status']));
                                                    $developerName = $bid['username'] ?: $bid['developer_email']; ?>
                                                    <article class="bid-card">
                                                        <div class="bid-card-header">
                                                            <div class="developer-identity"><span class="developer-avatar" aria-hidden="true">&#128100;</span>
                                                                <div>
                                                                    <h4><?php echo htmlspecialchars($developerName, ENT_QUOTES, 'UTF-8'); ?></h4>
                                                                    <p>Developer</p>
                                                                </div>
                                                            </div>
                                                            <span class="status-badge status-<?php echo htmlspecialchars(strtolower($bidStatus), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($bidStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                                        </div>
                                                        <div class="bid-meta">
                                                            <div class="meta-item"><span>Proposed price</span><strong>₱<?php echo number_format((float) $bid['proposed_price'], 2); ?></strong></div>
                                                            <div class="meta-item"><span>Estimated timeline</span><strong><?php echo htmlspecialchars($bid['timeline'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
                                                        </div>
                                                        <?php if (trim($bid['message']) !== ''): ?><div class="bid-message">
                                                                <h5>Message to client</h5>
                                                                <p><?php echo nl2br(htmlspecialchars($bid['message'], ENT_QUOTES, 'UTF-8')); ?></p>
                                                            </div><?php endif; ?>
                                                        <?php if (strtolower($bid['status']) === 'pending' && strtolower($projectStatus) === 'open'): ?>
                                                            <div class="bid-actions">
                                                                <form action="../backend/bid-actions.php" method="POST" onsubmit="return confirm('Accept this bid? Other pending bids for this project will be rejected.');"><input type="hidden" name="action" value="accept"><input type="hidden" name="bid_id" value="<?php echo (int) $bid['bid_id']; ?>"><button class="button button-accept" type="submit">Accept bid</button></form>
                                                                <form action="../backend/bid-actions.php" method="POST" onsubmit="return confirm('Reject this bid?');"><input type="hidden" name="action" value="reject"><input type="hidden" name="bid_id" value="<?php echo (int) $bid['bid_id']; ?>"><button class="button button-secondary" type="submit">Reject</button></form>
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

            <?php else: ?>
                <main class="main-topbar">
                    <header class="page-heading">
                        <div>
                            <p class="eyebrow">AUProject COMMUNITY</p>
                            <h1>Browse Developers</h1>
                            <p class="page-description">Find a developer who can help bring your project to life.</p>
                        </div>
                    </header>
                    <div class="dev-list">
                        <?php if (!$developers): ?>
                            <section class="empty-state">
                                <h2>No developers registered yet</h2>
                                <p>Check back later for new developers.</p>
                            </section>
                        <?php else: ?>
                            <?php foreach ($developers as $developer): ?>
                                <article class="dev-card">
                                    <div class="developer-identity"><span class="developer-avatar" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($developer['username'], 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <div>
                                            <h3><?php echo htmlspecialchars($developer['username'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                            <p>AUProject Developer</p>
                                        </div>
                                    </div>
                                    <div class="dev-links">
                                        <?php if (!empty($developer['facebook_url'])): ?><a href="<?php echo htmlspecialchars($developer['facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
                                        <?php if (!empty($developer['instagram_url'])): ?><a href="<?php echo htmlspecialchars($developer['instagram_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
                                        <?php if (empty($developer['facebook_url']) && empty($developer['instagram_url'])): ?><span class="muted-text">No social links shared</span><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </main>
            <?php endif; ?>
        </div>
    </div>
    <script src="script.js"></script>
</body>

</html>