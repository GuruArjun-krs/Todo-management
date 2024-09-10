<?php
session_start();

// include './dbConnection.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header("Location: adminLogin.php");
    exit();
}

$adminDb = new mysqli("localhost", "root", "", "admin_tasks");
if ($adminDb->connect_error) {
    die("Connection failed: " . $adminDb->connect_error);
}

$message = '';
$messageType = '';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit'])) {
        $id = $_POST['id'];
        $type = $_POST['type'];
        $profileImage = NULL;

        if (isset($_FILES['profile_image_upload']) && $_FILES['profile_image_upload']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "images/";
            $fileName = basename($_FILES["profile_image_upload"]["name"]);
            $targetFile = $targetDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

            $check = getimagesize($_FILES["profile_image_upload"]["tmp_name"]);
            if ($check === false) {
                throw new Exception("File is not a valid image.");
            }

            $allowedFileTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($imageFileType, $allowedFileTypes)) {
                throw new Exception("Sorry, only JPG, JPEG, PNG, and GIF files are allowed.");
            }

            if ($_FILES["profile_image_upload"]["size"] > 5000000) {
                throw new Exception("Sorry, your file is too large. Maximum size is 5MB.");
            }

            $sql = "SELECT profile_image FROM boardmembers WHERE id = ?";
            $stmt = $adminDb->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $adminDb->error);
            }
            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $existingImage = $row['profile_image'];
            $stmt->close();

            if (!empty($existingImage) && $existingImage !== 'default.png') {
                $oldImagePath = $targetDir . $existingImage;
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }

            if (!move_uploaded_file($_FILES["profile_image_upload"]["tmp_name"], $targetFile)) {
                throw new Exception("Error uploading the file.");
            }

            $profileImage = $fileName;
            $message = "The file " . htmlspecialchars($fileName) . " has been uploaded.";
            $messageType = 'success';
        }

        if ($profileImage === NULL) {
            $sql = "UPDATE boardmembers SET type = ? WHERE id = ?";
            $stmt = $adminDb->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $adminDb->error);
            }
            $stmt->bind_param("si", $type, $id);
        } else {
            $sql = "UPDATE boardmembers SET profile_image = ?, type = ? WHERE id = ?";
            $stmt = $adminDb->prepare($sql);
            if ($stmt === false) {
                throw new Exception("Prepare failed: " . $adminDb->error);
            }
            $stmt->bind_param("ssi", $profileImage, $type, $id);
        }

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $message = "Record updated successfully";
        $messageType = 'success';
        $stmt->close();
    }
} catch (Exception $e) {
    $message = "Error: " . $e->getMessage();
    $messageType = 'danger';
}

$sql = "SELECT COUNT(*) as total FROM boardmembers";
$result = $adminDb->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    $rowCount = $row['total'];
} else {
    echo "Error: " . $adminDb->error;
}



$sql = "SELECT id, full_name, company_name, designation, type, created_at, profile_image FROM boardmembers";
$result = $adminDb->query($sql);
$boardMembers = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $boardMembers[] = $row;
    }
}
$adminDb->close();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Board Members</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url(./asserts/background.avif);
            background-size: cover;
            background-repeat: no-repeat;
        }

        .card {
            margin-bottom: 1rem;
        }

        .profile-image {
            width: 100%;
            height: auto;
        }

        .preview-img {
            width: 100%;
            height: auto;
            max-height: 200px;
            object-fit: cover;
        }

        .default-image {
            width: 100%;
            height: auto;
            max-height: 200px;
            object-fit: cover;
        }
    </style>
</head>

<body>
    <?php include './admin/adminHeader.php' ?>

    <div class="container mt-2">
        <h1 class="d-flex align-items-center justify-content-center">Board Members (<span class="text-danger"><?= htmlspecialchars($rowCount) ?></span>)</h1>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row mt-5" style="overflow-y: scroll; max-height: calc(100vh - 300px); -ms-overflow-style: none; scrollbar-width: none;">
            <?php foreach ($boardMembers as $member): ?>
                <div class="col-md-4">
                    <div class="card d-flex flex-column align-items-center justify-content-center text-center">
                        <?php
                        $imagePath = !empty($member['profile_image']) ? 'images/' . htmlspecialchars($member['profile_image']) : '../crud/asserts/defaultLogo.jpg';
                        ?>
                        <a href="#" data-bs-toggle="modal" style="width: 150px;height:150px;margin-top:20px" data-bs-target="#imagePreviewModal" data-image="<?= htmlspecialchars($imagePath) ?>">
                            <img src="<?= $imagePath ?>" class="card-img-top profile-image" alt="<?= htmlspecialchars($member['full_name']) ?>">
                        </a>
                        <div class="card-body">
                            <h5 class="card-title"><?= htmlspecialchars($member['full_name']) ?></h5>
                            <h6 class="card-subtitle mb-2 text-muted"><?= htmlspecialchars($member['company_name']) ?></h6>
                            <p class="card-text"><?= htmlspecialchars($member['designation']) ?></p>
                            <p class="card-text"><strong>Type:</strong> <?= htmlspecialchars($member['type']) ?></p>
                            <p class="card-text"><small class="text-muted">Created at: <?= htmlspecialchars($member['created_at']) ?></small></p>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?= $member['id'] ?>" data-image="<?= htmlspecialchars($member['profile_image']) ?>" data-type="<?= htmlspecialchars($member['type']) ?>">Edit</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="editId">
                        <div class="mb-3">
                            <div id="currentImageContainer">
                                <img id="currentProfileImage" src="../crud/asserts/defaultLogo.jpg" class="preview-img" alt="Profile Image">
                            </div>
                        </div>
                        <div class="mb-3">
                            <input type="file" class="form-control" id="profile_image_upload" name="profile_image_upload">
                        </div>
                        <div class="mb-3">
                            <label for="editType" class="form-label">Type</label>
                            <select class="form-select" id="editType" name="type">
                                <option value="partner">Partner</option>
                                <option value="shareholder">Shareholder</option>
                                <option value="member">Member</option>
                                <option value="secretary">Secretary</option>
                                <option value="coo">COO</option>
                            </select>
                        </div>
                        <button type="submit" name="edit" class="btn btn-primary">Save changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header text-center">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="imagePreview" src="images/defaultLogo.jpg" class="img-fluid" alt="Profile Image Preview">
                </div>
            </div>
        </div>
    </div>

    <footer class="fixed-bottom">
        <?php include './admin/adminFooter.php' ?>
    </footer>

    <script>
        var editModal = document.getElementById('editModal');
        editModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var image = button.getAttribute('data-image');
            var type = button.getAttribute('data-type');

            var modalTitle = editModal.querySelector('.modal-title');
            var editId = editModal.querySelector('#editId');
            var currentProfileImage = editModal.querySelector('#currentProfileImage');
            var editType = editModal.querySelector('#editType');
            modalTitle.textContent = '';
            editId.value = id;
            currentProfileImage.src = image ? "./images/" + image : '../crud/asserts/defaultLogo.jpg';
            editType.value = type;
        });

        var imagePreviewModal = document.getElementById('imagePreviewModal');
        imagePreviewModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var image = button.getAttribute('data-image');
            var imagePreview = imagePreviewModal.querySelector('#imagePreview');
            imagePreview.src = image ? image : 'images/defaultLogo.jpg';
        });
    </script>

</body>

</html>