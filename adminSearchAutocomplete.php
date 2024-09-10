<?php
include "./dbConnection.php";

if (isset($_GET['query'])) {
    $query = $conn->real_escape_string($_GET['query']);
    $stmt = $conn->prepare("SELECT DISTINCT CONCAT(user.first_name, ' ', user.last_name) AS suggestion
                            FROM user
                            LEFT JOIN todo_list ON user.id = todo_list.user_id
                            WHERE CONCAT(user.first_name, ' ', user.last_name) LIKE ?
                            LIMIT 10");
    $search_param = "%$query%";
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();

    $suggestions = [];
    while ($row = $result->fetch_assoc()) {
        $suggestions[] = $row['suggestion'];
    }

    echo json_encode($suggestions);
}
