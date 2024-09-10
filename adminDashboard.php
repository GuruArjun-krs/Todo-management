<?php
session_start();

if (!isset($_SESSION['username']) && !isset($_SESSION['role'])) {
    header("Location: adminLogin.php");
    exit();
}

include "./dbConnection.php";

$available_options = [1, 5, 10, 15, 20, 'All'];
$total_users = $conn->query("SELECT COUNT(*) AS total FROM user")->fetch_assoc()['total'];

$available_options = array_filter($available_options, function ($value) use ($total_users) {
    return $value !== 'All' || ($value === 'All' && $total_users > 0);
});



$records_per_page = isset($_GET['records_per_page']) ? $_GET['records_per_page'] : 5;


if ($records_per_page === 'All') {
    $records_per_page = $total_users;
} else {
    $records_per_page = intval($records_per_page);
}

$records_per_page = max($records_per_page, 1);

$current_page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$start_from = ($current_page - 1) * $records_per_page;

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$stmt = $conn->prepare("SELECT COUNT(DISTINCT user.id) AS user_count 
                        FROM user 
                        LEFT JOIN todo_list ON user.id = todo_list.user_id
                        WHERE user.first_name LIKE ? OR user.last_name LIKE ? OR todo_list.task_name LIKE ? OR todo_list.comments LIKE ?");
$search_param = "%$search%";
$stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$user_count = $row['user_count'];
$total_pages = ($records_per_page > 0) ? ceil($user_count / $records_per_page) : 1;

$valid_columns = ['user_id', 'fullname'];
$sort_name = isset($_GET['column']) && in_array($_GET['column'], $valid_columns) ? $_GET['column'] : 'user_id';
$sort_order = isset($_GET['sort']) && in_array($_GET['sort'], ['asc', 'desc']) ? $_GET['sort'] : 'asc';
$next_sort_order = $sort_order === 'asc' ? 'desc' : 'asc';

$sql_users = "SELECT DISTINCT user.id AS user_id, CONCAT(user.first_name, ' ', user.last_name) AS fullname
              FROM user 
              LEFT JOIN todo_list ON user.id = todo_list.user_id 
              WHERE CONCAT(user.first_name, ' ', user.last_name) LIKE ? 
              OR todo_list.task_name LIKE ? 
              OR todo_list.comments LIKE ? 
              ORDER BY $sort_name $sort_order";

if ($records_per_page !== $total_users) {
    $sql_users .= " LIMIT ?, ?";
}

$stmt = $conn->prepare($sql_users);
if ($records_per_page !== $total_users) {
    $stmt->bind_param("sssii", $search_param, $search_param, $search_param, $start_from, $records_per_page);
} else {
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
}
$stmt->execute();
$result_users = $stmt->get_result();

$user_ids = [];
$user_names = [];
while ($row = $result_users->fetch_assoc()) {
    $user_ids[] = $row['user_id'];
    $user_names[$row['user_id']] = $row['fullname'];
}

$records = [];
if (!empty($user_ids)) {
    $insertUserID = implode(',', array_fill(0, count($user_ids), '?'));
    $sql_tasks = "SELECT 
                    user.id AS user_id,
                    todo_list.task_name,
                    todo_list.task_status,
                    todo_list.created_at AS task_created_at,
                    todo_list.updated_at AS task_updated_at,
                    todo_list.comments
                  FROM 
                    user
                  LEFT JOIN 
                    todo_list ON user.id = todo_list.user_id 
                  WHERE 
                    user.id IN ($insertUserID)";

    $stmt = $conn->prepare($sql_tasks);
    $stmt->bind_param(str_repeat('i', count($user_ids)), ...$user_ids);
    $stmt->execute();
    $result_tasks = $stmt->get_result();

    while ($row = $result_tasks->fetch_assoc()) {
        $user_id = $row['user_id'];
        if (!isset($records[$user_id])) {
            $records[$user_id] = [
                'user_id' => $user_id,
                'first_name' => $user_names[$user_id],
                'tasks' => []
            ];
        }
        if ($row['task_name']) {
            $records[$user_id]['tasks'][] = [
                'task_name' => $row['task_name'],
                'task_status' => $row['task_status'],
                'task_created_at' => $row['task_created_at'],
                'task_updated_at' => $row['task_updated_at'],
                'task_comments' => $row['comments']
            ];
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
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet" />
    <script>
        let userIdToDelete = null;

        function showDeleteModal(userId) {
            userIdToDelete = userId;
            console.log('userIdToDelete', userIdToDelete)
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        function deleteUser() {
            if (userIdToDelete !== null) {
                window.location.href = `adminDeleteUser.php?id=${userIdToDelete}`;
            }
        }

        function editUser(userId) {
            if (userId !== null) {
                console.log('user id', userId)
                window.location.href = `adminEditUser.php?id=${userId}`;
            }
        }
    </script>
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

        .autocomplete-items {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-bottom: none;
            border-top: none;
            z-index: 99;
            left: 129px;
            right: 0;
            width: 341px;
        }

        .autocomplete-items div {
            padding: 10px;
            cursor: pointer;
            background-color: #fff;
            border-bottom: 1px solid #d4d4d4;
        }

        .autocomplete-items div:hover {
            background-color: #e9e9e9;
        }

        .autocomplete-active {
            background-color: DodgerBlue !important;
            color: #ffffff;
        }
    </style>
</head>

<body>
    <?php include './admin/adminHeader.php' ?>

    <div class="container mt-5" style="overflow-y: scroll; max-height: calc(100vh - 180px); -ms-overflow-style: none; scrollbar-width: none;">
        <form class="d-flex col-4 mb-3 p-2" method="GET" autocomplete="off">
            <div class="autocomplete" style="width:100%;">
                <input class="form-control me-2" id="search" type="search" name="search" placeholder="Search" aria-label="Search" value="<?= htmlspecialchars($search) ?>">
            </div>
            <button class="btn btn-outline-success" type="submit">Search</button>
        </form>

        <div class="row">
            <div class="col-md-10">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <form method="GET" id="recordsPerPageForm" class="d-flex align-items-center">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <select name="records_per_page" id="records_per_page" class="form-select form-select-sm text-success" onchange="document.getElementById('recordsPerPageForm').submit();">
                            <?php foreach ($available_options as $option): ?>
                                <option value="<?= $option ?>"
                                    <?= ($option == 'All' && $records_per_page == $total_users) || ($option == $records_per_page) ? 'selected' : '' ?>>
                                    <?= $option ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>


                    <div class="page-item">
                        <span class="text-success">Page <?= $current_page ?> of <?= $total_pages ?></span>
                    </div>

                    <nav>
                        <ul class="pagination mb-0">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link text-success" href="?page=<?= $current_page - 1 ?>&records_per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>&column=<?= $sort_name ?>&sort=<?= $sort_order ?>">Prev</a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link text-muted">Prev</span>
                                </li>
                            <?php endif; ?>

                            <li class="page-item <?= ($current_page == 1) ? 'active' : '' ?>">
                                <a class="page-link text-success <?= ($current_page == 1) ? 'bg-success text-white' : '' ?>" href="?page=1&records_per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>">1</a>
                            </li>

                            <?php if ($total_pages > 3): ?>
                                <li class="page-item <?= ($current_page == 2) ? 'active' : '' ?>">
                                    <a class="page-link text-success <?= ($current_page == 2) ? 'bg-success text-white' : '' ?>" href="?page=2&records_per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>">2</a>
                                </li>
                            <?php endif; ?>

                            <?php if ($total_pages > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link text-muted">...</span>
                                </li>
                            <?php endif; ?>

                            <?php if ($total_pages > 1): ?>
                                <li class="page-item <?= ($current_page == $total_pages) ? 'active' : '' ?>">
                                    <a class="page-link text-success <?= ($current_page == $total_pages) ? 'bg-success text-white' : '' ?>" href="?page=<?= $total_pages ?>&records_per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>"><?= $total_pages ?></a>
                                </li>
                            <?php endif; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link text-success" href="?page=<?= $current_page + 1 ?>&records_per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>">Next</a>
                                </li>
                            <?php else: ?>
                                <li class="page-item disabled">
                                    <span class="page-link text-muted">Next</span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <div id="alert-container"></div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="fs-2 fw-bold text-danger">User Tasks Overview</h2>
                    <div class="d-flex gap-3">
                        <div class="d-flex gap-3">
                            <button class="btn btn-warning text-light" onclick="document.getElementById('csvInput').click();">Import CSV <i class="fa-solid fa-cloud-arrow-up"></i></button>
                            <input type="file" id="csvInput" class="file-input" accept=".csv" onchange="importCSV(event)" style="display: none;">
                            <button class="btn btn-success" onclick="exportCSV()">Export CSV <i class="fa-solid fa-cloud-arrow-down"></i></button>
                        </div>
                    </div>
                </div>
                <table class="table table-hover table-bordered border-dark">
                    <thead>
                        <tr>
                            <th class="col-1">
                                <a href="?column=user_id&sort=<?= $next_sort_order ?>&records_per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>" class="text-decoration-none text-dark">
                                    User Id
                                    <?php if ($sort_name === 'user_id' && $sort_order === 'asc'): ?>
                                        <i class="fa-solid fa-caret-up"></i>
                                    <?php elseif ($sort_name === 'user_id' && $sort_order === 'desc'): ?>
                                        <i class="fa-solid fa-caret-down"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="col-2">
                                <a href="?column=fullname&sort=<?= $next_sort_order ?>&records_per_page=<?= $records_per_page ?>&search=<?= urlencode($search) ?>" class="text-decoration-none text-dark">
                                    User Name
                                    <?php if ($sort_name === 'fullname' && $sort_order === 'asc'): ?>
                                        <i class="fa-solid fa-caret-up"></i>
                                    <?php elseif ($sort_name === 'fullname' && $sort_order === 'desc'): ?>
                                        <i class="fa-solid fa-caret-down"></i>
                                    <?php endif; ?>
                                </a>
                            </th>
                            <th class="col-7">Tasks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="3" class="text-center fs-3 ">Oops! No Data Found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?= htmlspecialchars($record['user_id']) ?></td>
                                    <td><?= htmlspecialchars($record['first_name']) ?></td>
                                    <td>
                                        <table class="table border-warning table-hover table-bordered">
                                            <thead>
                                                <tr>
                                                    <th class="col-4">Task Name</th>
                                                    <th class="col-4">Status</th>
                                                    <th class="col-4">Comments</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($record['tasks'])): ?>
                                                    <?php foreach ($record['tasks'] as $task): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($task['task_name']) ?></td>
                                                            <td><?= htmlspecialchars($task['task_status']) ?></td>
                                                            <td><?= htmlspecialchars($task['task_comments']) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="4" class="text-center">Currently No Tasks Assigned</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-2">
                <div class="enrolled-users">
                    <p class="fs-4">Enrolled Users (<span class="fw-bold text-danger"><?= htmlspecialchars($total_users) ?></span>)</p>

                    <table class="table table-dark table-hover table-bordered mt-3">
                        <thead>
                            <tr>
                                <th>Usernames</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_names as $user_id => $name): ?>
                                <tr>
                                    <td class="d-flex justify-content-between">
                                        <?= htmlspecialchars($name) ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($role === 'admin' || $role === 'superadmin'): ?>
                                                <i class="fa-solid fa-user-xmark me-2" title="Delete User" style="cursor: pointer;" onclick="showDeleteModal(<?= htmlspecialchars($user_id) ?>)"></i>
                                            <?php endif; ?>
                                            <i class="fa-solid fa-user-pen" title="Edit User" style="cursor: pointer;" onclick="editUser(<?= htmlspecialchars($user_id) ?>)"></i>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

    <footer class="fixed-bottom">
        <?php include './admin/adminFooter.php'; ?>
    </footer>

    <script>
        function autocomplete(inp) {
            inp.addEventListener("input", function() {
                var val = this.value;
                if (!val) {
                    return false;
                }

                var xhr = new XMLHttpRequest();
                xhr.open("GET", "adminSearchAutocomplete.php?query=" + val, true);
                xhr.onreadystatechange = function() {
                    if (xhr.readyState == 4 && xhr.status == 200) {
                        var arr = JSON.parse(xhr.responseText);
                        console.log('responseText', arr)

                        closeAllLists();
                        if (!arr.length) {
                            return false;
                        }

                        var a, b;
                        a = document.createElement("DIV");
                        a.setAttribute("id", inp.id + "autocomplete-list");
                        a.setAttribute("class", "autocomplete-items");
                        inp.parentNode.appendChild(a);

                        for (var i = 0; i < arr.length; i++) {
                            b = document.createElement("DIV");
                            b.innerHTML = "<strong>" + arr[i].substr(0, val.length) + "</strong>";
                            b.innerHTML += arr[i].substr(val.length);
                            b.innerHTML += "<input type='hidden' value='" + arr[i] + "'>";
                            b.addEventListener("click", function() {
                                inp.value = this.getElementsByTagName("input")[0].value;
                                closeAllLists();
                            });
                            a.appendChild(b);
                        }
                    }
                };
                xhr.send();
            });

            function closeAllLists(elmnt) {
                var x = document.getElementsByClassName("autocomplete-items");
                for (var i = 0; i < x.length; i++) {
                    if (elmnt != x[i] && elmnt != inp) {
                        x[i].parentNode.removeChild(x[i]);
                    }
                }
            }

            document.addEventListener("click", function(e) {
                closeAllLists(e.target);
            });
        }

        autocomplete(document.getElementById("search"));

        function exportCSV() {
            window.location.href = './exportCSV/exportUsersCSV.php';
        }

        function importCSV(event) {
            const file = event.target.files[0];
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = ''; 

            if (file) {
                const formData = new FormData();
                formData.append('csvFile', file);

                fetch('importCSV/user_todolist.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === 'success') {
                            showAlert('success', result.message);
                            setTimeout(() => {
                                window.location.href = 'adminDashboard.php';
                            }, 1000);
                        } else {
                            showAlert('danger', result.message);
                        }
                    })
                    .catch(error => {
                        showAlert('danger', 'An error occurred while importing the file.');
                        console.error('Error:', error);
                    });
            } else {
                showAlert('warning', 'Please select a file to upload.');
                console.error('No file selected.');
            }
        }

        function showAlert(type, message) {
            const alertContainer = document.getElementById('alert-container');
            const alertDiv = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
            alertContainer.innerHTML = alertDiv;
        }

    </script>

</body>

</html>