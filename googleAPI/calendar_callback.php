<?php
session_start();
require_once 'GoogleCalendarService.php';

$calendar = new GoogleCalendarService();

if (isset($_GET['code'])) {
    $calendar->setAccessToken($_GET['code']);
    
    // Redirect back to reservation page
    header('Location: book_a_table.html');
    exit;
}
?>
