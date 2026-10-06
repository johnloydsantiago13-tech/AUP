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
$socialQuery = $conn->prepare(
    "SELECT facebook_url, instagram_url FROM users WHERE email = ? AND role = 'Developer' LIMIT 1"
);
if (!$socialQuery) {
    $_SESSION['bid_error'] = 'Unable to verify your profile details. Please try again.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}
$socialQuery->bind_param('s', $developerEmail);
if (!$socialQuery->execute()) {
    $_SESSION['bid_error'] = 'Unable to verify your profile details. Please try again.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}
$socialLinks = $socialQuery->get_result()->fetch_assoc();

if (!$socialLinks || (trim($socialLinks['facebook_url'] ?? '') === '' && trim($socialLinks['instagram_url'] ?? '') === '')) {
    $_SESSION['bid_error'] = 'Add a Facebook or Instagram link to your profile before submitting a proposal.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}

$projectIdInput = $_POST['project_id'] ?? null;
$proposedPriceInput = $_POST['proposed_price'] ?? null;
$timelineInput = $_POST['timeline'] ?? null;
$messageInput = $_POST['message'] ?? null;
if (
    !is_string($projectIdInput)
    || !is_string($proposedPriceInput)
    || !is_string($timelineInput)
    || !is_string($messageInput)
) {
    $_SESSION['bid_error'] = 'Enter a valid price, timeline, and proposal message.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}

$projectId = filter_var($projectIdInput, FILTER_VALIDATE_INT);
$proposedPrice = filter_var($proposedPriceInput, FILTER_VALIDATE_FLOAT);
$timeline = trim($timelineInput);
if (preg_match('/\A\d+\z/', $timeline)) {
    $timelineDays = (int) $timeline;
    if ($timelineDays > 0) {
        $timeline = $timelineDays . ($timelineDays === 1 ? ' Day' : ' Days');
    }
}
$message = trim($messageInput);

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
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}

$conn->begin_transaction();
$projectQuery = $conn->prepare("SELECT project_id FROM projects WHERE project_id = ? AND LOWER(status) = 'open' FOR UPDATE");
if (!$projectQuery) {
    $conn->rollback();
    $_SESSION['bid_error'] = 'Unable to verify this project. Please try again.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}
$projectQuery->bind_param('i', $projectId);
if (!$projectQuery->execute()) {
    $conn->rollback();
    $_SESSION['bid_error'] = 'Unable to verify this project. Please try again.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}
$project = $projectQuery->get_result()->fetch_assoc();

if (!$project) {
    $conn->rollback();
    $_SESSION['bid_error'] = 'This project is no longer open for proposals.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}

$existingBidQuery = $conn->prepare('SELECT bid_id FROM bids WHERE project_id = ? AND developer_email = ? LIMIT 1');
if (!$existingBidQuery) {
    $conn->rollback();
    $_SESSION['bid_error'] = 'Unable to verify existing proposals. Please try again.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}
$existingBidQuery->bind_param('is', $projectId, $developerEmail);
if (!$existingBidQuery->execute()) {
    $conn->rollback();
    $_SESSION['bid_error'] = 'Unable to verify existing proposals. Please try again.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}

if ($existingBidQuery->get_result()->num_rows > 0) {
    $conn->rollback();
    $_SESSION['bid_error'] = 'You have already submitted a proposal for this project.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}

$insertBid = $conn->prepare(
    "INSERT INTO bids (project_id, developer_email, proposed_price, timeline, message, status)
     VALUES (?, ?, ?, ?, ?, 'pending')"
);
if (!$insertBid) {
    $conn->rollback();
    $_SESSION['bid_error'] = 'Your proposal could not be prepared. Please try again.';
    header('Location: ../frontend/Developer.php?page=browse');
    exit();
}
$insertBid->bind_param('isdss', $projectId, $developerEmail, $proposedPrice, $timeline, $message);

if ($insertBid->execute()) {
    $conn->commit();
    $_SESSION['bid_success'] = 'Your proposal was sent to the client.';
} else {
    $conn->rollback();
    $_SESSION['bid_error'] = 'Your proposal could not be sent. Please try again.';
}

header('Location: ../frontend/Developer.php?page=browse');
exit();
