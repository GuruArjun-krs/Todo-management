<?php
session_start();
include "./dbConnection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: unauthorised.php");
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$otpError = $successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['verify_otp'])) {
        $userOtp = trim($_POST['otp'] ?? '');
        $userId = $_SESSION['user_id'] ?? '';

        if (empty($userOtp)) {
            $otpError = "Please enter the OTP.";
        } else {
            $query = "SELECT otp, otp_expiry FROM otp WHERE user_id = ?";
            $stmt = $conn->prepare($query);

            if ($stmt) {
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $stmt->bind_result($storedOtp, $otpExpiry);
                    $stmt->fetch();

                    if (time() > strtotime($otpExpiry)) {
                        $otpError = "OTP has expired. Please request a new OTP.";
                    } elseif ($userOtp === $storedOtp) {
                        $updateQuery = "UPDATE user SET status = 'verified' WHERE id = ?";
                        $updateStmt = $conn->prepare($updateQuery);

                        if ($updateStmt) {
                            $updateStmt->bind_param('i', $userId);
                            if ($updateStmt->execute()) {
                                $successMessage = "Your account has been verified successfully.";
                                unset($_SESSION['user_id']);
                                header("Location: login.php");
                                exit;
                            } else {
                                $otpError = "Error updating user status.";
                            }
                            $updateStmt->close();
                        } else {
                            $otpError = "Error preparing the statement: " . $conn->error;
                        }
                    } else {
                        $otpError = "Invalid OTP. Please try again.";
                    }
                } else {
                    $otpError = "User not found.";
                }
                $stmt->close();
            } else {
                $otpError = "Error preparing the statement: " . $conn->error;
            }
        }
    }

    if (isset($_POST['resend_otp'])) {
        $userId = $_SESSION['user_id'] ?? '';

        function generateOtp()
        {
            return mt_rand(100000, 999999);
        }

        if (!empty($userId)) {
            $newOtp = generateOtp();
            $otpExpiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

            $updateQuery = "UPDATE otp SET otp = ?, otp_expiry = ? WHERE user_id = ?";
            $updateStmt = $conn->prepare($updateQuery);

            if ($updateStmt) {
                $updateStmt->bind_param('ssi', $newOtp, $otpExpiry, $userId);
                if ($updateStmt->execute()) {
                    $selectQuery = "SELECT email FROM user WHERE id = ?";
                    $selectStmt = $conn->prepare($selectQuery);

                    if ($selectStmt) {
                        $selectStmt->bind_param('i', $userId);
                        $selectStmt->execute();
                        $selectStmt->bind_result($userEmail);
                        $selectStmt->fetch();
                        $selectStmt->close();

                        $mail = new PHPMailer(true);

                        try {
                            $mail->isSMTP();
                            $mail->Host       = $_ENV['MAIL_HOST'];
                            $mail->SMTPAuth   = true;
                            $mail->Username   = $_ENV['MAIL_USERNAME'];
                            $mail->Password   = $_ENV['MAIL_PASSWORD'];
                            $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
                            $mail->Port       = $_ENV['MAIL_PORT'];

                            $mail->setFrom($_ENV['MAIL_USERNAME'], 'Coderz');
                            $mail->addAddress($userEmail);

                            $mail->isHTML(true);
                            $mail->Subject = 'Your OTP Code';
                            $mail->Body    = "Your OTP code is <strong>$newOtp</strong>. It will expire in 5 minutes.";

                            $mail->send();
                            $successMessage = "OTP has been resent to your email.";
                        } catch (Exception $e) {
                            $otpError = "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                        }
                    } else {
                        $otpError = "Error preparing the statement: " . $conn->error;
                    }
                } else {
                    $otpError = "Error updating OTP.";
                }
                $updateStmt->close();
            } else {
                $otpError = "Error preparing the statement: " . $conn->error;
            }
        } else {
            $otpError = "User ID not found.";
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous" />
</head>

<body>
    <div class="d-flex justify-content-center align-items-center vh-100">
        <div class="text-light p-5 rounded shadow bg-dark col-4">
            <h2>Verify OTP</h2>
            <form method="post" action="verify_otp.php">
                <?php if ($otpError): ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($otpError); ?>
                    </div>
                <?php endif; ?>
                <?php if ($successMessage): ?>
                    <div class="alert alert-success" role="alert">
                        <?php echo htmlspecialchars($successMessage); ?>
                    </div>
                <?php endif; ?>
                <div class="mb-3">
                    <label for="otp" class="form-label">Enter OTP</label>
                    <input type="text" class="form-control" id="otp" name="otp" />
                </div>
                <div class="mb-3 text-center">
                    <span>Didn't receive OTP?
                        <button type="submit" name="resend_otp" class="btn btn-link text-success" style="text-decoration: none;">Resend OTP</button>
                    </span>
                </div>
                <button type="submit" name="verify_otp" class="btn btn-success text-uppercase w-100 mb-3">Verify OTP</button>
            </form>
        </div>
    </div>
</body>

</html>