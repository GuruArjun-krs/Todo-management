<?php
session_start();
include './dbConnection.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    header("Location: adminLogin.php");
    exit();
}

$role = $_SESSION['role'];

$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $admin_username = htmlspecialchars(trim($_POST['username']));
    $role = htmlspecialchars(trim($_POST['role']));
    $password = htmlspecialchars(trim($_POST['password']));
    $id = isset($_POST['id']) ? $_POST['id'] : null;

    if (empty($first_name) || empty($last_name) || empty($admin_username) || empty($role)) {
        $error_message = 'Please fill in all fields.';
    } else {
        if ($id) {
            if (empty($password)) {
                $stmt = $conn->prepare("UPDATE admin SET first_name = ?, last_name = ?, username = ?, role = ? WHERE id = ?");
                $stmt->bind_param("ssssi", $first_name, $last_name, $admin_username, $role, $id);
            } else {
                $stmt = $conn->prepare("UPDATE admin SET first_name = ?, last_name = ?, username = ?, role = ?, password = ? WHERE id = ?");
                $stmt->bind_param("sssssi", $first_name, $last_name, $admin_username, $role, $password, $id);
            }
            if ($stmt->execute()) {
                $success_message = 'Account updated successfully.';
            } else {
                $error_message = 'Error: ' . $stmt->error;
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO admin (first_name, last_name, username, role, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $first_name, $last_name, $admin_username, $role, $password);

            if ($stmt->execute()) {
                $success_message = 'Account created successfully.';
            } else {
                $error_message = 'Error: ' . $stmt->error;
            }
        }

        $stmt->close();
    }
}

$employer_result = $conn->query("SELECT * FROM admin WHERE role = 'employer'");
$admin_result = $conn->query("SELECT * FROM admin WHERE role = 'admin'");

$employers = $employer_result->fetch_all(MYSQLI_ASSOC);
$admins = $admin_result->fetch_all(MYSQLI_ASSOC);

$employer_count = count($employers);
$admin_count = count($admins);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-image: url(./asserts/background.avif);
            background-size: cover;
            background-repeat: no-repeat;
        }

        .custom-btn {
            background: none;
            color: white;
            border: none;
        }
    </style>
</head>

