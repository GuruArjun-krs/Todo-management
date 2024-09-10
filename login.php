<?php
session_start();
include "./dbConnection.php";

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_email = trim($_POST['email']);
    $user_password = trim($_POST['password']);
    $encrypt = md5($user_password);

    $stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
        if ($data['status'] !== 'verified') {
            $error_message = 'Account not verified. Please verify your account.';
        } elseif ($encrypt === $data['password']) {
            $_SESSION['user_email'] = $user_email;
            $_SESSION['user_id'] = $data['id'];

            if (!empty($_POST['remember_me'])) {
                setcookie("user_login", $user_email, time() + (24 * 60 * 60), "/");
                setcookie("user_password", $user_password, time() + (24 * 60 * 60), "/");
            } else {
                if (isset($_COOKIE["user_login"])) {
                    setcookie("user_login", "", time() - 3600, "/");
                }
                if (isset($_COOKIE["user_password"])) {
                    setcookie("user_password", "", time() - 3600, "/");
                }
            }

            header("Location: home.php");
            exit();
        } else {
            $error_message = 'Invalid password. Please try again.';
        }
    } else {
        $error_message = 'Invalid email address. Please try again.';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="./css/login.css" />
    <style>
        body {
            background-image: url(./asserts/background.avif);
            background-size: cover;
            background-repeat: no-repeat;
        }
    </style>
</head>

<body>
    <section class="d-flex justify-content-center align-items-center vh-100">
        <div class="signin text-light p-4 rounded shadow">
            <div class="content text-center">
                <h2 class="mb-4">LogIn</h2>
                <form class="form" id="loginForm" action="" method="POST" novalidate>
                    <div class="inputBox mb-3 position-relative">
                        <input type="email" name="email" id="email" class="form-control bg-dark text-light border-0" placeholder="Email" value="<?php if (isset($_COOKIE["user_login"])) { echo htmlspecialchars($_COOKIE["user_login"]); } ?>" required />
                    </div>

                    <div class="inputBox mb-3 position-relative">
                        <input type="password" name="password" id="password" class="form-control bg-dark text-light border-0" placeholder="Password" value="<?php if (isset($_COOKIE["user_password"])) { echo htmlspecialchars($_COOKIE["user_password"]); } ?>" required />
                    </div>

                    <div class="inputBox mb-3 d-flex justify-content-center align-items-center position-relative">
                        <input type="checkbox" name="remember_me" id="rememberMe" class="me-2" <?php if (isset($_COOKIE["user_login"])) { echo "checked"; } ?> />
                        <label for="rememberMe">Remember Me</label>
                    </div>

                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <div class="links mb-3">
                        <span>Don't have an account?
                            <a href="./register.php" class="text-success text-decoration-none">Signup</a></span>
                    </div>

                    <div class="inputBox">
                        <input type="submit" class="btn btn-success w-100" value="Login" />
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(event) {
            let isValid = true;

            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');

            emailField.classList.remove('is-invalid');
            passwordField.classList.remove('is-invalid');

            if (!emailField.value.trim()) {
                emailField.classList.add('is-invalid');
                isValid = false;
            }

            if (!passwordField.value.trim()) {
                passwordField.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
            }
        });
    </script>
</body>

</html>
