<?php
date_default_timezone_set('Asia/Jerusalem'); 
require '/home/orelcn/public_html/vendor/autoload.php';
use GuzzleHttp\Client;

$conn = mysqli_connect("localhost", "orelcn_resturant", "^vb$*.kdGngG", "orelcn_restaurant_db");
if (!$conn) {
    die('Connection failed');
}

// Initialize Brevo API client
$client = new Client([
    'base_uri' => 'https://api.brevo.com/v3/',
    'headers' => [
        'api-key' => '',
        'Content-Type' => 'application/json',
    ],
]);

// Check for expired notifications
$checkSQL = "SELECT w.*, u.name as customer_name 
             FROM waitlist w
             JOIN users u ON w.user_id = u.id
             WHERE w.status = 'notified'
             AND w.notification_expires < NOW()
             AND NOT EXISTS (
                 SELECT 1 FROM reservations r 
                 WHERE r.reservation_date = w.requested_date
                 AND r.reservation_time = w.requested_time
                 AND r.status = 'confirmed'
                 AND r.created_at > w.notification_time
             )";

$result = $conn->query($checkSQL);

while ($expiredNotification = $result->fetch_assoc()) {
    // Get next person in line
    $nextInLineSQL = "SELECT w.*, u.name as customer_name 
                      FROM waitlist w
                      JOIN users u ON w.user_id = u.id
                      WHERE w.requested_date = ?
                      AND w.requested_time = ?
                      AND w.status = 'waiting'
                      AND w.created_at > ?
                      ORDER BY w.created_at ASC
                      LIMIT 1";
                      
    $stmt = $conn->prepare($nextInLineSQL);
    $stmt->bind_param("sss", 
        $expiredNotification['requested_date'],
        $expiredNotification['requested_time'],
        $expiredNotification['created_at']
    );
    $stmt->execute();
    $nextResult = $stmt->get_result();
    
    if ($nextResult->num_rows > 0) {
        $nextWaitlist = $nextResult->fetch_assoc();
        
        // Update statuses
        $conn->query("UPDATE waitlist SET status = 'expired' WHERE id = " . $expiredNotification['id']);
        $conn->query("UPDATE waitlist 
                     SET status = 'notified',
                         notification_sent = 1,
                         notification_time = NOW(),
                         notification_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                     WHERE id = " . $nextWaitlist['id']);
        
        // Send notification email via Brevo API
        $websiteUrl = "https://orelcn.mtacloud.co.il/";
        $body = [
            'sender' => [
                'email' => 'orelcohen54@gmail.com',
                'name' => 'Restaurant Reservations',
            ],
            'to' => [
                [
                    'email' => $nextWaitlist['notification_email'],
                    'name' => $nextWaitlist['customer_name'],
                ],
            ],
            'subject' => 'Table Available - Action Required Within 15 Minutes',
            'htmlContent' => "Dear {$nextWaitlist['customer_name']},<br><br>
                              Great news! A table has become available for your request:<br>
                              <ul>
                                  <li>Date: {$nextWaitlist['requested_date']}</li>
                                  <li>Time: {$nextWaitlist['requested_time']}</li>
                                  <li>Guests: {$nextWaitlist['guests']}</li>
                              </ul>
                              <br>
                              <strong>Important: You have 15 minutes to book this table before it's offered to the next person in line.</strong><br><br>
                              <a href='{$websiteUrl}' style='background-color: #4CAF50; color: white; padding: 14px 20px; text-decoration: none; border-radius: 4px; display: inline-block;'>Book Your Table Now</a>
                              <br><br>
                              Best regards,<br>Restaurant Reservations Team",
        ];

        try {
            $response = $client->post('smtp/email', ['json' => $body]);
        } catch (Exception $e) {
            error_log('Error sending email: ' . $e->getMessage());
        }
    }
}

$conn->close();
?>
