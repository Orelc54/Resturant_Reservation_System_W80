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

// Read the input data
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['id'])) {
    echo json_encode(["error" => "Waitlist ID is required."]);
    exit;
}

$waitlist_id = $input['id'];

// Check if the waitlist entry belongs to the user
$query = "SELECT id FROM waitlist WHERE id = ? AND user_id = ? AND status = 'waiting'";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $waitlist_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(["error" => "Waitlist entry not found or cannot be cancelled."]);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();

// Update the waitlist status to cancelled
$update_query = "UPDATE waitlist SET status = 'cancelled' WHERE id = ? AND user_id = ?";
$update_stmt = $conn->prepare($update_query);
$update_stmt->bind_param('ii', $waitlist_id, $user_id);

if ($update_stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["error" => "Failed to cancel the waitlist."]);
}

$update_stmt->close();
$conn->close();
?>
