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

if (isset($_POST['update_profile'])) {
    $email = $_SESSION['email'];
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
}

header('Location: ../frontend/Clientprofile.php');
exit();
?>