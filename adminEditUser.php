<?php
session_start();

if (!isset($_SESSION['username']) && !isset($_SESSION['role'])) {
    header("Location: adminLogin.php");
    exit();
}


include "./dbConnection.php";

$user_id = isset($_GET['id']) ? intval($_GET['id']) : null;

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

if ($user_id !== null) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['task_id'], $_POST['status']) && !isset($_POST['task_name'])) {
            $task_id = intval($_POST['task_id']);
            $status = $_POST['status'];

            $conn->begin_transaction();

            try {
                $updateStmt = $conn->prepare("UPDATE todo_list SET task_status = ? WHERE id = ? AND user_id = ?");
                $updateStmt->bind_param("sii", $status, $task_id, $user_id);
                $updateStmt->execute();

                $conn->commit();
                $success_message = "Task status updated successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to update task status: " . $e->getMessage();
            }

            $updateStmt->close();
        } elseif (isset($_POST['task_name']) && !isset($_POST['task_id'])) {
            $task_name = htmlspecialchars($_POST['task_name']);
            $created_at = date('Y-m-d H:i:s');
            $updated_at = $created_at;
            $status = 'not started';

            $conn->begin_transaction();

            try {
                $insertStmt = $conn->prepare("INSERT INTO todo_list (user_id, task_name, task_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?)");
                $insertStmt->bind_param("issss", $user_id, $task_name, $status, $created_at, $updated_at);
                $insertStmt->execute();

                $conn->commit();
                $success_message = "New task added successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to add new task: " . $e->getMessage();
            }

            $insertStmt->close();
        } elseif (isset($_POST['delete_task_id'])) {
            $task_id = intval($_POST['delete_task_id']);

            $conn->begin_transaction();

            try {
                $deleteStmt = $conn->prepare("DELETE FROM todo_list WHERE id = ? AND user_id = ?");
                $deleteStmt->bind_param("ii", $task_id, $user_id);
                $deleteStmt->execute();

                $conn->commit();
                $success_message = "Task deleted successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to delete task: " . $e->getMessage();
            }

            $deleteStmt->close();
        } elseif (isset($_POST['task_id'], $_POST['task_name'])) {
            $task_id = intval($_POST['task_id']);
            $task_name = htmlspecialchars($_POST['task_name']);
            $updated_at = date('Y-m-d H:i:s');

            $conn->begin_transaction();

            try {
                $updateStmt = $conn->prepare("UPDATE todo_list SET task_name = ?, updated_at = ? WHERE id = ? AND user_id = ?");
                $updateStmt->bind_param("ssii", $task_name, $updated_at, $task_id, $user_id);
                $updateStmt->execute();

                $conn->commit();
                $success_message = "Task updated successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to update task: " . $e->getMessage();
            }

            $updateStmt->close();
        } elseif (isset($_POST['firstname'], $_POST['lastname'], $_POST['email'])) {
            $first_name = htmlspecialchars($_POST['firstname']);
            $last_name = htmlspecialchars($_POST['lastname']);
            $email = htmlspecialchars($_POST['email']);

            $conn->begin_transaction();

            try {
                $updateStmt = $conn->prepare("UPDATE user SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
                $updateStmt->bind_param("sssi", $first_name, $last_name, $email, $user_id);
                $updateStmt->execute();

                $conn->commit();
                $success_message = "User details updated successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Oops! Email is already exists.";
            }

            $updateStmt->close();
        } elseif (isset($_POST['task_id'], $_POST['comments'])) {
            $task_id = intval($_POST['task_id']);
            $comments = trim(htmlspecialchars($_POST['comments']));
            $updated_at = date('Y-m-d H:i:s');

            $conn->begin_transaction();

            try {
                $updateStmt = $conn->prepare("UPDATE todo_list SET comments = ?, updated_at = ? WHERE id = ? AND user_id = ?");
                $updateStmt->bind_param("ssii", $comments, $updated_at, $task_id, $user_id);
                $updateStmt->execute();

                $conn->commit();
                $success_message = "Comments updated successfully.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Failed to update comments: " . $e->getMessage();
            }

            $updateStmt->close();
        }
    }

    $stmt = $conn->prepare("SELECT * FROM user WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();

    if ($search) {
        $taskStmt = $conn->prepare("SELECT * FROM todo_list WHERE user_id = ? AND task_name LIKE ?");
        $searchParam = "%$search%";
        $taskStmt->bind_param("is", $user_id, $searchParam);
    } else {
        $taskStmt = $conn->prepare("SELECT * FROM todo_list WHERE user_id = ?");
        $taskStmt->bind_param("i", $user_id);
    }

    $taskStmt->execute();
    $taskResult = $taskStmt->get_result();
    $userTasks = [];
    while ($task = $taskResult->fetch_assoc()) {
        $userTasks[] = $task;
    }
    $taskStmt->close();
} else {
    header("Location: unauthorised.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-image: url(./asserts/background.avif);
            background-size: cover;
            background-repeat: no-repeat;
        }
    </style>
</head>

<body>
    <?php include './admin/adminHeader.php'; ?>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success text-center"><?= $success_message ?></div>
    <?php elseif (isset($error_message)): ?>
        <div class="alert alert-danger text-center"><?= $error_message ?></div>
    <?php endif; ?>

    <div class="container mt-5">
        <a href="adminDashboard.php" class="text-decoration-none fs-3 text-bold text-danger">
            <i class="fa-solid fa-arrow-left"></i></i><span> Go Back</span>
        </a>

        <?php if ($role === 'admin' || $role === 'superadmin'): ?>
            <form method="POST">
                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                <div class="row">
                    <div class="col-md-4 p-2">
                        <input type="text" class="form-control" placeholder="First Name" id="firstname" name="firstname" value="<?= htmlspecialchars($userData['first_name']) ?>" />
                    </div>
                    <div class="col-md-4 p-2">
                        <input type="text" class="form-control" placeholder="Last Name" id="lastname" name="lastname" value="<?= htmlspecialchars($userData['last_name']) ?>" />
                    </div>
                    <div class="col-md-4 p-2">
                        <input type="email" class="form-control" placeholder="Email" id="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>" />
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-success" id="makeChangesBtn">Update profile</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>

        <div class="d-flex justify-content-between mt-4">
            <form action="" method="POST" class="col-5">
                <input type="hidden" name="user_id" value="<?= $user_id ?>">
                <div class="d-flex gap-3">
                    <input type="text" name="task_name" class="form-control" placeholder="Assign New Task" required>
                    <button type="submit" class="btn btn-success col-3">Add Task</button>
                </div>
            </form>

            <form method="GET" class="d-flex gap-3 col-5">
                <input type="hidden" name="id" value="<?= $user_id ?>">
                <input class="form-control me-2" type="search" name="search" placeholder="Search Task" aria-label="Search" value="<?= htmlspecialchars($search) ?>">
                <button class="btn btn-outline-success col-3" type="submit">Search</button>
            </form>
        </div>

        <div class="container mt-5" id="todoListContainer">
            <div class="mt-5">
                <div class="d-flex justify-content-between align-items-center">
                    <h3>TODO List</h3>
                </div>
                <table class="table border-dark table-hover table-bordered mt-3">
                    <thead>
                        <tr>
                            <th>Task Name</th>
                            <th>Status</th>
                            <th>Actions</th>
                            <th>Comments</th>
                        </tr>
                    </thead>
                    <tbody id="todoListTableBody">

                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <?php foreach ($userTasks as $task): ?>
        <div class="modal fade" id="editModal<?= $task['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $task['id'] ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel<?= $task['id'] ?>">Edit Task</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form action="" method="POST">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <div class="mb-3">
                                <label for="taskName" class="form-label">Task Name</label>
                                <input type="text" class="form-control" id="taskName" name="task_name" value="<?= htmlspecialchars($task['task_name']) ?>">
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-success">Update Task</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script>
        function exportCSV() {
            const userId = <?= $user_id ?>;
            window.location.href = './exportCSV/usersTodoListCSV.php?id=' + userId;
        }

        document.querySelectorAll('.btn-info').forEach(button => {
            button.addEventListener('click', function() {
                const modalId = this.getAttribute('data-bs-target');
                const modal = new bootstrap.Modal(document.querySelector(modalId));
                modal.show();
            });
        });
    </script>

    <script>
        let userId = <?= $user_id ?>;
        let offset = 0;
        const limit = 2;

        function loadTasks() {
            const search = new URLSearchParams(window.location.search).get('search') || '';
            fetch(`../crud/infiniteScroll/adminEditUserScroll.php?user_id=${userId}&offset=${offset}&search=${encodeURIComponent(search)}`)
                .then(response => response.json())
                .then(data => {
                    let tasksHtml = '';
                    data.forEach(task => {
                        tasksHtml += `
                        <tr>
                            <td>${task.task_name}</td>
                            <td>
                                <form action="" method="POST" class="d-inline">
                                    <input type="hidden" name="task_id" value="${task.id}">
                                    <select name="status" onchange="this.form.submit()" class="form-select">
                                        <option value="Not Started" ${task.task_status === 'Not Started' ? 'selected' : ''}>Not Started</option>
                                        <option value="In Progress" ${task.task_status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                                        <option value="Completed" ${task.task_status === 'Completed' ? 'selected' : ''}>Completed</option>
                                        <option value="On Hold" ${task.task_status === 'On Hold' ? 'selected' : ''}>On Hold</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#editModal${task.id}">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="" method="POST" class="d-inline">
                                    <input type="hidden" name="delete_task_id" value="${task.id}">
                                    <button type="submit" class="btn btn-danger">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <form action="" method="POST">
                                    <input type="hidden" name="task_id" value="${task.id}">
                                    <textarea name="comments" class="form-control" rows="4">${task.comments.trim()}</textarea>
                                    <button type="submit" class="btn btn-success mt-2">Post Comment</button>
                                </form>
                            </td>
                        </tr>
                    `;
                    });

                    document.getElementById('todoListTableBody').insertAdjacentHTML('beforeend', tasksHtml);
                    offset += limit;
                });
        }

        loadTasks();

        window.addEventListener('scroll', () => {
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight) {
                loadTasks();
            }
        });
    </script>


</body>

</html>