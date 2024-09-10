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
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_id = $user_data['id'];
$first_name = $user_data['first_name'] . ' ' . $user_data['last_name'];
$profile_image = $user_data['profile'] ?? '';

$tasks_per_page = isset($_GET['tasks_per_page']) ? intval($_GET['tasks_per_page']) : 5;
$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$search_term = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
$offset = ($current_page - 1) * $tasks_per_page;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = $_POST['task_id'] ?? null;
    $status = $_POST['status'] ?? '';

    if ($task_id && in_array($status, ['Not Started', 'Completed', 'On Hold', 'In Progress'])) {
        $stmt = $conn->prepare("UPDATE todo_list SET task_status = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param("sii", $status, $task_id, $user_id);

        if ($stmt->execute()) {
            $success_message = "Task status updated successfully.";
        } else {
            $error_message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT COUNT(*) FROM todo_list WHERE user_id = ? AND task_name LIKE ?");
$stmt->bind_param("is", $user_id, $search_term);
$stmt->execute();
$result = $stmt->get_result();
$total_tasks = $result->fetch_row()[0];

$total_pages = ceil($total_tasks / $tasks_per_page);
$total_pages = max($total_pages, 1);

$stmt = $conn->prepare("SELECT * FROM todo_list WHERE user_id = ? AND task_name LIKE ? LIMIT ? OFFSET ?");
$stmt->bind_param("isii", $user_id, $search_term, $tasks_per_page, $offset);
$stmt->execute();
$to_do_list = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="./css/task.css">
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
                <a class="navbar-brand fs-3" href="home.php">Manage Task</a>
            </div>
            <ul class="navbar-nav ms-3">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
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

    <div class="main-content d-flex flex-grow-1">
        <?php include '../crud/layout/sidebar.php'; ?>

        <div class="container p-4 flex-grow-1" style="overflow-y: scroll; max-height: calc(100vh - 180px); -ms-overflow-style: none; scrollbar-width: none;">
            <h1 class="mb-4">Manage Tasks</h1>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>


            <form method="GET" action="" class="mb-4">
                <div class="col-4 mb-3">
                    <input type="text" name="search" class="form-control" placeholder="Search tasks" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" />
                </div>
                <div class="mb-3 d-flex justify-content-between">
                    <div class="">
                        <nav aria-label="Page navigation">
                            <ul class="pagination">
                                <li class="page-item <?= $current_page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link text-success" href="?search=<?= urlencode($_GET['search'] ?? '') ?>&tasks_per_page=<?= $tasks_per_page ?>&page=<?= $current_page - 1 ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>

                                <li class="page-item <?= $current_page == 1 ? 'active' : '' ?>">
                                    <a class="page-link text-success" href="?search=<?= urlencode($_GET['search'] ?? '') ?>&tasks_per_page=<?= $tasks_per_page ?>&page=1">1</a>
                                </li>

                                <?php if ($current_page > 3): ?>
                                    <li class="page-item <?= $current_page == 2 ? 'active' : '' ?>">
                                        <a class="page-link text-success" href="?search=<?= urlencode($_GET['search'] ?? '') ?>&tasks_per_page=<?= $tasks_per_page ?>&page=2">2</a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($total_pages > 4): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>

                                <?php if ($total_pages > 1): ?>
                                    <?php if ($current_page < $total_pages): ?>
                                        <li class="page-item <?= $current_page == $total_pages ? 'active' : '' ?>">
                                            <a class="page-link text-success" href="?search=<?= urlencode($_GET['search'] ?? '') ?>&tasks_per_page=<?= $tasks_per_page ?>&page=<?= $total_pages ?>"><?= $total_pages ?></a>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <li class="page-item <?= $current_page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link text-success" href="?search=<?= urlencode($_GET['search'] ?? '') ?>&tasks_per_page=<?= $tasks_per_page ?>&page=<?= $current_page + 1 ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>

                    </div>
                    <div class="text-center text-success fs-5">
                        <span>Page <?= $current_page ?> of <?= $total_pages ?></span>
                    </div>
                    <div class="col-1">
                        <select name="tasks_per_page" class="form-select" onchange="this.form.submit()">
                            <option value="<?= $total_tasks ?>" <?= $tasks_per_page == $total_tasks ? 'selected' : '' ?>>All</option>
                            <option value="1" <?= $tasks_per_page == 1 ? 'selected' : '' ?>>1</option>
                            <option value="5" <?= $tasks_per_page == 5 ? 'selected' : '' ?>>5</option>
                            <option value="10" <?= $tasks_per_page == 10 ? 'selected' : '' ?>>10</option>
                            <option value="15" <?= $tasks_per_page == 15 ? 'selected' : '' ?>>15</option>
                            <option value="20" <?= $tasks_per_page == 20 ? 'selected' : '' ?>>20</option>
                        </select>
                    </div>


                </div>
            </form>




            <table class="table table-bordered border-dark">
                <thead>
                    <tr>
                        <th>Task Name</th>
                        <th>Status</th>
                        <th>Action</th>
                        <th>Comments</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $to_do_list->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['task_name']) ?></td>
                            <td>
                                <?php
                                $status = htmlspecialchars($row['task_status']);
                                $badge_class = '';
                                $text_size_class = 'fs-4';
                                $padding_class = 'p-2';

                                switch ($status) {
                                    case 'Completed':
                                        $badge_class = 'bg-success';
                                        break;
                                    case 'In Progress':
                                        $badge_class = 'bg-primary';
                                        break;
                                    case 'On Hold':
                                        $badge_class = 'bg-warning';
                                        break;
                                    case 'Not Started':
                                    default:
                                        $badge_class = 'bg-danger';
                                        break;
                                }
                                ?>
                                <span class="badge <?= $badge_class ?> <?= $text_size_class ?> <?= $padding_class ?>"><?= $status ?></span>
                            </td>
                            <td>
                                <form action="tasks.php" method="POST" style="display:inline;">
                                    <input type="hidden" name="task_id" value="<?= $row['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" class="form-select">
                                        <option value="Not Started" <?= $row['task_status'] == 'Not Started' ? 'selected' : '' ?>>Not Started</option>
                                        <option value="In Progress" <?= $row['task_status'] == 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="On Hold" <?= $row['task_status'] == 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                                        <option value="Completed" <?= $row['task_status'] == 'Completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                </form>
                            </td>
                            <td>
                                <textarea class="form-control" rows=4 readonly style="white-space: pre-wrap;"><?= htmlspecialchars($row['comments']) ?></textarea>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>


        </div>
    </div>

    <?php include '../crud/layout/footer.php'; ?>
</body>

</html>