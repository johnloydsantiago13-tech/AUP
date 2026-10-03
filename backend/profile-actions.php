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
    if (($_GET['action'] ?? '') === 'profile_image') {
        http_response_code(401);
        exit();
    }

    header('Location: ../frontend/index.php');
    exit();
}

$email = $_SESSION['email'];

if (($_GET['action'] ?? '') === 'profile_image') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        header('Allow: GET');
        exit();
    }

    $query = $conn->prepare('SELECT profile_image FROM users WHERE email = ? LIMIT 1');
    if (!$query) {
        http_response_code(500);
        exit();
    }

    $query->bind_param('s', $email);
    if (!$query->execute()) {
        http_response_code(500);
        exit();
    }

    $imageData = $query->get_result()->fetch_assoc()['profile_image'] ?? null;
    if (!is_string($imageData) || $imageData === '') {
        http_response_code(404);
        exit();
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $imageInfo = @getimagesizefromstring($imageData);
    if ($imageInfo && in_array($imageInfo['mime'] ?? '', $allowedTypes, true)) {
        header('Content-Type: ' . $imageInfo['mime']);
        header('Content-Length: ' . strlen($imageData));
        header('Cache-Control: private, no-store, max-age=0');
        header('X-Content-Type-Options: nosniff');
        echo $imageData;
        exit();
    }

    if (preg_match('/\Auploads\/(profile-images|profiles)\/[a-f0-9]{32}\.(jpg|png|webp)\z/', $imageData, $legacyMatch)) {
        $legacyPath = __DIR__ . '/../frontend/' . $imageData;
        $realUploadDirectory = realpath(__DIR__ . '/../frontend/uploads/' . $legacyMatch[1]);
        $realImagePath = realpath($legacyPath);

        if ($realUploadDirectory && $realImagePath && str_starts_with($realImagePath, $realUploadDirectory . DIRECTORY_SEPARATOR)) {
            $legacyInfo = @getimagesize($realImagePath);
            if ($legacyInfo && in_array($legacyInfo['mime'] ?? '', $allowedTypes, true)) {
                header('Content-Type: ' . $legacyInfo['mime']);
                header('Content-Length: ' . filesize($realImagePath));
                header('Cache-Control: private, no-store, max-age=0');
                header('X-Content-Type-Options: nosniff');
                readfile($realImagePath);
                exit();
            }
        }
    }

    http_response_code(404);
    exit();
}

if (($_POST['action'] ?? '') === 'upload_profile_image') {
    $image = $_FILES['profile_image'] ?? null;

    if (!$image) {
        $_SESSION['profile_error'] = 'Choose an image to upload.';
    } elseif ($image['error'] === UPLOAD_ERR_INI_SIZE || $image['error'] === UPLOAD_ERR_FORM_SIZE || $image['size'] > 2 * 1024 * 1024) {
        $_SESSION['profile_error'] = 'Profile pictures must be 2 MB or smaller.';
    } elseif ($image['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['profile_error'] = 'Image upload failed. Please choose the image again.';
    } else {
        $imageInfo = getimagesize($image['tmp_name']);
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($image['tmp_name']);
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!$imageInfo || !isset($allowedTypes[$mimeType]) || ($imageInfo['mime'] ?? '') !== $mimeType) {
            $_SESSION['profile_error'] = 'Upload a valid PNG, JPG, or WebP image.';
        } else {
            $imageData = file_get_contents($image['tmp_name']);

            if ($imageData === false) {
                $_SESSION['profile_error'] = 'The selected image could not be read.';
            } else {
                $updateImage = $conn->prepare('UPDATE users SET profile_image = ? WHERE email = ?');
                if (!$updateImage) {
                    $_SESSION['profile_error'] = 'Profile picture storage is unavailable.';
                } else {
                    $blob = null;
                    $updateImage->bind_param('bs', $blob, $email);

                    if (!$updateImage->send_long_data(0, $imageData)) {
                        $_SESSION['profile_error'] = 'Profile picture could not be transferred to database storage.';
                    } elseif ($updateImage->execute()) {
                        $_SESSION['profile_success'] = 'Profile picture updated.';
                    } else {
                        $_SESSION['profile_error'] = 'Profile picture could not be saved. Please try again.';
                    }
                }
            }
        }
    }

    $profilePage = ($_SESSION['role'] ?? '') === 'Developer' ? '../frontend/Developer.php?page=profile' : '../frontend/Client.php?page=profile';
    header('Location: ' . $profilePage);
    exit();
}

if (($_POST['action'] ?? '') === 'remove_profile_image') {
    $removeImage = $conn->prepare('UPDATE users SET profile_image = NULL WHERE email = ?');
    $removeImage->bind_param('s', $email);

    if ($removeImage->execute()) {
        $_SESSION['profile_success'] = 'Profile picture removed.';
    } else {
        $_SESSION['profile_error'] = 'Profile picture could not be removed. Please try again.';
    }

    $profilePage = ($_SESSION['role'] ?? '') === 'Developer' ? '../frontend/Developer.php?page=profile' : '../frontend/Client.php?page=profile';
    header('Location: ' . $profilePage);
    exit();
}

if (isset($_POST['update_profile'])) {
    $displayName = trim($_POST['display_name'] ?? '');
    $facebookUrl = trim($_POST['facebook_url'] ?? '');
    $instagramUrl = trim($_POST['instagram_url'] ?? '');

    $facebookHosts = ['facebook.com', 'www.facebook.com', 'm.facebook.com', 'fb.com', 'www.fb.com'];
    $instagramHosts = ['instagram.com', 'www.instagram.com'];

    if ($displayName === '' || strlen($displayName) > 255) {
        $_SESSION['profile_error'] = 'Display name is required and must be 255 characters or fewer.';
    }
    elseif (!isSocialUrl($facebookUrl, $facebookHosts)) {
        $_SESSION['profile_error'] = 'The Facebook field must contain a Facebook URL.';
    } 
    elseif (!isSocialUrl($instagramUrl, $instagramHosts)) {
        $_SESSION['profile_error'] = 'The Instagram field must contain an Instagram URL.';
    } 
    else {
        $updateProfileQ = $conn->prepare("UPDATE users SET username = ?, facebook_url = ?, instagram_url = ? WHERE email = ?");
    if (!$updateProfileQ) {
        $_SESSION['profile_error'] = 'Unable to prepare the profile update.';
    } 
    else {
        $updateProfileQ->bind_param('ssss', $displayName, $facebookUrl, $instagramUrl, $email);
    if (!$updateProfileQ->execute()) {
        $_SESSION['profile_error'] = 'Profile update failed. Please try again.';
    } 
    else {
        $_SESSION['username'] = $displayName;
        $_SESSION['profile_success'] = 'Profile updated successfully.';
    }
    }
}

$profilePage = ($_SESSION['role'] ?? '') === 'Developer' ? '../frontend/Developer.php?page=profile' : '../frontend/Client.php?page=profile';
header('Location: ' . $profilePage);
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

$profilePage = ($_SESSION['role'] ?? '') === 'Developer' ? '../frontend/Developer.php?page=profile' : '../frontend/Client.php?page=profile';
header('Location: ' . $profilePage);
exit();
}

$profilePage = ($_SESSION['role'] ?? '') === 'Developer' ? '../frontend/Developer.php?page=profile' : '../frontend/Client.php?page=profile';
header('Location: ' . $profilePage);
exit();

?>