<?php
session_start();
header('Content-Type: application/json');

$conn = mysqli_connect("localhost", "orelcn_resturant", "^vb$*.kdGngG", "orelcn_restaurant_db");

if (!$conn) {
    die(json_encode(['error' => 'Connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $time = mysqli_real_escape_string($conn, $_POST['time']);
    $guests = (int)$_POST['guests'];
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $table_type = mysqli_real_escape_string($conn, $_POST['table_type']);
    $user_id = $_SESSION['user_id'];

    // Check if already on waitlist for this date/time
    $check_sql = "SELECT id FROM waitlist 
                  WHERE user_id = ? 
                  AND requested_date = ? 
                  AND requested_time = ? 
                  AND status = 'waiting'";
                  
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iss", $user_id, $date, $time);
    $check_stmt->execute();

    if ($check_stmt->get_result()->num_rows > 0) {
        die(json_encode(['error' => 'You are already on the waitlist for this date and time']));
    }

    // Add to waitlist
    $sql = "INSERT INTO waitlist (user_id, requested_date, requested_time, guests, 
            notification_email, preferred_table, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'waiting')";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ississ", $user_id, $date, $time, $guests, $email, $table_type);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to join waitlist']);
    }
}

$conn->close();
?>