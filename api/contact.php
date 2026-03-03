<?php
// ============================================================
//  api/contact.php  — Handle contact form submissions (POST)
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

$fullName = clean($_POST['full_name'] ?? '');
$phone    = clean($_POST['phone']     ?? '');
$email    = clean($_POST['email']     ?? '');
$subject  = clean($_POST['subject']   ?? '');
$message  = clean($_POST['message']   ?? '');
$ip       = $_SERVER['REMOTE_ADDR']   ?? '0.0.0.0';

if (empty($fullName)) jsonResponse(false, 'Full name is required.');
if (empty($message))  jsonResponse(false, 'Message is required.');

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Please enter a valid email address.');
}

// Rate limit: max 5 messages per IP per hour
$pdo  = getDB();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contact_messages WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute([$ip]);
if ((int)$stmt->fetchColumn() >= 5) {
    jsonResponse(false, 'Too many messages sent. Please try again later.');
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO contact_messages (full_name, phone, email, subject, message, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$fullName, $phone, $email, $subject, $message, $ip]);

    jsonResponse(true, 'Your message has been sent! We will get back to you within 24 hours.');

} catch (PDOException $e) {
    error_log('Contact insert error: ' . $e->getMessage());
    jsonResponse(false, 'Something went wrong. Please try again.');
}
