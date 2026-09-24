    <?php
        session_start();

        $error = [
            'login' => $_SESSION['log_error'] ?? '',
            'register' => $_SESSION['reg_error'] ?? '',
            'password' => $_SESSION['pass_error'] ?? '',
            'ys' => $_SESSION['ys_error'] ?? ''
        ];
        $activeForm = $_SESSION['active_form'] ?? 'login';

        unset($_SESSION['log_error'], $_SESSION['reg_error'], $_SESSION['pass_error'], $_SESSION['ys_error'], $_SESSION['active_form']);

        function showError($error) {
            return !empty($error) ? "<p class='error'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>" : '';
        }
        function showForm($formID, $activeForm) {
            return $formID === $activeForm ? 'active' : '';
        }
?>

<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AU Projects</title>
        <link rel="stylesheet" href="main.css">
    </head>
    <body>        
        <div class="container">
            <div class="form-box <?= showForm('login', $activeForm); ?>" id="loginBox">
                <h2>Login to AU Projects</h2>
                 <?= showError($error['login']); ?>
                <form action="../backend/login-reg.php" method="POST">
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit" name="login">Login</button>
                    <p>Don't have an account? <a href="#register" data-form="register">Register</a></p>
                </form>
            </div>

            <div class="form-box <?= showForm('register', $activeForm); ?>" id="registerBox">
                <form action="../backend/login-reg.php" method="POST">
                    <h2>Register</h2>
                     <?= showError($error['register']); ?>
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <input type="password" name="cpassword" placeholder="Confirm Password" required>
                    <?= showError($error['password']); ?>
                    
                    <select name="Role" required>
                        <option value="" disabled selected>--Select Role--</option>
                        <option value="Client">Client</option>
                        <option value="Developer">Developer</option>
                    </select>       
                    <input type="text" name="year" placeholder="year" required>
                    <input type="text" name="section" placeholder="section" required>
                     <?= showError($error['ys']); ?>
                
                    <button type="submit" name="register">Register</button>
                    <p>Already have an account? <a href="#login" data-form="login">Login</a></p>
                </form>
            </div>
        </div>

        <script src="script.js"></script>
    </body>
    </html>
     
