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

if (!$projectId || !in_array($action, ['delete', 'complete'], true)) {
    header('Location: ../frontend/Client.php?page=projects');
    exit();
}

$ownership = $conn->prepare('SELECT project_id, status FROM projects WHERE project_id = ? AND client_email = ? LIMIT 1');
$ownership->bind_param('is', $projectId, $clientEmail);
$ownership->execute();

$ownedProject = $ownership->get_result()->fetch_assoc();
if (!$ownedProject) {
    $_SESSION['project_error'] = 'Project not found or you do not own this project.';
    header('Location: ../frontend/Client.php?page=projects');
    exit();
}

if ($action === 'complete') {
    if (strtolower($ownedProject['status']) !== 'in progress') {
        $_SESSION['project_error'] = 'Only an active project can be marked complete.';
    } else {
        $complete = $conn->prepare(
            "UPDATE projects SET status = 'Completed'
             WHERE project_id = ? AND client_email = ? AND status = 'In Progress'"
        );
        $complete->bind_param('is', $projectId, $clientEmail);

        if ($complete->execute() && $complete->affected_rows === 1) {
            $_SESSION['project_success'] = 'Project marked as completed.';
        } else {
            $_SESSION['project_error'] = 'The project could not be marked complete.';
        }
    }

    header('Location: ../frontend/Client.php?page=projects');
    exit();
}

$transactionStarted = false;
try {
    $lockProject = $conn->prepare(
        'SELECT project_id FROM projects WHERE project_id = ? AND client_email = ? LIMIT 1 FOR UPDATE'
    );
    $deleteBids = $conn->prepare('DELETE FROM bids WHERE project_id = ?');
    $deleteProject = $conn->prepare('DELETE FROM projects WHERE project_id = ? AND client_email = ?');

    if (!$lockProject || !$deleteBids || !$deleteProject) {
        throw new RuntimeException('Could not prepare project deletion statements.');
    }

    $conn->begin_transaction();
    $transactionStarted = true;

    $lockProject->bind_param('is', $projectId, $clientEmail);
    if (!$lockProject->execute() || !$lockProject->get_result()->fetch_assoc()) {
        $conn->rollback();
        $_SESSION['project_error'] = 'Project not found or you do not own this project.';
        header('Location: ../frontend/Client.php?page=projects');
        exit();
    }

    $deleteBids->bind_param('i', $projectId);
    if (!$deleteBids->execute()) {
        throw new RuntimeException('Could not delete bids for project.');
    }

    $deleteProject->bind_param('is', $projectId, $clientEmail);
    if (!$deleteProject->execute() || $deleteProject->affected_rows !== 1) {
        throw new RuntimeException('Could not delete owned project.');
    }

    $conn->commit();
    $transactionStarted = false;
    $_SESSION['project_success'] = 'Project and its bids deleted successfully.';
} catch (RuntimeException $error) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (mysqli_sql_exception $rollbackError) {
            error_log('Project deletion rollback failed: ' . $rollbackError->getMessage());
        }
    }
    error_log('Project deletion failed: ' . $error->getMessage());
    $_SESSION['project_error'] = 'The project could not be deleted. Its bids and project details were not changed.';
}

header('Location: ../frontend/Client.php?page=projects');
exit();
?>