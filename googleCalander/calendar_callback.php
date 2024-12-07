<?php
session_start();
require_once 'GoogleCalendarService.php';

try {
    $calendar = new GoogleCalendarService();
    
    if (isset($_GET['code'])) {
        // Store the token
        $calendar->setAccessToken($_GET['code']);
        
        // Check if we have the reservation data stored in the session from the confirmation modal
        if (isset($_SESSION['calendar_reservation'])) {
            $result = $calendar->createEvent($_SESSION['calendar_reservation']);
            
            if ($result['success']) {
                // Clear the stored calendar data
                unset($_SESSION['calendar_reservation']);
                
                echo "<script>
                    window.opener.postMessage('calendar_success', '*');
                    window.close();
                </script>";
            } else {
                echo "<script>
                    window.opener.postMessage({error: '" . addslashes($result['error']) . "'}, '*');
                    window.close();
                </script>";
            }
        } else {
            echo "<script>
                window.opener.postMessage({error: 'No reservation data found'}, '*');
                window.close();
            </script>";
        }
    } else {
        echo "<script>
            window.opener.postMessage({error: 'Authorization failed'}, '*');
            window.close();
        </script>";
    }
} catch (Exception $e) {
    echo "<script>
        window.opener.postMessage({error: '" . addslashes($e->getMessage()) . "'}, '*');
        window.close();
    </script>";
}
?>