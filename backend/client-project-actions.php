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

$delete = $conn->prepare('DELETE FROM projects WHERE project_id = ? AND client_email = ?');
$delete->bind_param('is', $projectId, $clientEmail);
$deleteSucceeded = $delete->execute();

$_SESSION[$deleteSucceeded ? 'project_success' : 'project_error'] = $deleteSucceeded
    ? 'Project deleted successfully.'
    : 'Project cant not be deleted.';

header('Location: ../frontend/ClientBids.php');
exit();
?>