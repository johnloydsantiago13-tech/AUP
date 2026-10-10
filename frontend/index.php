    <?php
    session_start();

    if (!empty($_SESSION['email'])) {
        unset($_SESSION['email'], $_SESSION['username'], $_SESSION['role']);
        session_regenerate_id(true);
    }

    $error = [
        'login' => $_SESSION['log_error'] ?? '',
        'register' => $_SESSION['reg_error'] ?? '',
        'password' => $_SESSION['pass_error'] ?? '',
        'ys' => $_SESSION['ys_error'] ?? ''
    ];
    $activeForm = $_SESSION['active_form'] ?? 'login';

    unset($_SESSION['log_error'], $_SESSION['reg_error'], $_SESSION['pass_error'], $_SESSION['ys_error'], $_SESSION['active_form']);

    function showError($error)
    {
        return !empty($error) ? "<p class='error' role='alert'>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</p>" : '';
    }
    function showForm($formID, $activeForm)
    {
        return $formID === $activeForm ? 'active' : '';
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>AU Projects | Sign in</title>
        <link rel="stylesheet" href="main.css">
    </head>

    <body>
        <nav class="site-nav" aria-label="Main navigation">
            <a class="logo-group" aria-label="AU Projects">
                    <span class="logo-box" aria-hidden="true">AUP</span>
                <span class="logo-name">AU Projects</span>
            </a>
        </nav>

        <main class="auth-layout">
            <section class="welcome-panel" aria-labelledby="welcome-title">
                <div class="welcome-copy">
                    <span class="eyebrow">PLAN. BUILD. COLLABORATE.</span>
                    <h1 id="welcome-title">Good ideas grow better together.</h1>
                    <p>A shared space for clients and developers to connect, organize projects, and move work forward.</p>
                    <div class="welcome-note">
                        <span class="welcome-mark" aria-hidden="true">+</span>
                        <span>Start your next project with AU Projects.</span>
                    </div>
                </div>
                <div class="panel-decoration" aria-hidden="true">
                    <span class="decoration-orbit decoration-orbit-one"></span>
                    <span class="decoration-orbit decoration-orbit-two"></span>
                </div>
            </section>

            <section class="auth-panel" aria-label="Account access">
                <div class="container">
                    <div class="form-box <?= showForm('login', $activeForm); ?>" id="loginBox">
                        <div class="form-heading">
                            <h1>Sign</h1>
                        </div>
                        <?= showError($error['login']); ?>
                        <form class="auth-form" action="../backend/login-reg.php" method="POST">
                            <div class="field">
                                <label for="login-email">Email address</label>
                                <input id="login-email" type="email" name="email" placeholder="Email" autocomplete="email" required>
                            </div>
                            <div class="field">
                                <label for="login-password">Password</label>
                                <input id="login-password" type="password" name="password" placeholder="Password" autocomplete="current-password" required>
                            </div>
                            <button type="submit" name="login">Sign in</button>
                            <p class="form-switch">New to AU Projects? <a href="#register" data-form="register">Register</a></p>
                        </form>
                    </div>

                    <div class="form-box <?= showForm('register', $activeForm); ?>" id="registerBox">
                        <div class="form-heading">
                            <h1>Register</h1>
                        </div>
                        <?= showError($error['register']); ?>
                        <form class="auth-form register-form" action="../backend/login-reg.php" method="POST">
                            <div class="field">
                                <label for="register-username">Username</label>
                                <input id="register-username" type="text" name="username" placeholder="Username" autocomplete="username" required>
                            </div>
                            <div class="field">
                                <label for="register-email">Email address</label>
                                <input id="register-email" type="email" name="email" placeholder="Email" autocomplete="email" required>
                            </div>
                            <div class="field">
                                <label for="register-password">Password</label>
                                <input id="register-password" type="password" name="password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required>
                            </div>
                            <?= showError($error['password']); ?>
                            <div class="field">
                                <label for="register-role">Account type</label>
                                <select id="register-role" name="Role" required>
                                    <option value="" disabled selected>Select your role</option>
                                    <option value="Client">Client</option>
                                    <option value="Developer">Developer</option>
                                </select>
                            </div>
                            <div class="field-row">
                                <div class="field">
                                    <label for="register-year">Year</label>
                                    <input id="register-year" type="text" name="year" placeholder="Year" required>
                                </div>
                                <div class="field">
                                    <label for="register-section">Section</label>
                                    <input id="register-section" type="text" name="section" placeholder="Section" required>
                                </div>
                            </div>
                            <?= showError($error['ys']); ?>
                            <button type="submit" name="register">Register</button>
                            <p class="form-switch">Already have an account? <a href="#login" data-form="login">Login</a></p>
                        </form>
                    </div>
                </div>
            </section>
        </main>
        <script src="script.js"></script>
    </body>

    </html>