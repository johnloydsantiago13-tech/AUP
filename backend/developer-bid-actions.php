<?php
session_start();
require_once 'config.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
    || empty($_SESSION['email'])
    || ($_SESSION['role'] ?? '') !== 'Developer'
) {
    header('Location: ../frontend/index.php');
    exit();
}

$developerEmail = $_SESSION['email'];
$projectId = filter_var($_POST['project_id'] ?? null, FILTER_VALIDATE_INT);
$proposedPrice = filter_var($_POST['proposed_price'] ?? null, FILTER_VALIDATE_FLOAT);
$timeline = trim($_POST['timeline'] ?? '');
$message = trim($_POST['message'] ?? '');

if (
    !$projectId
    || $proposedPrice === false
    || $proposedPrice <= 0
    || $proposedPrice > 99999999.99
    || $timeline === ''
    || strlen($timeline) > 100
    || $message === ''
    || strlen($message) > 2000
) {
    $_SESSION['bid_error'] = 'Enter a valid price, timeline, and proposal message.';
    header('Location: ../frontend/Developer.php');
    exit();
}

$conn->begin_transaction();
$projectQuery = $conn->prepare("SELECT project_id FROM projects WHERE project_id = ? AND LOWER(status) = 'open' FOR UPDATE");
$projectQuery->bind_param('i', $projectId);
$projectQuery->execute();
$project = $projectQuery->get_result()->fetch_assoc();

if (!$project) {
    $conn->rollback();
    $_SESSION['bid_error'] = 'This project is no longer open for proposals.';
    header('Location: ../frontend/Developer.php');
    exit();
}

$existingBidQuery = $conn->prepare('SELECT bid_id FROM bids WHERE project_id = ? AND developer_email = ? LIMIT 1');
$existingBidQuery->bind_param('is', $projectId, $developerEmail);
$existingBidQuery->execute();

if ($existingBidQuery->get_result()->num_rows > 0) {
    $conn->rollback();
    $_SESSION['bid_error'] = 'You have already submitted a proposal for this project.';
    header('Location: ../frontend/Developer.php');
    exit();
}

$insertBid = $conn->prepare(
    "INSERT INTO bids (project_id, developer_email, proposed_price, timeline, message, status)
     VALUES (?, ?, ?, ?, ?, 'pending')"
);
$insertBid->bind_param('isdss', $projectId, $developerEmail, $proposedPrice, $timeline, $message);

if ($insertBid->execute()) {
    $conn->commit();
    $_SESSION['bid_success'] = 'Your proposal was sent to the client.';
} else {
    $conn->rollback();
    $_SESSION['bid_error'] = 'Your proposal could not be sent. Please try again.';
}

header('Location: ../frontend/Developer.php');
exit();
