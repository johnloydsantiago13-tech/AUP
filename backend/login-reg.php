<?php
session_start();
require_once 'config.php';

if (isset($_POST['register'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = ($_POST['Role'] === 'Developer') ? 'Developer' : 'Client';
    $year = preg_replace('/\s+/','',$_POST['year']);
    $section = preg_replace('/\s+/','',$_POST['section']);

    $checkEmailQ = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $checkEmailQ->bind_param('s', $email);
    $checkEmailQ->execute();
    $checkEmailQ->store_result();


    if ($checkEmailQ->num_rows > 0) {
        $_SESSION['reg_error'] = 'Email already exist.';    
        $_SESSION['active_form'] = 'register';
        }
        else if (!filter_var($email, FILTER_VALIDATE_EMAIL, FILTER_SANITIZE_EMAIL)) {
            $_SESSION['reg_error'] = 'Invalid email format.';
            $_SESSION['active_form'] = 'register';
        }
        else {
            if ($_POST['password'] !== $_POST['cpassword']) {
                $_SESSION['pass_error'] = 'Password do not match.';
                $_SESSION['active_form'] = 'register';

            } elseif (strlen($_POST['password']) < 8) {
                $_SESSION['pass_error'] = 'Password must be 8 or more characters.';
                $_SESSION['active_form'] = 'register';


            } elseif ($_POST['Role'] === 'Developer') {
    $checkyear = $conn->prepare("SELECT * FROM ys WHERE year = ?");
    $checkyear->bind_param('s', $year);
    $checkyear->execute();
    $checkyear->store_result();

    $checksection = $conn->prepare("SELECT * FROM ys WHERE section = ?");
    $checksection->bind_param('s', $section);
    $checksection->execute();
    $checksection->store_result();

    if($checkyear->num_rows > 0 && $checksection->num_rows > 0) {

        $insertQ = $conn->prepare("INSERT INTO users (username,email,password,role) VALUES (?,?,?,?)");
        $insertQ->bind_param('ssss',$username,$email,$password,$role);
        $insertQ->execute();

        if($insertQ->affected_rows !== 1) {
            $_SESSION['reg_error'] = 'Registration failed. Please try again.';
            $_SESSION['active_form'] = 'register';
        }
    }
    else {
        $_SESSION['ys_error'] = 'SINO KABA? DIKA AU BOI';
        $_SESSION['active_form'] = 'register';
    }
} 

    else {
        $insertQ = $conn->prepare("INSERT INTO users (username,email,password,role) VALUES (?,?,?,?)");
        $insertQ->bind_param('ssss',$username,$email,$password,$role);
        $insertQ->execute();

        if($insertQ->affected_rows !== 1) {
            $_SESSION['reg_error'] = 'Registration failed. Please try again.';
            $_SESSION['active_form'] = 'register';
        }
    }

}
        
    header('Location: ../frontend/index.php');
    exit();
}
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $checkUserQ = $conn->prepare("SELECT username, email, password, role FROM users WHERE email = ?");
    $checkUserQ->bind_param('s', $email);
    $checkUserQ->execute();
    $result = $checkUserQ->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            if ($user['role'] === 'Client') {
                header("Location: ../frontend/Client.php");
            } else if ($user['role'] === 'Developer') {
                header("Location: ../frontend/Developer.php");
            }
            exit();
        }
    }

    $_SESSION['log_error'] = "Invalid email or password.";
    $_SESSION['active_form'] = 'login';
    header("Location: ../frontend/index.php");
    exit();
}
?>