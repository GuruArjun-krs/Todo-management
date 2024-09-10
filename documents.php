<?php
session_start();
include './dbConnection.php';

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$user_email = $_SESSION['user_email'];
$stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user['id'];
$profile_image = $user['profile'] ?? '';
$first_name = $user['first_name'] . ' ' . $user['last_name'];

$upload_folder = __DIR__ . '/documents/';

function generateUniqueFileName($file_path)
{
    $file_info = pathinfo($file_path);
    $timestamp = time();
    return $file_info['dirname'] . '/' . $file_info['filename'] . '_' . $timestamp . '.' . $file_info['extension'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['document'])) {
        $files = $_FILES['document'];
        $file_count = count($files['name']);
        $uploaded_files = [];

        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $document_name = basename($files['name'][$i]);
                $document_tmp_name = $files['tmp_name'][$i];
                $document_folder = $upload_folder . generateUniqueFileName($document_name);

                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'docx'];
                $file_ext = strtolower(pathinfo($document_name, PATHINFO_EXTENSION));

                if (in_array($file_ext, $allowed_types)) {
                    if (move_uploaded_file($document_tmp_name, $document_folder)) {
                        $uploaded_files[] = 'documents/' . basename($document_folder);
                    } else {
                        $message = "Failed to move uploaded file.";
                    }
                } else {
                    $message = "Invalid file type. Only JPG, PNG, GIF, and PDF files are allowed.";
                }
            } else {
                $message = "Error with file upload: " . $files['error'][$i];
            }
        }

        if (!empty($uploaded_files)) {
            $file_paths_json = json_encode($uploaded_files);

            $stmt = $conn->prepare("INSERT INTO files (user_id, file_path, uploaded_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE file_path = VALUES(file_path), uploaded_at = NOW()");
            $stmt->bind_param("is", $user_id, $file_paths_json);
            $stmt->execute();
            $message = "Files uploaded successfully.";
        }
    } elseif (isset($_POST['delete'])) {
        $file_id = intval($_POST['delete']);
        $stmt = $conn->prepare("SELECT file_path FROM files WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $file_id, $user_id);
        $stmt->execute();
        $file = $stmt->get_result()->fetch_assoc();

        if ($file) {
            $file_paths = json_decode($file['file_path'], true);

            $file_deleted = false;
            foreach ($file_paths as $index => $file_path) {
                if (unlink($upload_folder . basename($file_path))) {
                    $file_deleted = true;
                    unset($file_paths[$index]);
                    break;
                }
            }

            if ($file_deleted) {
                $file_paths = array_values($file_paths);
                if (empty($file_paths)) {
                    $stmt = $conn->prepare("DELETE FROM files WHERE id = ?");
                    $stmt->bind_param("i", $file_id);
                    $stmt->execute();
                    $message = "File and its record deleted successfully.";
                } else {
                    $file_paths_json = json_encode($file_paths);
                    $stmt = $conn->prepare("UPDATE files SET file_path = ? WHERE id = ?");
                    $stmt->bind_param("si", $file_paths_json, $file_id);
                    $stmt->execute();
                    $message = "File deleted successfully.";
                }
            } else {
                $message = "Failed to delete file.";
            }
        } else {
            $message = "File not found.";
        }
    } else {
        $message = "No file uploaded or upload error.";
    }
}

$stmt = $conn->prepare("SELECT id, file_path, uploaded_at FROM files WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$files = $stmt->get_result();

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="./css/documents.css" />
    <style>
        body {
            background-image: url(./asserts/background.avif);
            background-size: cover;
            background-repeat: no-repeat;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid text-end">
            <div class="d-flex align-items-center gap-3">
                <img src='../../crud/asserts/logo.avif' alt="logo" style="width: 50px; height:50px;border-radius:50%" />
                <a class="navbar-brand fs-3" href="home.php">Documents</a>
            </div>
            <ul class="navbar-nav ms-3">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($profile_image) ?: './asserts/defaultLogo.jpg' ?>" alt="Profile Image" class="rounded-circle" style="width: 30px; height: 30px; object-fit: cover;">
                        <?= htmlspecialchars($first_name) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>

    <div class="d-flex flex-grow-1">
        <?php include '../crud/layout/sidebar.php'; ?>
        <div class="container flex-grow-1" style="margin-top:20px; overflow-y: scroll; max-height: calc(100vh - 180px); -ms-overflow-style: none; scrollbar-width: none;">
            <?php if (isset($message)): ?>
                <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data" class="col-4">
                <div class="mb-3">
                    <h2 class="mt-4">Upload Files</h2>
                    <input type="file" name="document[]" class="form-control" multiple>
                </div>
                <button type="submit" class="btn btn-primary">Upload Files</button>
            </form>

            <h2 class="mt-4">Uploaded Files</h2>
            <div class="row">
                <?php if ($files->num_rows > 0): ?>
                    <?php while ($file = $files->fetch_assoc()): ?>
                        <?php
                        $file_paths = json_decode($file['file_path'], true);
                        ?>
                        <?php foreach ($file_paths as $file_path): ?>
                            <div class="col-md-4">
                                <div class="doc-card">
                                    <div class="doc-card-header">
                                        <h5 class="doc-card-title"><?= htmlspecialchars(basename($file_path)) ?></h5>
                                    </div>
                                    <div class="doc-card-body">
                                        <?php
                                        $file_ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
                                        $file_url = htmlspecialchars($file_path);
                                        if ($file_ext === 'pdf'): ?>
                                            <iframe src="<?= $file_url ?>" class="w-100" style="height: 200px;" frameborder="0"></iframe>
                                        <?php else: ?>
                                            <img src="<?= $file_url ?>" alt="<?= htmlspecialchars(basename($file_path)) ?>" class="w-100" style="height: 200px;">
                                        <?php endif; ?>
                                    </div>
                                    <div class="doc-card-footer d-flex flex-column text-left">
                                        <span class="text-muted fs-4"><?= htmlspecialchars(basename($file_path)) ?></span>
                                        <small class="text-muted"><?= htmlspecialchars($file['uploaded_at']) ?></small>

                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="delete" value="<?= htmlspecialchars($file['id']) ?>">
                                            <button type="submit" class="btn btn-danger btn-sm mt-3">Delete</button>
                                            <a href="<?= $file_url ?>" download="<?= htmlspecialchars(basename($file_path)) ?>" class="btn btn-primary btn-sm mt-3">Download</a>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No files uploaded yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <?php include '../crud/layout/footer.php'; ?>
    </footer>
</body>

</html>