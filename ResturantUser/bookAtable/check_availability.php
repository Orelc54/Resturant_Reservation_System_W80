<?php
session_start();
// Database connection
$conn = mysqli_connect("localhost", "orelcn_resturant", "^vb$*.kdGngG", "orelcn_restaurant_db");
if (!$conn) {
    die(json_encode(['error' => 'Connection failed']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = mysqli_real_escape_string($conn, $_POST['date']);
    $time = mysqli_real_escape_string($conn, $_POST['time']);
    $guests = (int)$_POST['guests'];
    
    // query to check only for reservations within the next hour
    $sql = "SELECT t.* 
            FROM tables t 
            WHERE t.capacity >= ?
            AND t.id NOT IN (
                SELECT r.table_id 
                FROM reservations r 
                WHERE r.reservation_date = ?
                AND r.status = 'confirmed'
                AND (
                    -- Check if there's a reservation within the next hour
                    TIME_TO_SEC(TIMEDIFF(r.reservation_time, ?)) BETWEEN 0 AND 3600
                )
            )
            ORDER BY t.capacity";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iss', $guests, $date, $time);
    
    if (!$stmt->execute()) {
        die(json_encode(['error' => 'Failed to check availability']));
    }
    
    $result = $stmt->get_result();
    
    $tables = [];
    while ($row = $result->fetch_assoc()) {
        $table = [
            'id' => $row['id'],
            'name' => $row['name'],
            'capacity' => $row['capacity'],
            'image_url' => $row['image_url'] ?? 'default-table.jpg',
            'features' => json_decode($row['features'] ?? '[]')
        ];
        
        $tables[] = $table;
    }
    
    echo json_encode([
        'tables' => $tables,
        'date' => $date,
        'time' => $time,
        'guests' => $guests
    ]);
}
$conn->close();
?>
