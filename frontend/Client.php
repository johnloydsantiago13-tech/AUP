<?php
session_start();
require_once '../backend/config.php';
$projectOptions = require '../backend/project-options.php';
$projectCategories = $projectOptions['categories'];
$developerSkills = $projectOptions['skills'];
$developerYearLevels = $projectOptions['year_levels'];

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Client') {
    header('Location: index.php');
    exit();
}

$email = $_SESSION['email'];
$userQuery = $conn->prepare('SELECT id, username, email, role, profile_image, facebook_url, instagram_url FROM users WHERE email = ? LIMIT 1');
$userQuery->bind_param('s', $email);
$userQuery->execute();
$user = $userQuery->get_result()->fetch_assoc();

if (!$user) {
    header('Location: index.php');
    exit();
}

$allowedPages = ['dashboard', 'post-project', 'projects', 'developers'];
$page = $_GET['page'] ?? 'dashboard';
if ($page === 'profile') {
    $page = 'dashboard';
}
if (!is_string($page) || !in_array($page, $allowedPages, true)) {
    http_response_code(404);
    $page = 'dashboard';
}

$pageTitles = [
    'dashboard' => 'Dashboard',
    'post-project' => 'Post a Project',
    'projects' => 'My Projects',
    'developers' => 'Browse Developers',
];
$pageTitle = $pageTitles[$page];
$displayName = $user['username'] ?: 'Client';
$profileImage = !empty($user['profile_image']);
$initial = strtoupper(substr($displayName, 0, 1));
$hasSocialLinks = trim($user['facebook_url'] ?? '') !== '' || trim($user['instagram_url'] ?? '') !== '';

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
$projectStatusCounts = ['Open' => 0, 'In Progress' => 0, 'Completed' => 0];
$selectedProjectStatus = $_GET['status'] ?? '';
if (!is_string($selectedProjectStatus) || !array_key_exists($selectedProjectStatus, $projectStatusCounts)) {
    $selectedProjectStatus = '';
}
$developers = [];
$completedProjectsByDeveloper = [];
$selectedDeveloper = null;
$completedDeveloperProjects = [];
$developerProfileRequested = array_key_exists('developer_id', $_GET);
$selectedDeveloperSkill = $_GET['skill'] ?? '';
if (!is_string($selectedDeveloperSkill) || !in_array($selectedDeveloperSkill, $developerSkills, true)) {
    $selectedDeveloperSkill = '';
}
$selectedDeveloperYear = $_GET['year_level'] ?? '';
if (!is_string($selectedDeveloperYear) || !in_array($selectedDeveloperYear, $developerYearLevels, true)) {
    $selectedDeveloperYear = '';
}

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

    foreach ($projects as $project) {
        $status = strtolower(trim($project['status'] ?? ''));
        if ($status === 'open') {
            $projectStatusCounts['Open']++;
        } elseif ($status === 'in progress') {
            $projectStatusCounts['In Progress']++;
        } elseif ($status === 'completed') {
            $projectStatusCounts['Completed']++;
        }
    }

    if ($page === 'projects' && $selectedProjectStatus !== '') {
        $projects = array_values(array_filter(
            $projects,
            static fn (array $project): bool => strcasecmp($project['status'] ?? '', $selectedProjectStatus) === 0
        ));
    }

    if ($page === 'projects') {
        $bidQuery = $conn->prepare(
            "SELECT bids.bid_id, bids.project_id, bids.proposed_price, bids.timeline,
                    bids.message, bids.status, bids.developer_email,
                    users.id AS developer_id, users.username, users.profile_image AS developer_image,
                    users.course, users.year_level, users.facebook_url, users.instagram_url,
                    users.portfolio_url
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
    $developerId = isset($_GET['developer_id']) && is_string($_GET['developer_id'])
        ? filter_var($_GET['developer_id'], FILTER_VALIDATE_INT)
        : false;

    if ($developerId) {
        $developerQuery = $conn->prepare(
            "SELECT id, username, profile_image, about_me, skills, course, year_level,
                    portfolio_url, facebook_url, instagram_url,
                    (SELECT COUNT(DISTINCT project_id)
                     FROM developer_project_history
                     WHERE developer_id = users.id) AS completed_project_count
             FROM users
             WHERE id = ? AND role = 'Developer'
               AND (NULLIF(TRIM(facebook_url), '') IS NOT NULL OR NULLIF(TRIM(instagram_url), '') IS NOT NULL)
             LIMIT 1"
        );
        $developerQuery->bind_param('i', $developerId);
        $developerQuery->execute();
        $selectedDeveloper = $developerQuery->get_result()->fetch_assoc();

        if ($selectedDeveloper) {
            $completedProjectsQuery = $conn->prepare(
                "SELECT title, category, description
                 FROM developer_project_history
                 WHERE developer_id = ?
                 ORDER BY completed_at DESC, history_id DESC"
            );
            $completedProjectsQuery->bind_param('i', $developerId);
            $completedProjectsQuery->execute();
            $completedDeveloperProjects = $completedProjectsQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }

    if (!$developerProfileRequested || $selectedDeveloper) {
        $developerQuery = $conn->prepare(
            "SELECT id, username, profile_image, about_me, skills, course, year_level,
                    portfolio_url, facebook_url, instagram_url,
                    (SELECT COUNT(DISTINCT project_id)
                     FROM developer_project_history
                     WHERE developer_id = users.id) AS completed_project_count
             FROM users
             WHERE role = 'Developer'
               AND (NULLIF(TRIM(facebook_url), '') IS NOT NULL OR NULLIF(TRIM(instagram_url), '') IS NOT NULL)
             ORDER BY username ASC"
        );
        $developerQuery->execute();
        $developers = $developerQuery->get_result()->fetch_all(MYSQLI_ASSOC);
        if ($selectedDeveloperSkill !== '' || $selectedDeveloperYear !== '') {
            $developers = array_values(array_filter(
                $developers,
                static function (array $developer) use ($selectedDeveloperSkill, $selectedDeveloperYear): bool {
                    $skills = array_map('trim', explode(',', $developer['skills'] ?? ''));
                    return ($selectedDeveloperSkill === '' || in_array($selectedDeveloperSkill, $skills, true))
                        && ($selectedDeveloperYear === '' || ($developer['year_level'] ?? '') === $selectedDeveloperYear);
                }
            ));
        }

        if ($developers) {
            $completedProfilesQuery = $conn->prepare(
                "SELECT users.id AS developer_id, history.title, history.category, history.description
                 FROM users
                 INNER JOIN developer_project_history AS history ON history.developer_id = users.id
                 WHERE users.role = 'Developer'
                   AND (NULLIF(TRIM(users.facebook_url), '') IS NOT NULL OR NULLIF(TRIM(users.instagram_url), '') IS NOT NULL)
                 ORDER BY history.completed_at DESC, history.history_id DESC"
            );
            $completedProfilesQuery->execute();
            $completedProfileResult = $completedProfilesQuery->get_result();
            while ($completedProject = $completedProfileResult->fetch_assoc()) {
                $completedProjectsByDeveloper[(int) $completedProject['developer_id']][] = $completedProject;
            }
        }
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

<body>
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
                <a class="mobile-navbar-brand logo-group" href="Client.php" aria-label="AUProject client dashboard">
                    <span class="logo-box" aria-hidden="true">AUP</span>
                    <span class="logo-name">AUProject</span>
                </a>
                <span class="navbar-label">Client workspace</span>
                <div class="account-menu" data-account-menu>
                    <button class="account-menu-toggle" type="button" aria-label="Open profile and logout menu" aria-expanded="false" aria-controls="client-account-dropdown" data-menu-toggle>
                        <?php if ($profileImage): ?>
                            <img class="account-avatar" src="../backend/profile-actions.php?action=profile_image" alt="">
                        <?php else: ?>
                            <span class="account-avatar account-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <span class="account-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="account-menu-icon" aria-hidden="true"><i></i><i></i><i></i></span>
                    </button>
                    <div class="account-dropdown" id="client-account-dropdown" data-account-dropdown hidden>
                        <button type="button" data-open-client-profile>Profile</button>
                        <a href="index.php">Logout</a>
                    </div>
                </div>
            </nav>

            <?php if ($page === 'dashboard'): ?>
                <main class="main-topbar">
                    <h1>Welcome, <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>!</h1>
                    <p class="page-description">Track your projects and review developer proposals.</p>
                    <div class="main-boxes">
                        <a class="first-box developer-stat-link" href="Client.php?page=projects&amp;status=In%20Progress">
                            <h2>Active Projects</h2>
                            <p><?php echo $projectStatusCounts['In Progress']; ?></p>
                        </a>
                        <a class="first-box1 developer-stat-link" href="Client.php?page=projects&amp;status=Open">
                            <h2>Pending Projects</h2>
                            <p><?php echo $projectStatusCounts['Open']; ?></p>
                        </a>
                    </div>

                    <section class="Second-box">
                        <div class="Second-top">
                            <div>
                                <p class="eyebrow">YOUR WORK</p>
                                <h2>My Projects</h2>
                            </div>
                            <a href="Client.php?page=projects" id="ButtonP">View Project</a>
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

            <?php elseif ($page === 'post-project'): ?>
                <main class="Project-box">
                    <header class="prop">
                        <h1>Post a Project</h1>
                        <p>Share what you need and start receiving proposals from developers.</p>
                    </header>
                    <?php if ($projectError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($projectError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php if ($projectSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($projectSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php if (!$hasSocialLinks): ?>
                        <p class="error" role="alert">Add a Facebook or Instagram link in your profile before posting a project. <a href="Client.php?profile=1">Update your profile</a></p>
                    <?php endif; ?>
                    <form action="../backend/project-actions.php" method="POST" class="probox">
                        <label for="title">Project title</label>
                        <input type="text" id="title" name="title" placeholder="e.g. Student management system" maxlength="150" required>
                        <label for="category">Category</label>
                        <select id="category" name="category" required>
                            <option value="">Choose a category</option>
                            <?php foreach ($projectCategories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="description">Description</label>
                        <textarea id="description" name="description" placeholder="Describe the project, requirements, and expected outcome" required></textarea>
                        <div class="project-form-row">
                            <div class="profile-field">
                                <label for="budget">Budget (₱)</label>
                                <input type="number" id="budget" name="budget" placeholder="0" required>
                            </div>
                            <div class="profile-field">
                                <label for="deadline">Deadline</label>
                                <input type="date" id="deadline" name="deadline" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <button type="submit" name="create_project" class="button button-primary" <?php echo !$hasSocialLinks ? 'disabled' : ''; ?>>Post project</button>
                    </form>
                </main>

            <?php elseif ($page === 'projects'): ?>
                <main class="my-project">
                    <header class="page-heading">
                        <div>
                            <h1>My Projects</h1>
                            <p class="page-description">Review project details and proposals from developers.</p>
                        </div>
                        <a class="button button-primary" href="Client.php?page=post-project">Post a project</a>
                    </header>
                    <nav class="project-status-filters" aria-label="Filter projects by status">
                        <a href="Client.php?page=projects" <?php echo $selectedProjectStatus === '' ? 'aria-current="page"' : ''; ?>>All <span><?php echo $projectCount; ?></span></a>
                        <a href="Client.php?page=projects&amp;status=Open" <?php echo $selectedProjectStatus === 'Open' ? 'aria-current="page"' : ''; ?>>Pending <span><?php echo $projectStatusCounts['Open']; ?></span></a>
                        <a href="Client.php?page=projects&amp;status=In%20Progress" <?php echo $selectedProjectStatus === 'In Progress' ? 'aria-current="page"' : ''; ?>>Active <span><?php echo $projectStatusCounts['In Progress']; ?></span></a>
                        <a href="Client.php?page=projects&amp;status=Completed" <?php echo $selectedProjectStatus === 'Completed' ? 'aria-current="page"' : ''; ?>>Completed <span><?php echo $projectStatusCounts['Completed']; ?></span></a>
                    </nav>
                    <?php if ($projectError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($projectError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php if ($projectSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($projectSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                    <?php if (!$projects): ?>
                        <section class="empty-state"><span class="empty-state-icon" aria-hidden="true">+</span>
                            <h2><?php echo $selectedProjectStatus !== '' ? 'No ' . strtolower($selectedProjectStatus) . ' projects' : 'No projects yet'; ?></h2>
                            <p><?php echo $selectedProjectStatus !== '' ? 'Projects with this status will appear here.' : 'Post your first project to start receiving proposals from developers.'; ?></p>
                            <?php if ($selectedProjectStatus !== ''): ?>
                                <a class="button button-secondary" href="Client.php?page=projects">View all projects</a>
                            <?php else: ?>
                                <a class="button button-primary" href="Client.php?page=post-project">Create your first project</a>
                            <?php endif; ?>
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
                                            <button class="status-badge status-badge-trigger status-<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $projectStatus)), ENT_QUOTES, 'UTF-8'); ?>" type="button" data-open-dialog="client-project-status-<?php echo $projectId; ?>"><?php echo htmlspecialchars($projectStatus, ENT_QUOTES, 'UTF-8'); ?></button>
                                            <?php if (strtolower($projectStatus) === 'in progress'): ?>
                                                <button class="button button-accept project-complete-trigger" type="button" data-open-dialog="client-project-status-<?php echo $projectId; ?>">
                                                    <span aria-hidden="true">&#10003;</span>
                                                </button>
                                            <?php endif; ?>
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
                                    <dialog class="action-dialog" id="client-project-status-<?php echo $projectId; ?>" aria-labelledby="client-project-status-title-<?php echo $projectId; ?>" data-action-dialog>
                                        <header class="action-dialog-header">
                                            <div><p class="eyebrow">PROJECT STATUS</p><h2 id="client-project-status-title-<?php echo $projectId; ?>"><?php echo htmlspecialchars($project['title'], ENT_QUOTES, 'UTF-8'); ?></h2></div>
                                            <button class="profile-modal-close" type="button" aria-label="Close project status" data-close-dialog>&times;</button>
                                        </header>
                                        <div class="action-dialog-body">
                                            <p>This project is currently <strong><?php echo htmlspecialchars($projectStatus, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
                                            <p><?php echo strtolower($projectStatus) === 'in progress' ? 'When the work is finished, mark the project as completed to update its status.' : (strtolower($projectStatus) === 'completed' ? 'This project has been marked as completed.' : 'This project is open and can receive developer proposals.'); ?></p>
                                        </div>
                                        <?php if (strtolower($projectStatus) === 'in progress'): ?>
                                            <form class="action-dialog-actions" action="../backend/client-project-actions.php" method="POST">
                                                <input type="hidden" name="action" value="complete">
                                                <input type="hidden" name="project_id" value="<?php echo $projectId; ?>">
                                                <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
                                                <button class="button button-primary" type="submit">Mark project complete</button>
                                            </form>
                                        <?php else: ?>
                                            <div class="action-dialog-actions"><button class="button button-secondary" type="button" data-close-dialog>Close</button></div>
                                        <?php endif; ?>
                                    </dialog>
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
                                                    $developerName = $bid['username'] ?: 'Developer'; ?>
                                                    <article class="bid-card">
                                                        <div class="bid-card-header">
                                                            <div class="developer-identity">
                                                                <?php if (!empty($bid['developer_image'])): ?>
                                                                    <img class="account-avatar" src="../backend/profile-actions.php?action=profile_image&amp;user_id=<?php echo (int) $bid['developer_id']; ?>" alt="">
                                                                <?php else: ?>
                                                                    <span class="account-avatar account-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($developerName, 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <?php endif; ?>
                                                                <div>
                                                                    <h4><?php echo htmlspecialchars($developerName, ENT_QUOTES, 'UTF-8'); ?></h4>
                                                                    <p><?php echo htmlspecialchars((($bid['course'] ?? '') ?: 'BSIT') . ' • ' . (($bid['year_level'] ?? '') ?: '2nd Year'), ENT_QUOTES, 'UTF-8'); ?></p>
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
                                                                <button class="button button-accept" type="button" data-open-dialog="client-accept-bid-<?php echo (int) $bid['bid_id']; ?>">Accept bid</button>
                                                                <dialog class="action-dialog" id="client-accept-bid-<?php echo (int) $bid['bid_id']; ?>" aria-labelledby="client-accept-bid-title-<?php echo (int) $bid['bid_id']; ?>" data-action-dialog>
                                                                    <header class="action-dialog-header">
                                                                        <div><p class="eyebrow">CONFIRM BID</p><h2 id="client-accept-bid-title-<?php echo (int) $bid['bid_id']; ?>">Accept <?php echo htmlspecialchars($developerName, ENT_QUOTES, 'UTF-8'); ?>'s bid?</h2></div>
                                                                        <button class="profile-modal-close" type="button" aria-label="Close confirmation" data-close-dialog>&times;</button>
                                                                    </header>
                                                                    <div class="action-dialog-body"><p>Accepting this bid will move the project to In Progress and reject all other pending bids for this project.</p></div>
                                                                    <form class="action-dialog-actions" action="../backend/bid-actions.php" method="POST">
                                                                        <input type="hidden" name="action" value="accept">
                                                                        <input type="hidden" name="bid_id" value="<?php echo (int) $bid['bid_id']; ?>">
                                                                        <button class="button button-secondary" type="button" data-close-dialog>Cancel</button>
                                                                        <button class="button button-accept" type="submit">Accept bid</button>
                                                                    </form>
                                                                </dialog>
                                                                <form action="../backend/bid-actions.php" method="POST" onsubmit="return confirm('Reject this bid?');"><input type="hidden" name="action" value="reject"><input type="hidden" name="bid_id" value="<?php echo (int) $bid['bid_id']; ?>"><button class="button button-secondary" type="submit">Reject</button></form>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (strtolower($bid['status']) === 'accepted'): ?>
                                                            <section class="accepted-contact-panel" aria-label="Accepted developer contact information">
                                                                <div class="contact-panel-heading">
                                                                    <h4>Developer Information</h4>
                                                                    <span class="contact-confirmed">Project connection confirmed</span>
                                                                </div>
                                                                <div class="accepted-contact-card">
                                                                    <div class="developer-identity">
                                                                        <?php if (!empty($bid['developer_image'])): ?>
                                                                            <img class="account-avatar" src="../backend/profile-actions.php?action=profile_image&amp;user_id=<?php echo (int) $bid['developer_id']; ?>" alt="">
                                                                        <?php else: ?>
                                                                            <span class="account-avatar account-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($developerName, 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                                                        <?php endif; ?>
                                                                        <div><h4><?php echo htmlspecialchars($developerName, ENT_QUOTES, 'UTF-8'); ?></h4><p>Developer · AUProject</p></div>
                                                                    </div>
                                                                    <div class="dev-links">
                                                                        <?php if (!empty($bid['portfolio_url'])): ?><a href="<?php echo htmlspecialchars($bid['portfolio_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Portfolio</a><?php endif; ?>
                                                                        <?php if (!empty($bid['facebook_url'])): ?><a href="<?php echo htmlspecialchars($bid['facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
                                                                        <?php if (!empty($bid['instagram_url'])): ?><a href="<?php echo htmlspecialchars($bid['instagram_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
                                                                        <?php if (empty($bid['portfolio_url']) && empty($bid['facebook_url']) && empty($bid['instagram_url'])): ?><span class="muted-text">No contact links shared.</span><?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </section>
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
                        <form class="project-filter-form developer-filter-form" action="Client.php" method="GET">
                            <input type="hidden" name="page" value="developers">
                            <div class="profile-field">
                                <label for="developer-skill-filter">Skill</label>
                                <select id="developer-skill-filter" name="skill">
                                    <option value="">All skills</option>
                                    <?php foreach ($developerSkills as $skillOption): ?>
                                        <option value="<?php echo htmlspecialchars($skillOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedDeveloperSkill === $skillOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($skillOption, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="profile-field">
                                <label for="developer-year-filter">Year level</label>
                                <select id="developer-year-filter" name="year_level">
                                    <option value="">All year levels</option>
                                    <?php foreach ($developerYearLevels as $yearOption): ?>
                                        <option value="<?php echo htmlspecialchars($yearOption, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selectedDeveloperYear === $yearOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($yearOption, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="project-filter-actions">
                                <button class="button button-primary" type="submit">Apply filters</button>
                                <a class="button button-secondary" href="Client.php?page=developers">Clear</a>
                            </div>
                        </form>
                    <?php if ($selectedDeveloper): ?>
                        <?php $publicName = $selectedDeveloper['username'] ?: 'Developer'; ?>
                        <dialog class="action-dialog developer-profile-dialog" aria-labelledby="legacy-developer-profile-title" data-action-dialog data-dialog-start-open>
                            <header class="action-dialog-header">
                                <div><p class="eyebrow">DEVELOPER PROFILE</p><h2 id="legacy-developer-profile-title"><?php echo htmlspecialchars($publicName, ENT_QUOTES, 'UTF-8'); ?></h2></div>
                                <button class="profile-modal-close" type="button" aria-label="Close developer profile" data-close-dialog>&times;</button>
                            </header>
                            <section class="developer-public-profile">
                            <header class="developer-public-heading">
                                <?php if (!empty($selectedDeveloper['profile_image'])): ?>
                                    <img class="profile-avatar profile-avatar-large" src="../backend/profile-actions.php?action=profile_image&amp;user_id=<?php echo (int) $selectedDeveloper['id']; ?>" alt="">
                                <?php else: ?>
                                    <span class="profile-avatar profile-avatar-large profile-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($publicName, 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <div>
                                    <p class="eyebrow">DEVELOPER PROFILE</p>
                                    <h2><?php echo htmlspecialchars($publicName, ENT_QUOTES, 'UTF-8'); ?></h2>
                                    <p><?php echo htmlspecialchars((($selectedDeveloper['course'] ?? '') ?: 'BSIT') . ' • ' . (($selectedDeveloper['year_level'] ?? '') ?: '2nd Year'), ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                                <span class="completed-project-count"><?php echo (int) $selectedDeveloper['completed_project_count']; ?> completed <?php echo (int) $selectedDeveloper['completed_project_count'] === 1 ? 'project' : 'projects'; ?></span>
                            </header>
                            <section class="public-profile-section">
                                <h3>About Me</h3>
                                <p><?php echo nl2br(htmlspecialchars($selectedDeveloper['about_me'] ?: 'This developer has not added an introduction yet.', ENT_QUOTES, 'UTF-8')); ?></p>
                            </section>
                            <section class="public-profile-section">
                                <h3>Skills</h3>
                                <div class="skill-list">
                                    <?php foreach (array_filter(array_map('trim', explode(',', $selectedDeveloper['skills'] ?? ''))) as $skill): ?>
                                        <span class="skill-chip"><?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                    <?php if (trim($selectedDeveloper['skills'] ?? '') === ''): ?><span class="muted-text">No skills listed yet.</span><?php endif; ?>
                                </div>
                            </section>
                            <section class="public-profile-section">
                                <h3>Portfolio &amp; social links</h3>
                                <div class="dev-links">
                                    <?php if (!empty($selectedDeveloper['portfolio_url'])): ?><a href="<?php echo htmlspecialchars($selectedDeveloper['portfolio_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Portfolio</a><?php endif; ?>
                                    <?php if (!empty($selectedDeveloper['facebook_url'])): ?><a href="<?php echo htmlspecialchars($selectedDeveloper['facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
                                    <?php if (!empty($selectedDeveloper['instagram_url'])): ?><a href="<?php echo htmlspecialchars($selectedDeveloper['instagram_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
                                    <?php if (empty($selectedDeveloper['portfolio_url']) && empty($selectedDeveloper['facebook_url']) && empty($selectedDeveloper['instagram_url'])): ?><span class="muted-text">No links shared.</span><?php endif; ?>
                                </div>
                            </section>
                            <section class="public-profile-section">
                                <h3>Completed projects</h3>
                                <?php if (!$completedDeveloperProjects): ?>
                                    <p class="muted-text">No completed projects to show yet.</p>
                                <?php else: ?>
                                    <div class="completed-project-list">
                                        <?php foreach ($completedDeveloperProjects as $completedProject): ?>
                                            <article class="completed-project-item">
                                                <h4><?php echo htmlspecialchars($completedProject['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                <p class="project-category"><?php echo htmlspecialchars($completedProject['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p><?php echo nl2br(htmlspecialchars($completedProject['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                            </article>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </section>
                            </section>
                        </dialog>
                    <?php endif; ?>
                    <?php if ($developerProfileRequested && !$selectedDeveloper): ?>
                        <section class="empty-state">
                            <h2>Developer profile not found</h2>
                            <p>This profile may have been removed or is unavailable.</p>
                            <a class="button button-primary" href="Client.php?page=developers">Browse developers</a>
                        </section>
                    <?php else: ?>
                    <div class="dev-list">
                        <?php if (!$developers): ?>
                            <section class="empty-state">
                                <h2>No developers registered yet</h2>
                                <p>Check back later for new developers.</p>
                            </section>
                        <?php else: ?>
                            <?php foreach ($developers as $developer): ?>
                                <article class="dev-card">
                                    <div class="developer-card-avatar">
                                        <?php if (!empty($developer['profile_image'])): ?>
                                            <img class="account-avatar" src="../backend/profile-actions.php?action=profile_image&amp;user_id=<?php echo (int) $developer['id']; ?>" alt="">
                                        <?php else: ?>
                                            <span class="account-avatar account-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($developer['username'] ?: 'D', 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="developer-card-content">
                                        <header class="developer-card-heading">
                                            <div class="developer-identity">
                                                <div>
                                                    <h3><?php echo htmlspecialchars($developer['username'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                                    <p><?php echo htmlspecialchars((($developer['course'] ?? '') ?: 'BSIT') . ' • ' . (($developer['year_level'] ?? '') ?: '2nd Year'), ENT_QUOTES, 'UTF-8'); ?></p>
                                                </div>
                                            </div>
                                            <div class="dev-links">
                                                <button class="button button-secondary developer-card-cta" type="button" data-open-dialog="client-developer-profile-<?php echo (int) $developer['id']; ?>">View profile</button>
                                            </div>
                                        </header>
                                        <div class="developer-card-meta"><strong><?php echo (int) $developer['completed_project_count']; ?></strong><span>completed projects</span></div>
                                        <?php $cardSkills = array_values(array_filter(array_map('trim', explode(',', $developer['skills'] ?? '')))); ?>
                                        <div class="developer-card-skills">
                                            <?php foreach (array_slice($cardSkills, 0, 4) as $skill): ?>
                                                <span class="skill-chip"><?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($cardSkills) > 4): ?><span class="skill-chip">+<?php echo count($cardSkills) - 4; ?></span><?php endif; ?>
                                            <?php if (!$cardSkills): ?><span class="muted-text">No skills listed yet.</span><?php endif; ?>
                                        </div>
                                        <p class="developer-card-about"><?php echo htmlspecialchars($developer['about_me'] ?: 'This developer has not added an introduction yet.', ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </article>
                                <?php $profileProjects = $completedProjectsByDeveloper[(int) $developer['id']] ?? []; ?>
                                <dialog class="action-dialog developer-profile-dialog" id="client-developer-profile-<?php echo (int) $developer['id']; ?>" aria-labelledby="client-developer-profile-title-<?php echo (int) $developer['id']; ?>" data-action-dialog>
                                    <header class="action-dialog-header">
                                        <div><p class="eyebrow">DEVELOPER PROFILE</p><h2 id="client-developer-profile-title-<?php echo (int) $developer['id']; ?>"><?php echo htmlspecialchars($developer['username'] ?: 'Developer', ENT_QUOTES, 'UTF-8'); ?></h2></div>
                                        <button class="profile-modal-close" type="button" aria-label="Close developer profile" data-close-dialog>&times;</button>
                                    </header>
                                    <section class="developer-public-profile">
                                        <header class="developer-public-heading">
                                            <?php if (!empty($developer['profile_image'])): ?>
                                                <img class="profile-avatar profile-avatar-large" src="../backend/profile-actions.php?action=profile_image&amp;user_id=<?php echo (int) $developer['id']; ?>" alt="">
                                            <?php else: ?>
                                                <span class="profile-avatar profile-avatar-large profile-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars(strtoupper(substr($developer['username'] ?: 'D', 0, 1)), ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                            <div><p class="eyebrow">PUBLIC PROFILE</p><p><?php echo htmlspecialchars((($developer['course'] ?? '') ?: 'BSIT') . ' • ' . (($developer['year_level'] ?? '') ?: '2nd Year'), ENT_QUOTES, 'UTF-8'); ?></p></div>
                                            <span class="completed-project-count"><?php echo (int) $developer['completed_project_count']; ?> completed <?php echo (int) $developer['completed_project_count'] === 1 ? 'project' : 'projects'; ?></span>
                                        </header>
                                        <section class="public-profile-section"><h3>About Me</h3><p><?php echo nl2br(htmlspecialchars($developer['about_me'] ?: 'This developer has not added an introduction yet.', ENT_QUOTES, 'UTF-8')); ?></p></section>
                                        <section class="public-profile-section">
                                            <h3>Skills</h3>
                                            <div class="skill-list">
                                                <?php $profileSkills = array_filter(array_map('trim', explode(',', $developer['skills'] ?? ''))); ?>
                                                <?php foreach ($profileSkills as $skill): ?><span class="skill-chip"><?php echo htmlspecialchars($skill, ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?>
                                                <?php if (!$profileSkills): ?><span class="muted-text">No skills listed yet.</span><?php endif; ?>
                                            </div>
                                        </section>
                                        <section class="public-profile-section">
                                            <h3>Portfolio &amp; social links</h3>
                                            <div class="dev-links">
                                                <?php if (!empty($developer['portfolio_url'])): ?><a href="<?php echo htmlspecialchars($developer['portfolio_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Portfolio</a><?php endif; ?>
                                                <?php if (!empty($developer['facebook_url'])): ?><a href="<?php echo htmlspecialchars($developer['facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Facebook</a><?php endif; ?>
                                                <?php if (!empty($developer['instagram_url'])): ?><a href="<?php echo htmlspecialchars($developer['instagram_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Instagram</a><?php endif; ?>
                                            </div>
                                        </section>
                                        <section class="public-profile-section">
                                            <h3>Completed projects</h3>
                                            <?php if (!$profileProjects): ?>
                                                <p class="muted-text">No completed projects to show yet.</p>
                                            <?php else: ?>
                                                <div class="completed-project-list">
                                                    <?php foreach ($profileProjects as $completedProject): ?>
                                                        <article class="completed-project-item">
                                                            <h4><?php echo htmlspecialchars($completedProject['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                                            <p class="project-category"><?php echo htmlspecialchars($completedProject['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                                                            <p><?php echo nl2br(htmlspecialchars($completedProject['description'], ENT_QUOTES, 'UTF-8')); ?></p>
                                                        </article>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </section>
                                    </section>
                                </dialog>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </main>
            <?php endif; ?>
            <dialog class="client-profile-modal" aria-labelledby="client-profile-title" data-client-profile-modal>
                <header class="profile-card-heading">
                    <div class="profile-summary">
                        <button class="profile-modal-close1" type="button" aria-label="Close profile" data-close-client-profile>&times;</button>
                    </div>
                </header>
                <?php if ($profileError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($profileError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <?php if ($profileSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($profileSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <?php if ($passwordError !== ''): ?><p class="error" role="alert"><?php echo htmlspecialchars($passwordError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <?php if ($passwordSuccess !== ''): ?><p class="success" role="status"><?php echo htmlspecialchars($passwordSuccess, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
                <div class="profile-card-body">
                    <section class="profile-photo-panel" aria-labelledby="client-photo-title">
                        <?php if ($profileImage): ?>
                            <img class="profile-avatar profile-photo-preview" src="../backend/profile-actions.php?action=profile_image" alt="">
                        <?php else: ?>
                            <span class="profile-avatar profile-photo-preview profile-avatar-fallback" aria-hidden="true"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <div class="profile-photo-copy">
                            <h3 id="client-photo-title">Profile picture</h3>
                            <p>PNG, JPG, or WebP. Maximum 2 MB.</p>
                            <div class="profile-photo-actions">
                                <form action="../backend/profile-actions.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="action" value="upload_profile_image">
                                    <label class="button button-primary upload-button" for="client-profile-image">Choose photo</label>
                                    <input class="visually-hidden" type="file" id="client-profile-image" name="profile_image" accept="image/png,image/jpeg,image/webp" required>
                                    <button class="button button-secondary upload-submit" type="submit">Upload</button>
                                </form>
                                <?php if ($profileImage): ?>
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
                        <div class="profile-field"><label for="client-display-name">Display name</label><input type="text" id="client-display-name" name="display_name" value="<?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>" maxlength="255" required></div>
                        <div class="profile-field"><label for="client-profile-role">Role</label><input type="text" id="client-profile-role" value="<?php echo htmlspecialchars($user['role'], ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                        <div class="profile-field"><label for="client-profile-email">Email</label><input type="email" id="client-profile-email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" readonly></div>
                        <div class="profile-field">
                            <div class="profile-field-heading"><label for="client-facebook">Facebook link <span>Optional if Instagram is set</span></label><button class="profile-field-edit" type="button" data-toggle-link-editor aria-controls="client-facebook" aria-expanded="false" aria-label="Edit Facebook link"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M11.6 1.4a1.4 1.4 0 0 1 2 2L5.1 11.9l-3 .8.8-3z"></path><path d="M9.9 3.1l3 3"></path></svg></button></div>
                            <?php if (!empty($user['facebook_url'])): ?><a class="profile-link-value" href="<?php echo htmlspecialchars($user['facebook_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" data-link-value><?php echo htmlspecialchars($user['facebook_url'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?><span class="profile-link-value profile-link-empty" data-link-value>No Facebook link added</span><?php endif; ?>
                            <input class="profile-link-input" type="url" id="client-facebook" name="facebook_url" placeholder="https://facebook.com/username" value="<?php echo htmlspecialchars($user['facebook_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" hidden>
                        </div>
                        <button class="button button-primary" type="submit">Save changes</button>
                        <div class="profile-field">
                            <div class="profile-field-heading"><label for="client-instagram">Instagram link <span>Optional if Facebook is set</span></label><button class="profile-field-edit" type="button" data-toggle-link-editor aria-controls="client-instagram" aria-expanded="false" aria-label="Edit Instagram link"><svg aria-hidden="true" viewBox="0 0 16 16"><path d="M11.6 1.4a1.4 1.4 0 0 1 2 2L5.1 11.9l-3 .8.8-3z"></path><path d="M9.9 3.1l3 3"></path></svg></button></div>
                            <?php if (!empty($user['instagram_url'])): ?><a class="profile-link-value" href="<?php echo htmlspecialchars($user['instagram_url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" data-link-value><?php echo htmlspecialchars($user['instagram_url'], ENT_QUOTES, 'UTF-8'); ?></a><?php else: ?><span class="profile-link-value profile-link-empty" data-link-value>No Instagram link added</span><?php endif; ?>
                            <input class="profile-link-input" type="url" id="client-instagram" name="instagram_url" placeholder="https://instagram.com/username" value="<?php echo htmlspecialchars($user['instagram_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" hidden>
                        </div>
                    </form>
                    <section class="password-panel" aria-labelledby="client-password-title">
                        <div class="password-panel-heading"><h3 id="client-password-title">Change password</h3></div>
                        <form class="password-form" action="../backend/profile-actions.php" method="POST">
                            <div class="profile-field"><label for="client-current-password">Current password</label><input type="password" id="client-current-password" name="current_password" autocomplete="current-password" required></div>
                            <div class="profile-field"><label for="client-new-password">New password</label><input type="password" id="client-new-password" name="new_password" autocomplete="new-password" minlength="8" required></div>
                            <div class="profile-field"><label for="client-confirm-password">Confirm new password</label><input type="password" id="client-confirm-password" name="confirm_password" autocomplete="new-password" minlength="8" required></div>
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