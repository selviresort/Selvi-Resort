<?php
// ============================================================
//  api/get_booked_dates.php
//  Returns all confirmed/contacted/new booked event dates as JSON
//  Place at: selvi-resort/api/get_booked_dates.php
// ============================================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/../includes/config.php';

try {
    $pdo  = getDB();

    // Fetch all event_date and alt_date that are booked (not cancelled)
    $stmt = $pdo->query("
        SELECT event_date, alt_date 
        FROM bookings 
        WHERE status IN ('new', 'contacted', 'confirmed', 'completed')
          AND event_date IS NOT NULL
          AND event_date >= CURDATE()
    ");
    $rows = $stmt->fetchAll();

    $dates = [];
    foreach ($rows as $row) {
        if (!empty($row['event_date'])) {
            $dates[] = $row['event_date']; // format: YYYY-MM-DD
        }
        // Also block alternate dates that are confirmed
        if (!empty($row['alt_date'])) {
            $dates[] = $row['alt_date'];
        }
    }

    // Remove duplicates
    $dates = array_values(array_unique($dates));

    echo json_encode(['success' => true, 'booked_dates' => $dates]);

} catch (PDOException $e) {
    error_log('get_booked_dates error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'booked_dates' => []]);
}
exit;
