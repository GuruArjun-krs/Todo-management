<?php
session_start();
include "./dbConnection.php";

if (!isset($_SESSION['user_email'])) {
    header("Location: login.php");
    exit();
}

$error_message = '';
$success_message = '';
$edit_task_id = isset($_GET['edit']) ? intval($_GET['edit']) : null;
$edit_task_text = '';
$delete_task_id = isset($_GET['delete']) ? intval($_GET['delete']) : null;

$user_email = $_SESSION['user_email'];
$user_id = $_SESSION['user_id'];

$tasks_per_page = isset($_GET['tasks_per_page']) ? intval($_GET['tasks_per_page']) : 5;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($current_page - 1) * $tasks_per_page;

$stmt = $conn->prepare("SELECT profile, first_name , last_name FROM user WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$profile_image = $user['profile'] ?? '';
$first_name =  $user['first_name'] . " " . $user['last_name'];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task = trim($_POST['task'] ?? '');
    $task_id = $_POST['task_id'] ?? null;

    if (!empty($task)) {
        if ($task_id) {
            $stmt = $conn->prepare("UPDATE todo_list SET task_name = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $task, $task_id, $user_id);
            if ($stmt->execute()) {
                $success_message = "Task updated successfully.";
                $edit_task_id = null;
            } else {
                $error_message = "Error updating task: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO todo_list (user_id, task_name) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $task);
            if ($stmt->execute()) {
                $success_message = "Task added successfully.";
            } else {
                $error_message = "Error adding task: " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $error_message = "Task cannot be empty.";
    }
}

if ($edit_task_id) {
    $stmt = $conn->prepare("SELECT task_name FROM todo_list WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $edit_task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $edit_task_text = $row['task_name'];
    } else {
        $error_message = "Task not found.";
        $edit_task_id = null;
    }
    $stmt->close();
}

if ($delete_task_id) {
    $stmt = $conn->prepare("DELETE FROM todo_list WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $delete_task_id, $user_id);
    if ($stmt->execute()) {
        $success_message = "Task deleted successfully.";
    } else {
        $error_message = "Error deleting task: " . $stmt->error;
    }
    $stmt->close();
}

$search_term = isset($_GET['search']) ? '%' . $conn->real_escape_string($_GET['search']) . '%' : '%';

$stmt = $conn->prepare("SELECT COUNT(*) FROM todo_list WHERE user_id = ? AND task_name LIKE ?");
$stmt->bind_param("is", $user_id, $search_term);
$stmt->execute();
$result = $stmt->get_result();
$total_tasks = $result->fetch_row()[0];
$total_pages = ceil($total_tasks / $tasks_per_page);

$stmt = $conn->prepare("SELECT id, task_name FROM todo_list WHERE user_id = ? AND task_name LIKE ? LIMIT ? OFFSET ?");
$stmt->bind_param("isii", $user_id, $search_term, $tasks_per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();

$conn->close();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="./css/dashboard.css" />
    <style>
        body {
            background-image: url(./asserts/background.avif);
            background-size: cover;
            background-repeat: no-repeat;
        }

        .pagination .page-link {
            color: #28a745 !important;
        }

        .pagination .page-item.active .page-link {
            background-color: rgba(144, 238, 144, 0.3) !important;
            border-color: #28a745 !important;
        }

        .pagination .page-link:hover {
            color: #218838 !important;
        }

        .text-success {
            color: #28a745 !important;
        }
    </style>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid text-end">
            <div class="d-flex align-items-center gap-3">
                <img src='../../crud/asserts/logo.avif' alt="logo" style="width: 50px; height:50px;border-radius:50%" />
                <a class="navbar-brand fs-3" href="home.php">Dashboard</a>
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
        <?php include '../crud/layout/sidebar.php' ?>

        <div class="container flex-grow-1" style="overflow-y: scroll; max-height: calc(100vh - 180px); -ms-overflow-style: none; scrollbar-width: none;">
            <div class="mb-4">
                <h3 class="mb-3"><?= $edit_task_id ? 'Edit Task' : 'Assign Your Task' ?></h3>
                <form action="" method="POST">
                    <div class="mb-3 col-6">
                        <input type="text" name="task" class="form-control" placeholder="Enter task" value="<?= htmlspecialchars($edit_task_text) ?>" required />
                    </div>
                    <input type="hidden" name="task_id" value="<?= htmlspecialchars($edit_task_id) ?>" />
                    <button type="submit" class="btn btn-success"><?= $edit_task_id ? 'Update Task' : 'Add Task' ?></button>
                </form>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger mt-3">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success mt-3">
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>
            </div>

            <h3 class="mb-3">Your Tasks</h3>
            <form class="mb-4" method="GET" action="">
                <div class="row mb-3">
                    <div class="col-4">
                        <input type="text" name="search" class="form-control" placeholder="Search tasks" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
                    </div>
                    <div class="col-1">
                        <select name="tasks_per_page" class="form-select" onchange="this.form.submit()">
                            <option value="<?= $total_tasks ?> ?? 'All' " <?= $tasks_per_page == $total_tasks ? 'selected' : '' ?>>All</option>
                            <option value="1" <?= $tasks_per_page == 1 ? 'selected' : '' ?>>1</option>
                            <option value="5" <?= $tasks_per_page == 5 ? 'selected' : '' ?>>5</option>
                            <option value="10" <?= $tasks_per_page == 10 ? 'selected' : '' ?>>10</option>
                            <option value="15" <?= $tasks_per_page == 15 ? 'selected' : '' ?>>15</option>
                            <option value="20" <?= $tasks_per_page == 20 ? 'selected' : '' ?>>20</option>
                        </select>
                    </div>
                </div>
            </form>

            <ul class="list-group col-6">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center mb-3">
                        <?= htmlspecialchars($row['task_name']) ?>
                        <div>
                            <button class="btn btn-warning btn-sm me-2" data-bs-toggle="modal" data-bs-target="#editModal" data-id="<?= $row['id'] ?>" data-task="<?= htmlspecialchars($row['task_name']) ?>">Edit</button>
                            <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteModal" data-id="<?= $row['id'] ?>">Delete</button>
                        </div>
                    </li>
                <?php endwhile; ?>
            </ul>

            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?= $current_page == 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $current_page - 1 ?>&tasks_per_page=<?= $tasks_per_page ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    <li class="page-item <?= $current_page == 1 ? 'active' : '' ?>">
                        <a class="page-link" href="?page=1&tasks_per_page=<?= $tasks_per_page ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>">1</a>
                    </li>

                    <?php if ($total_pages > 3): ?>
                        <li class="page-item <?= $current_page == 2 ? 'active' : '' ?>">
                            <a class="page-link" href="?page=2&tasks_per_page=<?= $tasks_per_page ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>">2</a>
                        </li>
                    <?php endif; ?>

                    <?php if ($total_pages > 4): ?>
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    <?php endif; ?>

                    <?php if ($total_pages > 1): ?>
                        <li class="page-item <?= $current_page == $total_pages ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $total_pages ?>&tasks_per_page=<?= $tasks_per_page ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>"><?= $total_pages ?></a>
                        </li>
                    <?php endif; ?>

                    <li class="page-item <?= $current_page == $total_pages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $current_page + 1 ?>&tasks_per_page=<?= $tasks_per_page ?>&search=<?= htmlspecialchars($_GET['search'] ?? '') ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>


        </div>
    </div>

    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Edit Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editForm" action="" method="POST">
                        <div class="mb-3">
                            <input type="text" name="task" id="editTaskInput" class="form-control" placeholder="Enter task" required />
                        </div>
                        <input type="hidden" name="task_id" id="editTaskId" />
                        <button type="submit" class="btn btn-primary">Update Task</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Task Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Delete Task</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this task?</p>
                </div>
                <div class="modal-footer">
                    <form id="deleteForm" action="" method="GET">
                        <input type="hidden" name="delete" id="deleteTaskId" />
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include '../crud/layout/footer.php' ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');

            editModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const taskId = button.getAttribute('data-id');
                const taskName = button.getAttribute('data-task');
                const modal = bootstrap.Modal.getInstance(editModal);
                const form = modal._element.querySelector('form');

                form.querySelector('#editTaskInput').value = taskName;
                form.querySelector('#editTaskId').value = taskId;
            });

            deleteModal.addEventListener('show.bs.modal', (event) => {
                const button = event.relatedTarget;
                const taskId = button.getAttribute('data-id');
                const modal = bootstrap.Modal.getInstance(deleteModal);
                const form = modal._element.querySelector('form');

                form.querySelector('#deleteTaskId').value = taskId;
            });
        });
    </script>
</body>

</html>