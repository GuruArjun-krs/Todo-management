<?php
session_start();

if (!isset($_SESSION['username']) && !isset($_SESSION['role'])) {
    header("Location: adminLogin.php");
    exit();
}


include "./dbConnection.php";

if (isset($_GET['id'])) {
    $user_id = intval($_GET['id']);

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("DELETE FROM todo_list WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $stmt = $conn->prepare("SELECT file_path FROM files WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $file_path = $row['file_path'];
            if (file_exists($file_path)) {
                unlink($file_path); 
            }
        }

        $stmt = $conn->prepare("DELETE FROM files WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM user WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "Error: " . $e->getMessage();
    }

    $conn->close();
    header("Location: adminDashboard.php");
    exit();
} else {
    echo "No user ID provided.";
}
