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

// Modified query to include position calculation
$query = "SELECT w1.id, 
          w1.requested_date AS date, 
          w1.requested_time AS time, 
          w1.guests, 
          w1.preferred_table AS table_type,
          w1.notification_sent,
          w1.notification_time,
          (SELECT COUNT(*) 
           FROM waitlist w2 
           WHERE w2.requested_date = w1.requested_date 
           AND w2.requested_time = w1.requested_time 
           AND w2.created_at <= w1.created_at 
           AND w2.status = 'waiting') as position
          FROM waitlist w1
          WHERE w1.user_id = ? 
          AND w1.status = 'waiting'
          ORDER BY w1.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$waitlists = [];
while ($row = $result->fetch_assoc()) {
    // Calculate estimated wait time based on position and party size
    $baseWaitTime = ($row['position'] - 1) * 15; // 15 minutes per position
    
    // Add additional time for larger parties
    if ($row['guests'] > 4) {
        $baseWaitTime += 10; // Add 10 minutes for larger parties
    }
    
    // Add time based on table type if applicable
    if ($row['table_type'] == 'window' || $row['table_type'] == 'private') {
        $baseWaitTime += 15; // Add 15 minutes for special table requests
    }
    
    $row['estimated_wait'] = $baseWaitTime;
    
    // Format notification time if exists
    if ($row['notification_sent'] && $row['notification_time']) {
        $row['notification_time'] = date('c', strtotime($row['notification_time']));
    }
    
    $waitlists[] = $row;
}

$stmt->close();
$conn->close();

echo json_encode(["waitlists" => $waitlists]);
?>
