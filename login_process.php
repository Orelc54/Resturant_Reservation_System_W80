<?php
session_start();
$conn = mysqli_connect("localhost:8000", "username", "password", "restaurant_db");

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$action = $_POST['action'] ?? '';

// Process Login
if ($action === 'login') {
    $email = mysqli_real_escape_string($conn, $_POST['loginEmail']);
    $password = $_POST['loginPassword'];
    
    $sql = "SELECT id, password FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            header("Location: home.php");
            exit();
        }
    }
    header("Location: index.html?error=invalid");
}

// Process Registration
if ($action === 'register') {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['registerEmail']);
    $password = password_hash($_POST['registerPassword'], PASSWORD_DEFAULT);
    
    // Check if email already exists
    $check_sql = "SELECT id FROM users WHERE email = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        header("Location: index.html?error=registration");
        exit();
    }
    
    $sql = "INSERT INTO users (name, email, password) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $name, $email, $password);
    
    if ($stmt->execute()) {
        header("Location: index.html?success=registered");
    } else {
        header("Location: index.html?error=registration");
    }
    exit();
}
?>
