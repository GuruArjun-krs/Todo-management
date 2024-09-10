<?php
session_start();
include "./dbConnection.php";

if (!isset($_SESSION['username']) && !isset($_SESSION['role'])) {
    header("Location: adminLogin.php");
    exit();
}

$query = "SELECT task_status, COUNT(*) AS total FROM todo_list GROUP BY task_status";
$result = $conn->query($query);

$tasks = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tasks[$row['task_status']] = $row['total'];
    }
}

$status_filter = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

$query_names = "SELECT t.task_status, t.task_name, CONCAT(u.first_name,' ',u.last_name) AS full_name
                 FROM todo_list t
                 JOIN user u ON t.user_id = u.id";

if ($status_filter) {
    $query_names .= " WHERE t.task_status = ?";
}

if ($search_query) {
    if ($status_filter) {
        $query_names .= " AND CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    } else {
        $query_names .= " WHERE CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    }
}

$stmt = $conn->prepare($query_names);

if ($status_filter && $search_query) {
    $search_query = "%$search_query%";
    $stmt->bind_param("ss", $status_filter, $search_query);
} elseif ($status_filter) {
    $stmt->bind_param("s", $status_filter);
} elseif ($search_query) {
    $search_query = "%$search_query%";
    $stmt->bind_param("s", $search_query);
}

$stmt->execute();
$result_names = $stmt->get_result();

$task_details = [];
if ($result_names->num_rows > 0) {
    while ($row = $result_names->fetch_assoc()) {
        $task_details[$row['task_status']][] = $row;
    }
}

$query_users = "SELECT first_name, last_name, email FROM user";
$result_users = $conn->query($query_users);

$users = [];
if ($result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
}

if (isset($_GET['autocomplete'])) {
    $search_query = $_GET['autocomplete'];

    $query_autocomplete = "SELECT DISTINCT CONCAT(u.first_name, ' ', u.last_name) AS full_name 
                          FROM todo_list t
                          JOIN user u ON t.user_id = u.id";

    if ($status_filter) {
        $query_autocomplete .= " WHERE t.task_status = ?";
        $query_autocomplete .= " AND CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    } else {
        $query_autocomplete .= " WHERE CONCAT(u.first_name, ' ', u.last_name) LIKE ?";
    }

    $stmt_autocomplete = $conn->prepare($query_autocomplete);

    if ($status_filter) {
        $search_query = "%$search_query%";
        $stmt_autocomplete->bind_param("ss", $status_filter, $search_query);
    } else {
        $search_query = "%$search_query%";
        $stmt_autocomplete->bind_param("s", $search_query);
    }

    $stmt_autocomplete->execute();
    $result_autocomplete = $stmt_autocomplete->get_result();

    $suggestions = [];
    while ($row = $result_autocomplete->fetch_assoc()) {
        $suggestions[] = $row['full_name'];
    }

    echo json_encode($suggestions);
    exit();
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Tasks Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url(./asserts/background.avif);
            background-size: cover;
            background-repeat: no-repeat;
        }

        .autocomplete-list {
            position: absolute;
            border: 1px solid #d4d4d4;
            border-bottom: none;
            border-top: none;
            z-index: 1000;
            background-color: white;
            max-height: 200px;
            color: black;
            overflow-y: auto;
            width: calc(100% - 73px);
            top: 37px;
            border-top-left-radius: 2px;
            right: 16.5%;
        }

        .autocomplete-item {
            padding: 8px;
            cursor: pointer;
            border-bottom: 1px solid #d4d4d4;
            background-color: white;
        }

        .autocomplete-item:hover {
            background-color: #f0f0f0;
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

    <div class="container mt-3" style="overflow-y: scroll; max-height: calc(100vh - 140px); -ms-overflow-style: none; scrollbar-width: none; ">
        <div class="row d-flex">
            <div class="col">
                <div class="row">
                    <div class="card text-white bg-dark mb-3">
                        <div class="card-header text-center fs-4 text-warning">Task Analytics</div>
                        <div class="card-body">
                            <h5 class="card-title">Total Tasks: <?= array_sum($tasks) ?></h5>
                            <ul class="list-group">
                                <?php foreach ($tasks as $status => $total): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?= htmlspecialchars($status) ?>
                                        <span class="badge bg-danger rounded-pill"><?= $total ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="card text-white bg-dark mb-3">
                        <div class="card-header text-center fs-4 text-warning">Users Overview</div>
                        <div id="alert-container"></div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title">Total Users: <?= count($users) ?></h5>
                                <div class="d-flex gap-3">
                                    <button class="btn btn-warning text-light" onclick="document.getElementById('csvInput').click();"><i class="fa-solid fa-cloud-arrow-up"></i></button>
                                    <input type="file" id="csvInput" class="file-input" accept=".csv" onchange="importCSV(event)" style="display: none;">
                                    <button class="btn btn-success" onclick="exportCSV()"><i class="fa-solid fa-cloud-arrow-down"></i></button>
                                </div>
                            </div>
                            <ul class="list-group" style="overflow-y: scroll; max-height: calc(100vh - 595px); -ms-overflow-style: none; scrollbar-width: none; ">
                                <?php foreach ($users as $user): ?>
                                    <li class="list-group-item">
                                        <strong><?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?></strong>
                                        <span class="float-end"><?= htmlspecialchars($user['email']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>


            <div class="col" >
                <div class="card text-white bg-dark mb-3">
                    <div class="container mt-3">
                        <form method="GET" action="" class="d-flex gap-5">
                            <div class="input-group  mb-3">
                                <input type="text" class="form-control autocomplete" autocomplete="off" name="search" placeholder="Search by name" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                <button class="btn btn-primary" type="submit">Search</button>
                            </div>
                            <div class="form-group mb-3">
                                <select class="form-select" id="statusFilter" name="status" onchange="this.form.submit()">
                                    <option value="">All</option>
                                    <option value="Not Started" <?= (isset($_GET['status']) && $_GET['status'] == 'Not Started') ? 'selected' : '' ?>>Not Started</option>
                                    <option value="In Progress" <?= (isset($_GET['status']) && $_GET['status'] == 'In Progress') ? 'selected' : '' ?>>In Progress</option>
                                    <option value="On Hold" <?= (isset($_GET['status']) && $_GET['status'] == 'On Hold') ? 'selected' : '' ?>>On Hold</option>
                                    <option value="Completed" <?= (isset($_GET['status']) && $_GET['status'] == 'Completed') ? 'selected' : '' ?>>Completed</option>
                                </select>
                            </div>
                        </form>
                    </div>

                    <div class="card-body" style="overflow-y: scroll; max-height: calc(100vh - 230px); -ms-overflow-style: none; scrollbar-width: none;">
                        <?php if (empty($task_details)): ?>
                            <p class="text-center text-muted fs-4">Oops! No Data Found</p>
                        <?php else: ?>
                            <?php foreach ($task_details as $status => $tasks): ?>
                                <h5 class="card-title text-warning"><?= htmlspecialchars($status) ?></h5>
                                <ul class="list-group mb-3">
                                    <?php foreach ($tasks as $task): ?>
                                        <li class="list-group-item text-dark">
                                            <?= htmlspecialchars($task['task_name']) ?>
                                            <br><small class="text-muted">Assigned to: <?= htmlspecialchars($task['full_name']) ?></small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.querySelector('input[name="search"]');
            const autocompleteList = document.createElement('div');
            autocompleteList.classList.add('autocomplete-list');
            input.parentNode.appendChild(autocompleteList);

            input.addEventListener('input', function() {
                const query = this.value;
                if (query.length > 1) {
                    fetch(`?autocomplete=${encodeURIComponent(query)}`)
                        .then(response => response.json())
                        .then(data => {
                            autocompleteList.innerHTML = '';
                            if (data.length === 0) {
                                const noResult = document.createElement('div');
                                noResult.textContent = 'No matches found';
                                noResult.classList.add('autocomplete-item');
                                autocompleteList.appendChild(noResult);
                            } else {
                                data.forEach(item => {

                                    const div = document.createElement('div');
                                    div.textContent = item;
                                    div.classList.add('autocomplete-item');
                                    div.addEventListener('click', function() {
                                        input.value = item;
                                        autocompleteList.innerHTML = '';
                                    });
                                    autocompleteList.appendChild(div);
                                });
                            }
                        });
                } else {
                    autocompleteList.innerHTML = '';
                }
            });

            document.addEventListener('click', function(event) {
                if (!autocompleteList.contains(event.target) && event.target !== input) {
                    autocompleteList.innerHTML = '';
                }
            });
        });

        function exportCSV() {
            window.location.href = './exportCSV/exportUserList.php';
        }

        function importCSV(event) {
            const file = event.target.files[0];
            const alertContainer = document.getElementById('alert-container');
            alertContainer.innerHTML = '';

            if (file) {
                const formData = new FormData();
                formData.append('csvFile', file);

                fetch('importCSV/user.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.status === 'success') {
                            showAlert('success', result.message);
                            setTimeout(() => {
                                window.location.href = 'adminTasks.php';
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