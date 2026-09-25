<?php
session_start();
require_once 'config.php';

function isSocialUrl($url, $allowedHosts) {
    if ($url === '') {
    return true;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
    return false;
    }

$parts = parse_url($url);
$scheme = strtolower($parts['scheme'] ?? '');
$host = strtolower($parts['host'] ?? '');

return in_array($scheme, ['http', 'https'], true)
    && in_array($host, $allowedHosts, true);
}

if (empty($_SESSION['email'])) {
    header('Location: ../frontend/index.php');
    exit();
}

$email = $_SESSION['email'];

if (isset($_POST['update_profile'])) {
    $facebookUrl = trim($_POST['facebook_url'] ?? '');
    $instagramUrl = trim($_POST['instagram_url'] ?? '');

    $facebookHosts = ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com'];
    $instagramHosts = ['instagram.com', 'www.instagram.com'];

    if (!isSocialUrl($facebookUrl, $facebookHosts)) {
        $_SESSION['profile_error'] = 'The Facebook field must contain a Facebook URL.';
    } 
    elseif (!isSocialUrl($instagramUrl, $instagramHosts)) {
        $_SESSION['profile_error'] = 'The Instagram field must contain an Instagram URL.';
    } 
    else {
        $updateProfileQ = $conn->prepare("UPDATE users SET facebook_url = ?, instagram_url = ? WHERE email = ?");
    if (!$updateProfileQ) {
        $_SESSION['profile_error'] = 'Unable to prepare the profile update.';
    } 
    else {
        $updateProfileQ->bind_param('sss', $facebookUrl, $instagramUrl, $email);
    if (!$updateProfileQ->execute()) {
        $_SESSION['profile_error'] = 'Profile update failed. Please try again.';
    } 
    else {
        $_SESSION['profile_success'] = 'Profile updated successfully.';
    }
    }
}

header('Location: ../frontend/Clientprofile.php');
exit();
}

if (isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $userQ = $conn->prepare("SELECT password FROM users WHERE email = ?");
    $userQ->bind_param('s', $email);
    $userQ->execute();
    $user = $userQ->get_result()->fetch_assoc();

    if (!$user || !password_verify($currentPassword, $user['password'])) {
        $_SESSION['password_error'] = 'Current password is incorrect.';
    }
    elseif (strlen($newPassword) < 8) {
        $_SESSION['password_error'] = 'New password must be 8 or more characters.';
    }
    elseif ($newPassword !== $confirmPassword) {
        $_SESSION['password_error'] = 'New password and confirmation do not match.';
    }
    else {
        $newHashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $updatePasswordQ = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $updatePasswordQ->bind_param('ss', $newHashed, $email);
        
        if ($updatePasswordQ->execute()) {
            $_SESSION['password_success'] = 'Password updated successfully.';
        } else {
            $_SESSION['password_error'] = 'Password could not be updated. Please try again.';
        }
    }

header('Location: ../frontend/Clientprofile.php');
exit();
}

header('Location: ../frontend/Clientprofile.php');
exit();
?>