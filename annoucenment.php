<?php
session_start();
include "./dbConnection.php";

if (!isset($_SESSION['username']) && !isset($_SESSION['role'])) {
    header("Location: adminLogin.php");
    exit();
}

$announcement_query = "
    SELECT a.id, a.admin_id, a.announcement, a.created_at, ad.username
    FROM announcements a
    JOIN admin ad ON a.admin_id = ad.id
    ORDER BY a.created_at DESC
";
$announcement_result = $conn->query($announcement_query);

$announcements = [];
if ($announcement_result->num_rows > 0) {
    while ($row = $announcement_result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_announcement_id']) && isset($_POST['admin_id'])) {
    $delete_id = intval($_POST['delete_announcement_id']);
    $admin_id = intval($_POST['admin_id']);
    if ($_SESSION['admin_id'] == $admin_id) {
        $delete_stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND admin_id = ?");
        $delete_stmt->bind_param("ii", $delete_id, $admin_id);

        if ($delete_stmt->execute()) {
            $success_message = "Announcement deleted successfully.";
            $announcement_result = $conn->query($announcement_query);
            $announcements = [];
            if ($announcement_result->num_rows > 0) {
                while ($row = $announcement_result->fetch_assoc()) {
                    $announcements[] = $row;
                }
            }
        } else {
            $error_message = "Error deleting announcement: " . $conn->error;
        }
        $delete_stmt->close();
    } else {
        $error_message = "You are not authorized to delete this announcement.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['delete_announcement_id'])) {
    if (!isset($_SESSION['admin_id'])) {
        $error_message = "Admin not logged in.";
    } else {
        $admin_id = $_SESSION['admin_id'];
        $announcement_text = trim($_POST['announcement']);

        if (empty($announcement_text)) {
            $error_message = "Announcement text cannot be empty.";
        } else {
            $stmt = $conn->prepare("INSERT INTO announcements (admin_id, announcement) VALUES (?, ?)");
            $stmt->bind_param("is", $admin_id, $announcement_text);

            if ($stmt->execute()) {
                $success_message = "Announcement posted successfully.";
                $announcement_result = $conn->query($announcement_query);
                $announcements = [];
                if ($announcement_result->num_rows > 0) {
                    while ($row = $announcement_result->fetch_assoc()) {
                        $announcements[] = $row;
                    }
                }
            } else {
                $error_message = "Error posting announcement: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Announcements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url(./asserts/background.avif);
            background-size: cover;
            background-repeat: no-repeat;
            display: flex;
            flex-direction: column;
            height: 100vh;
            margin: 0;
        }

        .bottom-fixed {
            position: fixed;
            bottom: 0;
            width: 100%;
        }
    </style>
</head>

<body>
    <?php include './admin/adminHeader.php'; ?>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success text-center"><?= htmlspecialchars($success_message) ?></div>
    <?php elseif (isset($error_message)): ?>
        <div class="alert alert-danger text-center"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="container mt-3">
        <a href="./adminDashboard.php" class="text-decoration-none text-danger fs-5">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back To Dashboard</span>
        </a>
    </div>

    <div class="container mt-3">
        <div class="row">
            <?php if ($role === 'admin' || $role === 'superadmin'): ?>
                <div class="col-6">
                    <div class="card text-white bg-dark mb-3">
                        <div class="card-header text-center fs-4 text-warning">Post an Announcement</div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <textarea class="form-control" id="announcementText" name="announcement" rows="4" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Post Announcement</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-6">
                <div class="card text-white bg-dark mb-3" style="overflow-y: scroll; max-height: calc(100vh - 220px); -ms-overflow-style: none; scrollbar-width: none;">
                    <div class="card-header text-center fs-4 text-warning">All Announcements</div>
                    <div class="card-body">
                        <?php if (empty($announcements)): ?>
                            <p class="text-center text-muted fs-4">No announcements found.</p>
                        <?php else: ?>
                            <?php foreach ($announcements as $announcement): ?>
                                <?php if ($role === 'admin' || $role === 'superadmin'): ?>
                                    <div class="text-end">
                                        <form method="POST" action="" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                            <input type="hidden" name="delete_announcement_id" value="<?= htmlspecialchars($announcement['id']) ?>">
                                            <input type="hidden" name="admin_id" value="<?= htmlspecialchars($announcement['admin_id']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    </div>
                                    <div class="announcement mb-3 p-3 border rounded">
                                        <li class="list-group-item text-dark">
                                            <?= htmlspecialchars($announcement['announcement']) ?>
                                            <br><small class="text-muted">Posted By: <?= htmlspecialchars($announcement['username']) ?></small>
                                        </li>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-fixed">
        <?php include './admin/adminFooter.php'; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
</body>

</html>