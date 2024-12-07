<?php
session_start();
require_once 'GoogleCalendarService.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $calendar = new GoogleCalendarService();
    
    // If not authorized, get auth URL
    if (!isset($_SESSION['google_token'])) {
        echo json_encode(['auth_url' => $calendar->getAuthUrl()]);
        exit;
    }

    // Add event to calendar
    $reservation = [
        'date' => $_POST['date'],
        'time' => $_POST['time'],
        'table_name' => $_POST['table_name'],
        'guests' => $_POST['guests']
    ];

    $result = $calendar->createEvent($reservation);
    echo json_encode($result);
}
?>
