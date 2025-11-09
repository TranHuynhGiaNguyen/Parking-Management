<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header('Location: index.php'); exit;
}

require 'db_connect.php';

$id = $_POST['id'] ?? 0;
$fullname = trim($_POST['full_name'] ?? '');
$role = trim($_POST['role'] ?? '');
$password = trim($_POST['password'] ?? '');

if ($id && $role) {
  if ($password) {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET full_name=?, role=?, password=? WHERE id=?");
    $stmt->bind_param("sssi", $fullname, $role, $hashed, $id);
  } else {
    $stmt = $conn->prepare("UPDATE users SET full_name=?, role=? WHERE id=?");
    $stmt->bind_param("ssi", $fullname, $role, $id);
  }
  $stmt->execute();
  $stmt->close();
}
header("Location: admin_dashboard.php");
