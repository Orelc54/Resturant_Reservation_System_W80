<?php
session_start();
header('Content-Type: application/json');

// Database connection
$conn = mysqli_connect("localhost:8000", "username", "password", "restaurant_db");

if (!$conn) {
    die(json_encode(['error' => 'Connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $table_id = (int)$_POST['table_id'];
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $time = mysqli_real_escape_string($conn, $_POST['time']);
    $guests = (int)$_POST['guests'];
    $user_id = $_SESSION['user_id'];
    
    // Check if table is still available
    $check_sql = "SELECT id FROM reservations 
                  WHERE table_id = ? 
                  AND reservation_date = ? 
                  AND reservation_time = ? 
                  AND status = 'confirmed'";
                  
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('iss', $table_id, $date, $time);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        die(json_encode(['error' => 'Table no longer available']));
    }
    
    // Make reservation
    $sql = "INSERT INTO reservations (table_id, user_id, reservation_date, 
            reservation_time, guests, status) 
            VALUES (?, ?, ?, ?, ?, 'confirmed')";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iissi', $table_id, $user_id, $date, $time, $guests);
    
    if ($stmt->execute()) {
        $reservation_id = $stmt->insert_id;

        // Get table details
        $table_sql = "SELECT name FROM tables WHERE id = ?";
        $table_stmt = $conn->prepare($table_sql);
        $table_stmt->bind_param('i', $table_id);
        $table_stmt->execute();
        $table = $table_stmt->get_result()->fetch_assoc();
        
        // Get restaurant details for Google Calendar event
        $restaurant_details = [
            'name' => 'Your Restaurant Name',
            'address' => 'Your Restaurant Address'
        ];
        
        // Store reservation data in session for calendar use
        $_SESSION['last_reservation'] = [
            'id' => $reservation_id,
            'date' => $date,
            'time' => $time,
            'table_name' => $table['name'],
            'guests' => $guests,
            'restaurant' => $restaurant_details
        ];
        
        echo json_encode([
            'success' => true,
            'reservation_id' => $reservation_id,
            'table_name' => $table['name'],
            'date' => $date,
            'time' => $time,
            'guests' => $guests,
            'restaurant' => $restaurant_details
        ]);
    } else {
        echo json_encode(['error' => 'Failed to make reservation']);
    }
}

$conn->close();
?>