<body>
    <?php include './admin/adminHeader.php'; ?>
    <div class="container mt-3 fs-4">
        <a href="./adminDashboard.php" class="text-decoration-none text-danger">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back To Dashboard</span>
        </a>
    </div>

    <div class="container mt-2">
        <div class="row">
            <div class="col-4">
                <div class="card text-white bg-dark mb-3 p-4">
                    <h2 class="text-center text-warning mb-3">Manage Account</h2>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error_message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>

                    <form class="form needs-validation" id="loginForm" action="" method="POST" novalidate>
                        <div class="mb-3 position-relative">
                            <input
                                type="text"
                                name="first_name"
                                id="first_name"
                                class="form-control <?= !empty($error_message) && empty($first_name) ? 'is-invalid' : '' ?>"
                                placeholder="First Name"
                                value="<?= htmlspecialchars($first_name ?? '') ?>" />
                        </div>

                        <div class="mb-3 position-relative">
                            <input
                                type="text"
                                name="last_name"
                                id="last_name"
                                class="form-control <?= !empty($error_message) && empty($last_name) ? 'is-invalid' : '' ?>"
                                placeholder="Last Name"
                                value="<?= htmlspecialchars($last_name ?? '') ?>" />
                        </div>

                        <div class="mb-3 position-relative">
                            <input
                                type="text"
                                name="username"
                                id="username"
                                class="form-control <?= !empty($error_message) && empty($admin_username) ? 'is-invalid' : '' ?>"
                                placeholder="Username"
                                value="<?= htmlspecialchars($admin_username ?? '') ?>" />
                        </div>
                        <?php if ($role === 'admin'): ?>
                            <div class="mb-3">
                                <select class="form-select" id="editRole" name="role" required>
                                    <option value="admin" disabled>Admin</option>
                                    <option value="employer" <?= isset($role) && $role == 'employer' ? 'selected' : '' ?>>Employer</option>
                                </select>
                            </div>
                        <?php elseif ($role === 'superadmin'): ?>
                            <div class="mb-3">
                                <select class="form-select" id="editRole" name="role" required>
                                    <option value="admin" <?= isset($role) && $role == 'admin' ? 'selected' : '' ?>>Admin</option>
                                    <option value="employer" <?= isset($role) && $role == 'employer' ? 'selected' : '' ?>>Employer</option>
                                    <option value="superadmin" <?= isset($role) && $role == 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3 position-relative">
                            <input
                                type="password"
                                name="password"
                                id="password"
                                class="form-control <?= !empty($error_message) && empty($password) ? 'is-invalid' : '' ?>"
                                placeholder="Password" />
                        </div>

                        <input
                            type="submit"
                            class="btn btn-success w-100"
                            value="Create Account" />
                    </form>
                </div>
            </div>

            <div class="col-4">
                <div class="card text-white bg-dark mb-3 p-4">
                    <h3 class="text-center mb-3">Employers (<span class="text-warning"><?= $employer_count ?></span>)</h3>
                    <ul class="list-group" style="overflow-y: scroll; max-height: calc(100vh - 315px); -ms-overflow-style: none; scrollbar-width: none;">
                        <?php foreach ($employers as $employer): ?>
                            <li class="list-group-item bg-light text-dark d-flex justify-content-between align-items-center">
                                <span>
                                    <?= htmlspecialchars($employer['first_name'] . ' ' . $employer['last_name']) ?>
                                </span>
                                <div class="d-flex gap-3">
                                    <?php if ($role  !== 'employer'): ?>
                                        <i class="fa-solid fa-user-xmark me-2" title="Delete User" style="cursor: pointer;" onclick="showDeleteModal(<?= htmlspecialchars($employer['id']) ?>)"></i>
                                        <i class="fa-solid fa-user-pen" title="Edit User" style="cursor: pointer;" onclick="editUser(<?= htmlspecialchars($employer['id']) ?>, '<?= htmlspecialchars($employer['first_name']) ?>', '<?= htmlspecialchars($employer['last_name']) ?>', '<?= htmlspecialchars($employer['username']) ?>', '<?= htmlspecialchars($employer['role']) ?>')"></i>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="col-4">
                <div class="card text-white bg-dark mb-3 p-4">
                    <h3 class="text-center mb-3">Admins (<span class="text-warning"><?= $admin_count ?></span>)</h3>
                    <ul class="list-group" style="overflow-y: scroll; max-height: calc(100vh - 315px); -ms-overflow-style: none; scrollbar-width: none;">
                        <?php foreach ($admins as $admin): ?>
                            <li class="list-group-item bg-light text-dark d-flex justify-content-between align-items-center">
                                <span>
                                    <?= htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) ?>
                                </span>
                                <div class="d-flex gap-3">
                                    <?php if ($role  !== 'employer'): ?>
                                        <?php if ($role === 'superadmin'): ?>
                                            <i class="fa-solid fa-user-xmark me-2" title="Delete User" style="cursor: pointer;" onclick="showDeleteModal(<?= htmlspecialchars($admin['id']) ?>)"></i>
                                            <i class="fa-solid fa-user-pen" title="Edit User" style="cursor: pointer;"
                                                onclick="editUser(<?= htmlspecialchars($admin['id']) ?>, '<?= htmlspecialchars($admin['first_name']) ?>', '<?= htmlspecialchars($admin['last_name']) ?>', '<?= htmlspecialchars($admin['username']) ?>', '<?= htmlspecialchars($admin['role']) ?>')"></i>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this user? This action will remove all related data.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="deleteUser()">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editUserForm" action="" method="POST">
                    <div class="modal-body p-4 bg-dark">
                        <div class=" text-end">
                            <button type="button" class="custom-btn" data-bs-dismiss="modal"><i class="fa-solid fa-xmark fs-5"></i></button>
                        </div>
                        <p class="text-center fs-3 fw-bold text-warning">Edit Management</p>
                        <input type="hidden" name="id" id="editUserId">
                        <div class="mb-3">
                            <input type="text" class="form-control" id="editFirstName" placeholder="First Name" name="first_name" required>
                        </div>
                        <div class="mb-3">
                            <input type="text" class="form-control" id="editLastName" placeholder="Last Name" name="last_name" required>
                        </div>
                        <div class="mb-3">
                            <input type="text" class="form-control" id="editUsername" placeholder="Username" name="username" required>
                        </div>
                        <?php if ($role === 'admin'): ?>
                            <div class="mb-3">
                                <select class="form-select" id="editRole" name="role" required>
                                    <option value="admin">Admin</option>
                                    <option value="employer">Employer</option>
                                </select>
                            </div>
                        <?php elseif ($role === 'superadmin'): ?>
                            <div class="mb-3">
                                <select class="form-select" id="editRole" name="role" required>
                                    <option value="admin">Admin</option>
                                    <option value="employer">Employer</option>
                                    <option value="superadmin">Super Admin</option>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <input type="password" class="form-control" placeholder="Password" id="editPassword" name="password">
                            <div class="mt-4 d-flex align-items-center gap-2">
                                <i class="fa-regular fa-circle-question text-light" style="font-size: 1.2rem;"></i>
                                <p class="text-danger mb-0" style="font-size: 0.9rem;">Leave blank to keep the current password.</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-dark border-none d-flex justify-content-center pt-0" style="border: none;">
                        <button type="submit" class="btn btn-success">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <footer class="fixed-bottom">
        <?php include './admin/adminFooter.php'; ?>
    </footer>

    <script>
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function(form) {
                    form.addEventListener('submit', function(event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()

        let userIdToDelete = null;

        function showDeleteModal(userId) {
            userIdToDelete = userId;
            console.log('userIdToDelete', userIdToDelete)
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        function deleteUser() {
            if (userIdToDelete !== null) {
                window.location.href = `managementDel.php?id=${userIdToDelete}`;
            }
        }

        function editUser(id, firstName, lastName, username, role) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editFirstName').value = firstName;
            document.getElementById('editLastName').value = lastName;
            document.getElementById('editUsername').value = username;
            document.getElementById('editRole').value = role;

            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }
    </script>
</body>

</html>