<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not logged in']));
}

$conn = mysqli_connect("localhost", "orelcn_resturant", "^vb$*.kdGngG", "orelcn_restaurant_db");

if (!$conn) {
    die(json_encode(['error' => 'Connection failed']));
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT r.*, t.name as table_name 
        FROM reservations r 
        JOIN tables t ON r.table_id = t.id 
        WHERE r.user_id = ? 
        AND r.status = 'confirmed'
        ORDER BY r.reservation_date DESC, r.reservation_time DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$reservations = [];
while ($row = $result->fetch_assoc()) {
    $reservations[] = [
        'id' => $row['id'],
        'date' => $row['reservation_date'],
        'time' => $row['reservation_time'],
        'table_name' => $row['table_name'],
        'guests' => $row['guests']
    ];
}

echo json_encode($reservations);
$conn->close();
?>
