<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['email'])) {
    header('Location: ../frontend/index.php');
    exit();
}

$action = $_POST['action'] ?? '';
$bidId = filter_var($_POST['bid_id'] ?? null, FILTER_VALIDATE_INT);
$clientEmail = $_SESSION['email'];

if ($action !== 'accept' || !$bidId) {
    header('Location: ../frontend/ClientBids.php');
    exit();
}

$bidQuery = $conn->prepare(
    "SELECT bids.bid_id, bids.project_id
     FROM bids
     INNER JOIN projects ON projects.project_id = bids.project_id
     WHERE bids.bid_id = ? AND projects.client_email = ? AND bids.status = 'Pending'
     LIMIT 1"
);
$bidQuery->bind_param('is', $bidId, $clientEmail);
$bidQuery->execute();
$bid = $bidQuery->get_result()->fetch_assoc();

if (!$bid) {
    $_SESSION['project_error'] = 'This bid is no longer available.';
    header('Location: ../frontend/ClientBids.php');
    exit();
}

$conn->begin_transaction();
$acceptBid = $conn->prepare("UPDATE bids SET status = 'Accepted' WHERE bid_id = ?");
$acceptBid->bind_param('i', $bidId);
$acceptSucceeded = $acceptBid->execute();

$rejectOthers = $conn->prepare(
    "UPDATE bids SET status = 'Rejected'
     WHERE project_id = ? AND bid_id <> ? AND status = 'Pending'"
);
$rejectOthers->bind_param('ii', $bid['project_id'], $bidId);
$rejectSucceeded = $rejectOthers->execute();

$closeProject = $conn->prepare("UPDATE projects SET status = 'In Progress' WHERE project_id = ?");
$closeProject->bind_param('i', $bid['project_id']);
$projectSucceeded = $closeProject->execute();

if ($acceptSucceeded && $rejectSucceeded && $projectSucceeded) {
    $conn->commit();
    $_SESSION['project_success'] = 'Bid accepted. Other bids were rejected.';
} else {
    $conn->rollback();
    $_SESSION['project_error'] = 'The bid could not be accepted.';
}

header('Location: ../frontend/ClientBids.php?id=' . (int) $bid['project_id']);
exit();
?>

client-project-actions

<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['email'])) {
    header('Location: ../frontend/index.php');
    exit();
}

$action = $_POST['action'] ?? '';
$projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);
$clientEmail = $_SESSION['email'];

if (!$projectId || $action !== 'delete') {
    header('Location: ../frontend/ClientBids.php');
    exit();
}

$ownership = $conn->prepare('SELECT project_id FROM projects WHERE project_id = ? AND client_email = ? LIMIT 1');
$ownership->bind_param('is', $projectId, $clientEmail);
$ownership->execute();

if ($ownership->get_result()->num_rows !== 1) {
    $_SESSION['project_error'] = 'Project not found or you do not own this project.';
    header('Location: ../frontend/ClientBids.php');
    exit();
}

if (isset($_POST['delete'])) {
$delete = $conn->prepare('DELETE FROM projects WHERE project_id = ? AND client_email = ?');
$delete->bind_param('is', $projectId, $clientEmail);
$deleteSucceeded = $delete->execute();
$_SESSION[$deleteSucceeded ? 'project_success' : 'project_error'] = $deleteSucceeded
    ? 'Project deleted successfully.'
    : 'Project could not be deleted.';
header('Location: ../frontend/ClientBids.php');
exit();
}


?>
