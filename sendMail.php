<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $message = $_POST['message'] ?? '';

    $ccEmails = ['guruarjun@coderzvisiontech.com', 'mrplaystoreytt@gmail.com'];
    $bccEmails = ['balaguru1512@gmail.com', 'venkatacharan@coderzvisiontech.com'];

    if (empty($name) || empty($email) || empty($message)) {
        $error = [];

        if (empty($name)) {
            $error[] = 'name';
        }

        if (empty($email)) {
            $error[] = 'email';
        }

        if (empty($message)) {
            $error[] = 'message';
        }

        $errorString = implode(',', $error);
        header("Location: home.php?status=error&fields={$errorString}");
        exit;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $mail->Port       = $_ENV['MAIL_PORT'];

        $mail->setFrom('no-reply@example.com', 'Support Team');
        $mail->addAddress($email, $name);

        foreach ($ccEmails as $cc) {
            $mail->addCC($cc);
        }
        foreach ($bccEmails as $bcc) {
            $mail->addBCC($bcc);
        }

        if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['tmp_name'])) {
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['attachments']['error'][$key] == UPLOAD_ERR_OK) {
                    $uploadfile = tempnam(sys_get_temp_dir(), sha1($_FILES['attachments']['name'][$key]));
                    move_uploaded_file($tmp_name, $uploadfile);
                    $mail->addAttachment($uploadfile, $_FILES['attachments']['name'][$key]);
                }
            }
        }

        $localhostUrl = 'http://localhost/crud/login.php';

        $mail->isHTML(true);
        $mail->Subject = 'Support Team';
        $mail->Body    = '
            <p>Hello ' . htmlspecialchars($name) . ',</p>
            <p>Thank you for reaching out to us. We have received your message and our support team will contact you soon.</p>
            <p>For more information, you can visit our <a href="' . $localhostUrl . '">website</a>.</p>
            <p>Best regards,<br>Support Team</p>
        ';
        $mail->AltBody = '
            Hello ' . htmlspecialchars($name) . ',
            
            Thank you for reaching out to us. We have received your message and our support team will contact you soon.

            For more information, you can register to our website: ' . $localhostUrl . '
            
            Best regards,
            Support Team
        ';

        $mail->send();
        header('Location: home.php?status=success');
    } catch (Exception $e) {
        error_log("Mail Error: " . $mail->ErrorInfo);
        header('Location: home.php?status=error');
    }
}
