<?php
session_start();
require_once 'config.php';
$projectOptions = require __DIR__ . '/project-options.php';

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Client') {
    header('Location: ../frontend/index.php');
    exit();
}

if (isset($_POST['create_project'])) {
    $clientEmail = $_SESSION['email'];
    $socialQuery = $conn->prepare(
        "SELECT facebook_url, instagram_url FROM users WHERE email = ? AND role = 'Client' LIMIT 1"
    );
    if (!$socialQuery) {
        $_SESSION['project_error'] = 'Unable to verify your profile details. Please try again.';
        header('Location: ../frontend/Client.php?page=post-project');
        exit();
    }
    $socialQuery->bind_param('s', $clientEmail);
    if (!$socialQuery->execute()) {
        $_SESSION['project_error'] = 'Unable to verify your profile details. Please try again.';
        header('Location: ../frontend/Client.php?page=post-project');
        exit();
    }
    $socialLinks = $socialQuery->get_result()->fetch_assoc();

    if (!$socialLinks || (trim($socialLinks['facebook_url'] ?? '') === '' && trim($socialLinks['instagram_url'] ?? '') === '')) {
        $_SESSION['project_error'] = 'Add a Facebook or Instagram link to your profile before posting a project.';
        header('Location: ../frontend/Client.php?page=post-project');
        exit();
    }

    $titleInput = $_POST['title'] ?? '';
    $categoryInput = $_POST['category'] ?? '';
    $descriptionInput = $_POST['description'] ?? '';
    $budgetInput = $_POST['budget'] ?? '';
    $deadlineInput = $_POST['deadline'] ?? '';

    if (
        !is_string($titleInput)
        || !is_string($categoryInput)
        || !is_string($descriptionInput)
        || !is_string($budgetInput)
        || !is_string($deadlineInput)
    ) {
        $_SESSION['project_error'] = 'Please complete all project fields correctly.';
        header('Location: ../frontend/Client.php?page=post-project');
        exit();
    }

    $title = trim($titleInput);
    $category = trim($categoryInput);
    $description = trim($descriptionInput);
    $budget = filter_var($budgetInput, FILTER_VALIDATE_FLOAT);
    $deadline = trim($deadlineInput);
    $deadlineDate = DateTimeImmutable::createFromFormat('!Y-m-d', $deadline);

    if (
        $title === ''
        || !in_array($category, $projectOptions['categories'], true)
        || $description === ''
        || $budget === false
        || $budget <= 0
        || !$deadlineDate
        || $deadlineDate->format('Y-m-d') !== $deadline
    ) {
        $_SESSION['project_error'] = 'Please complete all project fields correctly.';
    } elseif ($deadlineDate < new DateTimeImmutable('today')) {
        $_SESSION['project_error'] = 'The deadline cannot be in the past.';
    } else {
        $createProjectQ = $conn->prepare(
            "INSERT INTO projects (client_email, title, category, description, budget, deadline)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        if (!$createProjectQ) {
            $_SESSION['project_error'] = 'Unable to prepare the project post.';
        } else {
            $createProjectQ->bind_param(
                'ssssds',
                $clientEmail,
                $title,
                $category,
                $description,
                $budget,
                $deadline
            );

            if (!$createProjectQ->execute()) {
                $_SESSION['project_error'] = 'Please try again.';
            } else {
                $_SESSION['project_success'] = 'Project posted successfully.';
            }
        }
    }
}

header('Location: ../frontend/Client.php?page=projects');
exit();
?>
