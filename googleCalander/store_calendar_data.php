<?php
session_start();
require_once 'GoogleCalendarService.php';

header('Content-Type: application/json');

try {
    // Get the JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if ($data) {
        // Store the reservation data in session
        $_SESSION['calendar_reservation'] = $data;
        
        // Get the authorization URL
        $calendar = new GoogleCalendarService();
        $auth_url = $calendar->getAuthUrl();
        
        echo json_encode(['success' => true, 'auth_url' => $auth_url]);
    } else {
        echo json_encode(['error' => 'Invalid data received']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
