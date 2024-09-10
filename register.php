<?php
session_start();
include "./dbConnection.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $country = $_POST['country'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $termsAndConditions = isset($_POST['terms_and_conditions']) ? 1 : 0;
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (isset($_POST['languages_known']) && is_array($_POST['languages_known'])) {
        $languagesKnown = implode(", ", $_POST['languages_known']);
    } else {
        $languagesKnown = '';
    }

    $hashedPassword = md5($password);

    $profileImage = '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['logo']['tmp_name'];
        $fileName = $_FILES['logo']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedExts = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($fileExtension, $allowedExts)) {
            $uploadDir = 'images/';
            $destPath = $uploadDir . uniqid() . '.' . $fileExtension;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $profileImage = $destPath;
            } else {
                $_SESSION['error'] = "Error moving the uploaded file.";
                header("Location: register.php");
                exit;
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Only JPG, JPEG, PNG, and GIF are allowed.";
            header("Location: register.php");
            exit;
        }
    }

    $checkQuery = "SELECT * FROM user WHERE email = ?";
    $stmt = $conn->prepare($checkQuery);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Email already exists.";
        echo print_r($_SESSION['error']);
        $stmt->close();
        $conn->close();
        header("Location: register.php");
        exit;
    }

    $stmt->close();

    $query = "INSERT INTO user (first_name, last_name, dob, country, gender, languages_known, terms_and_conditions, email, password, profile, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param('ssssssssss', $firstName, $lastName, $dob, $country, $gender, $languagesKnown, $termsAndConditions, $email, $hashedPassword, $profileImage);
        if ($stmt->execute()) {
            $lastId = $conn->insert_id;
            $_SESSION['user_id'] = $lastId;

            $otp = rand(100000, 999999);
            $otpExpiry = date('Y-m-d H:i:s', time() + 300);

            $otpQuery = "INSERT INTO otp (user_id, otp, otp_expiry) VALUES (?, ?, ?)";
            $otpStmt = $conn->prepare($otpQuery);

            if ($otpStmt) {
                $otpStmt->bind_param('iss', $lastId, $otp, $otpExpiry);
                $otpStmt->execute();
                $otpStmt->close();

                $mail = new PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['MAIL_HOST'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['MAIL_USERNAME'];
                    $mail->Password   = $_ENV['MAIL_PASSWORD'];
                    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
                    $mail->Port       = $_ENV['MAIL_PORT'];

                    $mail->setFrom('no-reply@example.com', 'Coderz');
                    $mail->addAddress($email);

                    $mail->isHTML(true);
                    $mail->Subject = 'Your OTP Code';
                    $mail->Body    = "Your OTP code is: <strong>$otp</strong>";
                    $mail->AltBody = "Your OTP code is: $otp";

                    $mail->send();
                    $_SESSION['success'] = "Registration successful. An OTP has been sent to your email.";
                    header("Location: verify_otp.php");
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = "Error sending OTP: {$mail->ErrorInfo}";
                }
            } else {
                $_SESSION['error'] = "Error preparing the OTP statement: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Error preparing the statement: " . $conn->error;
    }

    $conn->close();
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register Form</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
        crossorigin="anonymous" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="./css/register.css">
    <style>
        .profile-picture-container {
            position: relative;
            width: 100px;
            height: 100px;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 50%;
            overflow: hidden;
            border: 2px solid #ddd;
        }

        .profile-picture-container input[type="file"] {
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
            position: absolute;
        }

        .profile-picture-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            cursor: pointer;
        }

        .form-container {
            max-width: 100%;
            width: 100%;
            max-width: 500px;
        }

        body {
            background-image: url(./asserts/background.avif);
            background-size: cover;
            background-repeat: no-repeat;
        }
    </style>
    <script>
        function validateForm() {
            let valid = true;
            const errorElements = document.querySelectorAll(".error-message");
            errorElements.forEach((el) => el.remove());

            const fields = [{
                    id: "email",
                    message: "",
                    validate: validateEmail
                },
                {
                    id: "password",
                    message: ""
                }
            ];

            fields.forEach((field) => {
                const value = document.getElementById(field.id).value;
                if (value === "") {
                    displayError(field.id, field.message);
                    valid = false;
                } else if (field.validate && !field.validate(value)) {
                    displayError(field.id, "Please enter a valid value");
                    valid = false;
                }
            });

            const termsAndConditions = document.getElementById("terms_and_conditions");
            if (!termsAndConditions.checked) {
                displayError("terms_and_conditions", "");
                valid = false;
            }

            return valid;
        }

        function displayError(fieldId, message) {
            const field = document.getElementById(fieldId);
            if (field) {
                field.classList.add("is-invalid");

                const errorElement = document.createElement("div");
                errorElement.className = "invalid-feedback";
                errorElement.innerText = message;

                if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('invalid-feedback')) {
                    field.parentNode.appendChild(errorElement);
                }
            }
        }



        function validateEmail(email) {
            const re =
                /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@(([^<>()[\]\.,;:\s@"]+\.)+[^<>()[\]\.,;:\s@"]{2,})$/i;
            return re.test(String(email).toLowerCase());
        }

        function previewImage(event) {
            const file = event.target.files[0];
            const reader = new FileReader();

            reader.onload = function() {
                const output = document.getElementById("logoPreview");
                output.src = reader.result;
                output.style.display = "block";
            };

            if (file && file.type.match("image.*")) {
                reader.readAsDataURL(file);
            } else {
                alert("Please select a valid image file (JPG, PNG, GIF).");
                event.target.value = "";
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
            <?php if (isset($_SESSION['error']) || isset($_SESSION['success'])): ?>
                messageModal.show();
            <?php endif; ?>
        });
    </script>
</head>

<body>
    <div class="d-flex justify-content-center align-items-center vh-100">
        <div class=" signin form-container text-light p-4 rounded shadow" style="padding-left: 40px; padding-right:40px; ">

            <h2 class="">Register Form</h2>
            <form
                method="post"
                action="register.php"
                enctype="multipart/form-data"
                onsubmit="return validateForm()">
                <div class="row mt-3">
                    <div class="col-md-6 d-flex flex-column justify-content-evenly">
                        <input
                            type="text"
                            class="form-control bg-dark  text-light border-0 mb-2"
                            id="first_name"
                            placeholder="First Name"
                            name="first_name" />

                        <input
                            type="text"
                            class="form-control bg-dark  text-light border-0"
                            id="last_name"
                            placeholder="Last Name"
                            name="last_name" />
                    </div>
                    <div
                        class="col-md-6 d-flex justify-content-center align-items-center">
                        <div class="profile-picture-container">
                            <input
                                type="file"
                                id="logo"
                                name="logo"
                                onchange="previewImage(event)" />
                            <img
                                id="logoPreview"
                                src="../img/defaultLogo.jpg"
                                alt="Profile Preview" />
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <input
                        type="email"
                        class="form-control bg-dark  text-light border-0"
                        id="email"
                        placeholder="E-mail"
                        name="email" />
                </div>

                <div class="mt-3">
                    <input type="date" class="form-control bg-dark  text-light border-0" id="dob" name="dob">
                </div>

                <div class="mt-3 d-flex justify-content-between">
                    <label class="form-label">Gender:</label>
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="radio"
                            id="female"
                            name="gender"
                            value="Female" />
                        <label class="form-check-label" for="female">Female</label>
                    </div>
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="radio"
                            id="male"
                            name="gender"
                            value="Male" />
                        <label class="form-check-label" for="male">Male</label>
                    </div>
                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="radio"
                            id="other"
                            name="gender"
                            value="other" />
                        <label class="form-check-label" for="other">Other</label>
                    </div>
                </div>
                <div class="mt-3">
                    <select class="form-select bg-dark text-light border-0" id="country" name="country">
                        <option value="India">India</option>
                        <option value="USA">USA</option>
                        <option value="UK">UK</option>
                        <option value="Canada">Canada</option>
                    </select>
                </div>
                <div class="mt-3">
                    <label class="form-label">Languages Known:</label>
                    <div class="d-flex justify-content-between">
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="english"
                                name="languages_known[]"
                                value="English" />
                            <label class="form-check-label" for="english">English</label>
                        </div>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="tamil"
                                name="languages_known[]"
                                value="Tamil" />
                            <label class="form-check-label" for="tamil">Tamil</label>
                        </div>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="telugu"
                                name="languages_known[]"
                                value="Telugu" />
                            <label class="form-check-label" for="telugu">Telugu</label>
                        </div>
                        <div class="form-check">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                id="malayalam"
                                name="languages_known[]"
                                value="Malayalam" />
                            <label class="form-check-label" for="malayalam">Malayalam</label>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <input
                        type="password"
                        placeholder="Password"
                        class="form-control bg-dark text-light border-0"
                        id="password"
                        name="password" />
                </div>

                <div class="form-check mt-3">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        id="terms_and_conditions"
                        name="terms_and_conditions"
                        value="terms_and_conditions" />
                    <label class="form-check-label" for="terms_and_conditions">Agree terms and conditions</label>
                </div>
                <button
                    type="submit"
                    class="btn btn-success text-uppercase mt-4 w-100 mb-3">
                    Register
                </button>

                <div class="links mb-3 text-center">
                    <span>Already have an account?
                        <a href="./login.php" class="text-success text-decoration-none">Login</a>
                    </span>
                </div>
            </form>
        </div>

        <div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="messageModalLabel">Message</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php
                        if (isset($_SESSION['error'])) {
                            echo "<div class='alert alert-danger' role='alert'>{$_SESSION['error']}</div>";
                            unset($_SESSION['error']);
                        }
                        if (isset($_SESSION['success'])) {
                            echo "<div class='alert alert-success' role='alert'>{$_SESSION['success']}</div>";
                            unset($_SESSION['success']);
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>