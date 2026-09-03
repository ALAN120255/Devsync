<?php
// api/appointments.php — JSON API for appointment actions
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');
$db  = getDB();
$uid = (int)$_SESSION['user_id'];
$role= $_SESSION['user_role'];

$action = sanitize($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {

    // ── Get available time slots for a doctor on a given date ────────
    case 'slots':
        $doctorId = (int)($_GET['doctor_id'] ?? 0);
        $date     = sanitize($_GET['date'] ?? '');
        if (!$doctorId || !$date) { echo json_encode([]); exit; }

        $allSlots = array('08:00','08:30','09:00','09:30','10:00','10:30',
                          '11:00','11:30','12:00','12:30','14:00','14:30',
                          '15:00','15:30','16:00','16:30','17:00');

        $booked = $db->prepare(
            "SELECT appointment_time FROM appointments
             WHERE doctor_id=? AND appointment_date=? AND status NOT IN ('cancelled')"
        );
        $booked->execute([$doctorId, $date]);
        $bookedTimes = $booked->fetchAll(PDO::FETCH_COLUMN, 0);

        $available = array();
        foreach ($allSlots as $slot) {
            $available[] = array(
                'time'      => $slot,
                'label'     => date('g:i A', strtotime($slot)),
                'available' => !in_array($slot, $bookedTimes),
            );
        }
        echo json_encode($available);
        break;

    // ── Get upcoming appointments count ──────────────────────────────
    case 'count':
        $col = $role === 'doctor' ? 'doctor_id' : 'patient_id';
        $cnt = $db->prepare("SELECT COUNT(*) FROM appointments WHERE $col=? AND appointment_date>=CURDATE() AND status NOT IN ('cancelled')");
        $cnt->execute([$uid]);
        echo json_encode(['count' => (int)$cnt->fetchColumn()]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
}
