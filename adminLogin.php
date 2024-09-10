<?php
session_start();
include "./dbConnection.php";

$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_name = $_POST['username'];
    $user_password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, username, password,role FROM admin WHERE username = ?");
    $stmt->bind_param("s", $user_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
        if ($user_password === $data['password']) {
            $_SESSION['username'] = $user_name;
            $_SESSION['admin_id'] = $data['id'];
             $_SESSION['role'] = $data['role'];
            if (!empty($_POST['remember_me'])) {
                setcookie("user_login", $_POST["username"], time() + (24 * 60 * 60));
                setcookie("user_password", $_POST["password"], time() + (24 * 60 * 60));
            } else {
                if (isset($_COOKIE["user_login"])) {
                    setcookie("user_login", "", time() - 3600);
                }
                if (isset($_COOKIE["user_password"])) {
                    setcookie("user_password", "", time() - 3600);
                }
            }
            header("Location: adminDashboard.php");
            exit();
        } else {
            $error_message = 'Invalid password. Please try again.';
        }
    } else {
        $error_message = 'Invalid username address. Please try again.';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="../crud/css/adminLogin.css" rel="stylesheet" />
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
                <h2 class="mb-4">Management Login</h2>
                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                <form class="form" id="loginForm" action="" method="POST" novalidate>
                    <div class="inputBox mb-3 position-relative">
                        <input
                            type="text"
                            name="username"
                            id="username"
                            class="form-control bg-dark text-light border-0"
                            placeholder="Username"
                            value="<?php echo isset($_COOKIE["user_login"]) ? htmlspecialchars($_COOKIE["user_login"]) : ''; ?>"
                        />
                    </div>

                    <div class="inputBox mb-3 position-relative">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            class="form-control bg-dark text-light border-0"
                            placeholder="Password"
                            value="<?php echo isset($_COOKIE["user_password"]) ? htmlspecialchars($_COOKIE["user_password"]) : ''; ?>"
                        />
                    </div>

                    <div class="inputBox mb-3 d-flex justify-content-center align-items-center position-relative">
                        <input
                            type="checkbox"
                            name="remember_me"
                            id="rememberMe"
                            class="me-2"
                            <?php echo isset($_COOKIE["user_login"]) ? 'checked' : ''; ?>
                        />
                        <label for="rememberMe">Remember Me</label>
                    </div>

                    <div class="inputBox">
                        <input
                            type="submit"
                            class="btn btn-success w-100"
                            value="Login"
                        />
                    </div>
                </form>
            </div>
        </div>
    </section>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(event) {
            let isValid = true;

            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');

            usernameField.classList.remove('is-invalid');
            passwordField.classList.remove('is-invalid');

            if (!usernameField.value.trim()) {
                usernameField.classList.add('is-invalid');
                isValid = false;
            }

            if (!passwordField.value.trim()) {
                passwordField.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    </script>
</body>

</html>
