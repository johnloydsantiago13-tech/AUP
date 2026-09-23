<?php
session_start();
require_once 'config.php';

if (empty($_SESSION['email']) || ($_SESSION['role'] ?? '') !== 'Client') {
    header('Location: ../frontend/index.php');
    exit();
}

if (isset($_POST['create_project'])) {
    $clientEmail = $_SESSION['email'];
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $budget = filter_var($_POST['budget'] ?? '', FILTER_VALIDATE_FLOAT);
    $deadline = $_POST['deadline'] ?? '';

    if ($title === '' || $category === '' || $description === '' || $budget === false || $budget <= 0 || $deadline === '') {
        $_SESSION['project_error'] = 'Please complete all project fields correctly.';
    } elseif ($deadline < date('Y-m-d')) {
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
                $_SESSION['project_error'] = 'Project could not be posted. Please try again.';
            } else {
                $_SESSION['project_success'] = 'Project posted successfully.';
            }
        }
    }
}

header('Location: ../frontend/ClientPostProject.php');
exit();
?>
