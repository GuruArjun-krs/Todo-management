<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task List</title>
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

        .container {
            overflow-y: auto;
            max-height: calc(100vh - 186px);
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        .container::-webkit-scrollbar {
            display: none;
        }


        #loader {
            display: none;
        }
    </style>
</head>

<body>
    <?php include './admin/adminHeader.php'; ?>

    <div class="container mt-4">
        <h1 class="text-center mb-4">All Database</h1>
        <table class="table table-bordered border-dark table-striped" id="task-table">
            <thead class="table-dark">
                <tr class="text-warning">
                    <th>User ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Task Name</th>
                    <th>Task Status</th>
                    <th>Comments</th>
                </tr>
            </thead>
            <tbody id="task-body">
            </tbody>
        </table>
        <div id="loader" class="text-center">
            <img src="https://i.imgur.com/llF5iyg.gif" alt="Loading..." style="width: 100px; height:100px">
        </div>
    </div>

    <footer class="fixed-bottom">
        <?php include './admin/adminFooter.php'; ?>
    </footer>

    <script>
        let page = 0;
        let loading = false;
        const loader = $('#loader');
        const container = $('.container');

        function loadTasks() {
            if (loading) return;
            loading = true;
            loader.show();

            $.ajax({
                url: './infiniteScroll/adminTaskListScroll.php',
                type: 'GET',
                data: {
                    page: page
                },
                success: function(response) {
                    console.log('response', response)
                    const tasks = JSON.parse(response);
                    if (tasks?.length > 0) {
                        tasks.forEach(data => {
                            let taskCount = data?.tasks?.length;
                            console.log('data', data)
                            let html = `<tr>
                                <td rowspan="${taskCount}">${data.user_id}</td>
                                <td rowspan="${taskCount}">${data.first_name}</td>
                                <td rowspan="${taskCount}">${data.last_name}</td>
                                <td>${data.tasks[0]}</td>
                                <td>${data.statuses[0]}</td>
                                <td>${data.comments[0]}</td>
                            </tr>`;
                            for (let i = 1; i < taskCount; i++) {
                                html += `<tr>
                                    <td>${data.tasks[i]}</td>
                                    <td>${data.statuses[i]}</td>
                                    <td>${data.comments[i]}</td>
                                </tr>`;
                            }
                            $('#task-body').append(html);
                        });
                        page++;
                    } else {
                        container.off('scroll', handleScroll);
                    }
                    loading = false;
                    loader.hide();
                }
            });
        }

        function handleScroll() {
            if (container.scrollTop() + container.height() >= container[0].scrollHeight - 100) {
                loadTasks();
            }
        }

        container.on('scroll', handleScroll);

        loadTasks();
    </script>
</body>

</html>