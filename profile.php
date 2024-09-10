<?php
session_start();
include "./dbConnection.php";

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$error_message = '';
$success_message = '';

$user_email = $_SESSION['user_email'];

$stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $country = $_POST['country'] ?? '';
    $gender = $_POST['gender'][0] ?? '';
    $languages_known = isset($_POST['languages_known']) ? implode(',', $_POST['languages_known']) : '';
    $profile = $_FILES['profile']['name'] ?? '';
    $remove_image = $_POST['remove_image'] ?? '';

    if ($remove_image === '1') {
        if (!empty($user['profile']) && file_exists($user['profile'])) {
            unlink($user['profile']);
        }

        $stmt = $conn->prepare("UPDATE user SET profile = NULL WHERE email = ?");
        $stmt->bind_param("s", $user['email']);
        $stmt->execute();
        $stmt->close();
    } elseif (!empty($profile)) {
        $target_dir = "images/";
        $target_file = $target_dir . basename($_FILES["profile"]["name"]);

        if (!empty($user['profile']) && file_exists($user['profile'])) {
            unlink($user['profile']);
        }

        if (move_uploaded_file($_FILES["profile"]["tmp_name"], $target_file)) {
            $stmt = $conn->prepare("UPDATE user SET profile = ? WHERE email = ?");
            $stmt->bind_param("ss", $target_file, $user['email']);
            $stmt->execute();
            $stmt->close();
        } else {
            $error_message = "Sorry, there was an error uploading your file.";
        }
    }

    $stmt = $conn->prepare("UPDATE user SET first_name = ?, last_name = ?, dob = ?, country = ?, gender = ?, languages_known = ? WHERE email = ?");
    $stmt->bind_param("sssssss", $first_name, $last_name, $dob, $country, $gender, $languages_known, $user_email);

    if ($stmt->execute()) {
        $success_message = "Profile updated successfully.";
    } else {
        $error_message = "Error: " . $stmt->error;
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profile</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" />
    <link rel="stylesheet" href="./css/profile.css" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
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
                <a class="navbar-brand fs-3" href="home.php">Profile</a>
            </div>
            <ul class="navbar-nav ms-3">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($user['profile'] ?: './asserts/defaultLogo.jpg') ?>" alt="Profile Image" class="rounded-circle" style="width: 30px; height: 30px; object-fit: cover;">
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
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

        <div class="container my-5" style="overflow-y: scroll; max-height: calc(100vh - 180px); -ms-overflow-style: none; scrollbar-width: none;">
            <div class="text-center">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error_message) ?></div>
                <?php endif; ?>
                <?php if ($success_message): ?>
                    <div class="alert alert-success" role="alert"><?= htmlspecialchars($success_message) ?></div>
                <?php endif; ?>
            </div>
            <h3 class="mb-4">Edit Profile</h3>
            <form method="post" action="profile.php" enctype="multipart/form-data">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="first_name" class="form-label fw-bold">First Name:</label>
                            <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($user['first_name']) ?>" required />
                        </div>

                        <div class="mb-3">
                            <label for="last_name" class="form-label fw-bold">Last Name:</label>
                            <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($user['last_name']) ?>" required />
                        </div>

                        <div class="mb-3">
                            <label for="dob" class="form-label fw-bold">Date of Birth:</label>
                            <input type="date" class="form-control" id="dob" name="dob" value="<?= htmlspecialchars($user['dob']) ?>" required />
                        </div>

                        <div class="mb-3">
                            <label for="country" class="form-label fw-bold">Country:</label>
                            <select class="form-select" id="country" name="country" required>
                                <option value="India" <?= $user['country'] == 'India' ? 'selected' : '' ?>>India</option>
                                <option value="USA" <?= $user['country'] == 'USA' ? 'selected' : '' ?>>USA</option>
                                <option value="UK" <?= $user['country'] == 'UK' ? 'selected' : '' ?>>UK</option>
                                <option value="Canada" <?= $user['country'] == 'Canada' ? 'selected' : '' ?>>Canada</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6 d-flex justify-content-center align-items-center">
                        <div class="profile-picture-container position-relative">
                            <input type="file" id="profile" name="profile" class="d-hidden" onchange="previewImage(event)" />
                            <img id="profilePreview" src="<?= htmlspecialchars($user['profile'] ?: './asserts/defaultLogo.jpg') ?>" alt="Profile Preview" class="profile-picture" />
                            <i class="fa-solid fa-trash fs-4 position-absolute top-0 end-0 translate-middle p-2 bg-light rounded-circle" onclick="removeImage()"></i>
                            <input type="hidden" id="remove_image" name="remove_image" value="0" />
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Gender:</label>
                    <div class="d-flex flex-wrap">
                        <div class="form-check me-3">
                            <input class="form-check-input" type="radio" id="female" name="gender[]" value="Female" <?= $user['gender'] === 'Female' ? 'checked' : '' ?> />
                            <label class="form-check-label" for="female">Female</label>
                        </div>
                        <div class="form-check me-3">
                            <input class="form-check-input" type="radio" id="male" name="gender[]" value="Male" <?= $user['gender'] === 'Male' ? 'checked' : '' ?> />
                            <label class="form-check-label" for="male">Male</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" id="other" name="gender[]" value="Other" <?= $user['gender'] === 'Other' ? 'checked' : '' ?> />
                            <label class="form-check-label" for="other">Other</label>
                        </div>
                    </div>
                </div>

                <div class="mb-4 col-6">
                    <label class="form-label">Languages Known:</label>
                    <div class="d-flex justify-content-between">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="english" name="languages_known[]" value="English" <?= strpos($user['languages_known'], 'English') !== false ? 'checked' : '' ?> />
                            <label class="form-check-label" for="english">English</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="tamil" name="languages_known[]" value="Tamil" <?= strpos($user['languages_known'], 'Tamil') !== false ? 'checked' : '' ?> />
                            <label class="form-check-label" for="tamil">Tamil</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="telugu" name="languages_known[]" value="Telugu" <?= strpos($user['languages_known'], 'Telugu') !== false ? 'checked' : '' ?> />
                            <label class="form-check-label" for="telugu">Telugu</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="malayalam" name="languages_known[]" value="Malayalam" <?= strpos($user['languages_known'], 'Malayalam') !== false ? 'checked' : '' ?> />
                            <label class="form-check-label" for="malayalam">Malayalam</label>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-4 mb-5">Save Changes</button>
            </form>
        </div>
    </div>
    <div class="fixed-bottom">
        <?php include '../crud/layout/footer.php' ?>
    </div>

    <script>
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function() {
                const output = document.getElementById('profilePreview');
                output.src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        }

        function removeImage() {
            if (confirm("Are you sure you want to remove the profile picture?")) {
                document.getElementById('remove_image').value = '1';
                document.querySelector('form').submit();
            }
        }
    </script>
</body>

</html>