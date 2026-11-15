<?php
// api/appointments.php — JSON API for appointment actions
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');
// Availability changes second by second - never let the browser or a proxy cache it.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$db  = getDB();
$uid = (int)$_SESSION['user_id'];
$role= $_SESSION['user_role'];

$action = sanitize($_POST['action'] ?? $_GET['action'] ?? '');

switch ($action) {

    // ── Get available time slots for a doctor on a given date ────────
    case 'slots':
        $doctorId = (int)($_GET['doctor_id'] ?? 0);
        $date     = sanitize($_GET['date'] ?? '');
        $d        = DateTime::createFromFormat('Y-m-d', $date);
        if (!$doctorId || !$d || $d->format('Y-m-d') !== $date) { echo json_encode(['server_time' => date('c'), 'slots' => []]); exit; }

        $allSlots = appointmentSlots();

        $booked = $db->prepare(
            "SELECT appointment_time FROM appointments
             WHERE doctor_id=? AND appointment_date=? AND status NOT IN ('cancelled')"
        );
        $booked->execute([$doctorId, $date]);
        // TIME columns come back as HH:MM:SS - normalise to HH:MM so they match our slot list.
        $bookedTimes = array_map(function ($t) { return substr($t, 0, 5); }, $booked->fetchAll(PDO::FETCH_COLUMN, 0));

        $available = array();
        foreach ($allSlots as $slot) {
            $inPast = strtotime("$date $slot") <= time();
            $available[] = array(
                'time'      => $slot,
                'label'     => date('g:i A', strtotime($slot)),
                'available' => !$inPast && !in_array($slot, $bookedTimes, true),
            );
        }
        // server_time lets the page show the doctor's real clock instead of the visitor's device clock.
        echo json_encode(['server_time' => date('c'), 'now_label' => date('g:i:s A'), 'slots' => $available]);
        break;

    // ── Cheap "has anything changed?" fingerprint of my appointments (polled by appointments.php) ──
    case 'signature':
        $col = $role === 'doctor' ? 'doctor_id' : 'patient_id';
        $q = $db->prepare("SELECT id, status, appointment_date, appointment_time FROM appointments WHERE $col=? ORDER BY id");
        $q->execute([$uid]);
        echo json_encode(['sig' => md5(json_encode($q->fetchAll(PDO::FETCH_NUM))), 'server_time' => date('c')]);
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
