<?php
session_start();
require '../../vendor/autoload.php';
use GuzzleHttp\Client;

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['error' => 'Not logged in']));
}

$conn = mysqli_connect("localhost", "orelcn_resturant", "^vb$*.kdGngG", "orelcn_restaurant_db");
if (!$conn) {
    die(json_encode(['error' => 'Connection failed']));
}

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);
$reservation_id = $data['reservation_id'];
$user_id = $_SESSION['user_id'];

// First check if reservation belongs to user and is in the future
$check_sql = "SELECT id, reservation_date, reservation_time, table_id, guests FROM reservations 
              WHERE id = ? 
              AND user_id = ? 
              AND reservation_date >= CURDATE()
              AND status = 'confirmed'";

$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $reservation_id, $user_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    die(json_encode(['error' => 'Invalid reservation or not authorized']));
}

$reservation = $result->fetch_assoc();

// Update reservation status
$sql = "UPDATE reservations SET status = 'cancelled' WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $reservation_id, $user_id);

if ($stmt->execute()) {
    // Get all eligible waitlist entries for this time slot
    $waitlistSQL = "SELECT w.*, u.name as customer_name 
                   FROM waitlist w
                   JOIN users u ON w.user_id = u.id 
                   WHERE w.requested_date = ? 
                   AND w.requested_time = ? 
                   AND w.guests <= ? 
                   AND w.status = 'waiting'
                   ORDER BY w.created_at ASC";
                   
    $waitlistStmt = $conn->prepare($waitlistSQL);
    $waitlistStmt->bind_param("ssi", 
        $reservation['reservation_date'], 
        $reservation['reservation_time'], 
        $reservation['guests']
    );
    $waitlistStmt->execute();
    $waitlistResult = $waitlistStmt->get_result();
    
    if ($waitlistResult->num_rows > 0) {
        $firstWaitlist = $waitlistResult->fetch_assoc();
        
        // Update the first waitlist entry status and set notification time
        $updateSQL = "UPDATE waitlist 
                     SET status = 'notified', 
                         notification_sent = 1,
                         notification_time = NOW(),
                         notification_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                     WHERE id = ?";
        $updateStmt = $conn->prepare($updateSQL);
        $updateStmt->bind_param("i", $firstWaitlist['id']);
        $updateStmt->execute();

        // Send notification email
        $websiteUrl = "https://orelcn.mtacloud.co.il/";
        $client = new Client([
            'base_uri' => 'https://api.brevo.com/v3/',
            'headers' => [
                'api-key' => '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',
                'Content-Type' => 'application/json',
            ],
        ]);

        $body = [
            'sender' => [
                'email' => 'orelcohen54@gmail.com',
                'name' => 'Restaurant Reservations',
            ],
            'to' => [
                [
                    'email' => $firstWaitlist['notification_email'],
                    'name' => $firstWaitlist['customer_name'],
                ],
            ],
            'subject' => 'Table Available - Action Required Within 15 Minutes',
            'htmlContent' => "Dear {$firstWaitlist['customer_name']},<br><br>
                              Great news! A table has become available for your request:<br>
                              <ul>
                                  <li>Date: {$reservation['reservation_date']}</li>
                                  <li>Time: {$reservation['reservation_time']}</li>
                                  <li>Guests: {$firstWaitlist['guests']}</li>
                              </ul>
                              <br>
                              <strong>Important: You have 15 minutes to book this table before it's offered to the next person in line.</strong><br><br>
                              <a href='{$websiteUrl}' style='background-color: #4CAF50; color: white; padding: 14px 20px; text-decoration: none; border-radius: 4px; display: inline-block;'>Book Your Table Now</a>
                              <br><br>
                              Best regards,<br>Restaurant Reservations Team",
        ];

        try {
            $response = $client->post('smtp/email', ['json' => $body]);
            $responseBody = json_decode($response->getBody(), true);
            error_log('Email sent successfully: ' . json_encode($responseBody));
        } catch (Exception $e) {
            error_log('Error sending email: ' . $e->getMessage());
        }
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Failed to cancel reservation']);
}

$conn->close();
?>