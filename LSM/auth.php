


<?php
// auth.php
require_once 'database.php';

function login($email, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM librarians WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user'] = $user;
        return true;
    }
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user']);
}

function logout() {
    session_destroy();
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}
?>