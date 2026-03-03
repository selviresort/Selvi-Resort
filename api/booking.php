<?php
// ============================================================
//  api/booking.php  — Handle booking form submissions (POST)
//  FIXED: Now captures and stores checkout_date for multi-day bookings
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Invalid request method.');
}

// ── Collect & sanitize inputs ────────────────────────────────
$fullName     = clean($_POST['full_name']       ?? '');
$phone        = clean($_POST['phone']           ?? '');
$email        = clean($_POST['email']           ?? '');
$whatsapp     = clean($_POST['whatsapp']        ?? '');
$eventType    = clean($_POST['event_type']      ?? '');
$packageName  = clean($_POST['package_name']    ?? '');
$eventDate    = clean($_POST['event_date']      ?? '');
$checkoutDate = clean($_POST['checkout_date']   ?? '');  // ✅ NEW: Capture checkout_date
$altDate      = clean($_POST['alt_date']        ?? '');
$timeSlot     = clean($_POST['time_slot']       ?? '');
$guestCount   = clean($_POST['guest_count']     ?? '');
$addonService = clean($_POST['addon_service']   ?? '');
$heardFrom    = clean($_POST['heard_from']      ?? '');
$specialReq   = clean($_POST['special_request'] ?? '');

// ── Required field validation ────────────────────────────────
if (empty($fullName))  jsonResponse(false, 'Full name is required.');
if (empty($phone))     jsonResponse(false, 'Phone number is required.');
if (empty($eventType)) jsonResponse(false, 'Event type is required.');

// Validate phone
if (!preg_match('/^[0-9\+\-\s]{7,15}$/', $phone)) {
    jsonResponse(false, 'Please enter a valid phone number.');
}

// Validate email if provided
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Please enter a valid email address.');
}

// ── Validate event date ──────────────────────────────────────
$eventDateForDB = null;
if (!empty($eventDate)) {
    $d = DateTime::createFromFormat('Y-m-d', $eventDate);
    if (!$d || $d->format('Y-m-d') !== $eventDate) {
        jsonResponse(false, 'Invalid event date format.');
    }
    if ($d < new DateTime('today')) {
        jsonResponse(false, 'Event date cannot be in the past.');
    }
    $eventDateForDB = $eventDate;
}

// ── Validate checkout date ───────────────────────────────────
// ✅ NEW: Validate checkout_date if provided
$checkoutDateForDB = null;
if (!empty($checkoutDate)) {
    $d = DateTime::createFromFormat('Y-m-d', $checkoutDate);
    if (!$d || $d->format('Y-m-d') !== $checkoutDate) {
        jsonResponse(false, 'Invalid checkout date format.');
    }
    // Checkout date must be after or equal to event date
    if ($eventDateForDB && $d < new DateTime($eventDateForDB)) {
        jsonResponse(false, 'Checkout date cannot be before event date.');
    }
    $checkoutDateForDB = $checkoutDate;
}

$altDateForDB = null;
if (!empty($altDate)) {
    $d2 = DateTime::createFromFormat('Y-m-d', $altDate);
    if ($d2 && $d2->format('Y-m-d') === $altDate) {
        $altDateForDB = $altDate;
    }
}

// ── Look up package_id from package name ─────────────────────
$packageId = null;
if (!empty($packageName)) {
    $pdo  = getDB();
    $stmt = $pdo->prepare("SELECT id FROM packages WHERE name = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$packageName]);
    $pkg = $stmt->fetch();
    if ($pkg) $packageId = $pkg['id'];
}

// ── Insert booking into database ─────────────────────────────
try {
    $pdo = getDB();
    $ref = generateBookingRef();

    // ✅ FIXED: Added checkout_date to INSERT
    $stmt = $pdo->prepare("
        INSERT INTO bookings
          (booking_ref, full_name, phone, email, whatsapp,
           event_type, package_id, package_name,
           event_date, checkout_date, alt_date, time_slot, guest_count,
           addon_service, heard_from, special_request, status)
        VALUES
          (?, ?, ?, ?, ?,
           ?, ?, ?,
           ?, ?, ?, ?, ?,
           ?, ?, ?, 'new')
    ");

    // ✅ FIXED: Added $checkoutDateForDB to execute() parameters
    $stmt->execute([
        $ref, $fullName, $phone, $email, $whatsapp,
        $eventType, $packageId, $packageName,
        $eventDateForDB, $checkoutDateForDB, $altDateForDB, $timeSlot, $guestCount,
        $addonService, $heardFrom, $specialReq
    ]);

    $bookingId = $pdo->lastInsertId();

    // ✅ NEW: Calculate duration for response
    $duration = '';
    if ($eventDateForDB && $checkoutDateForDB) {
        $nights = (new DateTime($eventDateForDB))->diff(new DateTime($checkoutDateForDB))->days;
        $duration = $nights . ' night' . ($nights != 1 ? 's' : '');
    }

    jsonResponse(true, 'Booking enquiry submitted successfully!', [
        'booking_ref'  => $ref,
        'booking_id'   => $bookingId,
        'full_name'    => $fullName,
        'phone'        => $phone,
        'email'        => $email,
        'event_type'   => $eventType,
        'package_name' => $packageName ?: 'Not selected',
        'event_date'   => $eventDateForDB ? date('d M Y', strtotime($eventDateForDB)) : '',
        'checkout_date'=> $checkoutDateForDB ? date('d M Y', strtotime($checkoutDateForDB)) : '',
        'duration'     => $duration,
        'guest_count'  => $guestCount,
        'time_slot'    => $timeSlot,
        'status'       => 'New',
        'submitted_at' => date('d M Y, h:i A'),
    ]);

} catch (PDOException $e) {
    error_log('Booking insert error: ' . $e->getMessage());
    jsonResponse(false, 'Something went wrong. Please try again or call us directly.');
}
