<?php
session_start();
include './dbConnection.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header("Location: adminLogin.php");
    exit();
}

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    $stmt = $conn->prepare("DELETE FROM admin WHERE id = ?");
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $success_message = 'User deleted successfully.';
    } else {
        $error_message = 'Error: ' . $stmt->error;
    }

    $stmt->close();
    $conn->close();

    header("Location: management.php");
    exit();
} else {
    header("Location: management.php");
    exit();
}
