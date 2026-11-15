<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$db = getDB();
$role = $user['role'];
$msg = '';

// Write prescription (doctor only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['write_prescription']) && $role === 'doctor') {
    requireCsrf();
    $patientId  = (int)($_POST['patient_id'] ?? 0);
    $apptId     = (int)($_POST['appointment_id'] ?? 0) ?: null;
    $diagnosis  = sanitize($_POST['diagnosis'] ?? '');
    $notes      = sanitize($_POST['notes'] ?? '');
    $issuedDate = date('Y-m-d');

    $medications = (isset($_POST['medications']) && is_array($_POST['medications'])) ? $_POST['medications'] : [];
    $hasMed = false;
    foreach ($medications as $m) { if (is_array($m) && trim($m['name'] ?? '') !== '') { $hasMed = true; break; } }

    // A doctor may only prescribe for patients they actually have an appointment with,
    // and only once their licence has been verified.
    $ok = $db->prepare("SELECT 1 FROM appointments WHERE doctor_id = ? AND patient_id = ? LIMIT 1");
    $ok->execute([$user['id'], $patientId]);
    $isMyPatient = (bool)$ok->fetchColumn();

    if (empty($user['is_verified'])) {
        $msg = '<div class="alert alert-error">Your account must be verified by an administrator before you can issue prescriptions.</div>';
    } elseif (!$patientId || !$isMyPatient) {
        $msg = '<div class="alert alert-error">Please choose one of your patients.</div>';
    } elseif (!$hasMed) {
        $msg = '<div class="alert alert-error">Add at least one medication.</div>';
    } else {
        if ($apptId) { // appointment must belong to this doctor + patient
            $ac = $db->prepare("SELECT id FROM appointments WHERE id = ? AND doctor_id = ? AND patient_id = ?");
            $ac->execute([$apptId, $user['id'], $patientId]);
            if (!$ac->fetch()) $apptId = null;
        }
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO prescriptions (doctor_id, patient_id, appointment_id, diagnosis, notes, issued_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user['id'], $patientId, $apptId, $diagnosis, $notes, $issuedDate]);
            $rxId = $db->lastInsertId();

            foreach ($medications as $med) {
                if (is_array($med) && trim($med['name'] ?? '') !== '') {
                    $mStmt = $db->prepare("INSERT INTO prescription_medications (prescription_id, medication_name, dosage, frequency, duration, instructions) VALUES (?, ?, ?, ?, ?, ?)");
                    $mStmt->execute([$rxId, sanitize($med['name']), sanitize($med['dosage'] ?? ''), sanitize($med['frequency'] ?? ''), sanitize($med['duration'] ?? ''), sanitize($med['instructions'] ?? '')]);
                }
            }

            // Notify patient
            addNotification($patientId, 'New Prescription', doctorName($user['full_name']) . ' has issued you a prescription.', 'prescription');

            $db->commit();
            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Prescription issued successfully!</div>';
        } catch (Exception $e) {
            $db->rollBack();
            $msg = '<div class="alert alert-error">Error saving prescription.</div>';
        }
    }
}

// Get prescriptions
if ($role === 'patient') {
    $stmt = $db->prepare("SELECT p.*, u.full_name as doctor_name, dp.specialization 
        FROM prescriptions p 
        JOIN users u ON p.doctor_id = u.id 
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE p.patient_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$user['id']]);
} else {
    $stmt = $db->prepare("SELECT p.*, u.full_name as patient_name 
        FROM prescriptions p 
        JOIN users u ON p.patient_id = u.id 
        WHERE p.doctor_id = ? ORDER BY p.created_at DESC");
    $stmt->execute([$user['id']]);
}
$prescriptions = $stmt->fetchAll();

// Get single prescription for view
$viewId = (int)($_GET['view'] ?? 0);
$viewRx = null;
$viewMeds = [];
if ($viewId) {
    $vStmt = $db->prepare("SELECT p.*, u.full_name as doctor_name, u2.full_name as patient_name, dp.specialization, dp.license_number
        FROM prescriptions p 
        JOIN users u ON p.doctor_id = u.id 
        JOIN users u2 ON p.patient_id = u2.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE p.id = ? AND (p.patient_id = ? OR p.doctor_id = ?)");
    $vStmt->execute([$viewId, $user['id'], $user['id']]);
    $viewRx = $vStmt->fetch();
    if ($viewRx) {
        $mStmt = $db->prepare("SELECT * FROM prescription_medications WHERE prescription_id = ?");
        $mStmt->execute([$viewId]);
        $viewMeds = $mStmt->fetchAll();
    }
}

// Get patients for doctor
$patients = [];
if ($role === 'doctor') {
    $pStmt = $db->prepare("SELECT DISTINCT u.id, u.full_name FROM users u 
        JOIN appointments a ON a.patient_id = u.id 
        WHERE a.doctor_id = ? ORDER BY u.full_name");
    $pStmt->execute([$user['id']]);
    $patients = $pStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link rel="stylesheet" href="css/prescriptions.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Crimson+Pro:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="dashboard-page">

<?php include 'includes/sidebar.php'; ?>

<div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="dashboard-body">
        <div class="page-header">
            <div>
                <h1>Prescriptions</h1>
                <p><?= $role === 'doctor' ? 'Manage prescriptions for your patients' : 'Your prescription history' ?></p>
            </div>
            <?php if ($role === 'doctor'): ?>
            <button class="btn-primary" onclick="document.getElementById('writeRxModal').style.display='flex'">
                <i class="fas fa-pen-nib"></i> Write Prescription
            </button>
            <?php endif; ?>
        </div>

        <?= $msg ?>

        <?php if ($viewRx): ?>
        <!-- Prescription Detail View -->
        <div class="rx-detail-wrapper">
            <div class="rx-paper" id="rxPrint">
                <div class="rx-letterhead">
                    <div class="rx-logo">
                        <svg viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg" width="50">
                            <circle cx="30" cy="30" r="28" fill="url(#rxGrad)" />
                            <rect x="25" y="15" width="10" height="30" rx="3" fill="white"/>
                            <rect x="15" y="25" width="30" height="10" rx="3" fill="white"/>
                            <defs><linearGradient id="rxGrad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" style="stop-color:#1565C0"/><stop offset="100%" style="stop-color:#42A5F5"/></linearGradient></defs>
                        </svg>
                        <span>TeleCure</span>
                    </div>
                    <div class="rx-doctor-info">
                        <h3><?= htmlspecialchars($viewRx['doctor_name']) ?></h3>
                        <p><?= htmlspecialchars($viewRx['specialization'] ?? 'General Practitioner') ?></p>
                        <small>License: <?= htmlspecialchars($viewRx['license_number'] ?? 'N/A') ?></small>
                    </div>
                </div>
                <div class="rx-divider"></div>
                <div class="rx-patient-info">
                    <div class="rx-field">
                        <label>Patient Name:</label>
                        <span><?= htmlspecialchars($viewRx['patient_name']) ?></span>
                    </div>
                    <div class="rx-field">
                        <label>Date:</label>
                        <span><?= date('F j, Y', strtotime($viewRx['issued_date'])) ?></span>
                    </div>
                    <div class="rx-field">
                        <label>Prescription #:</label>
                        <span>TC-<?= str_pad($viewRx['id'], 6, '0', STR_PAD_LEFT) ?></span>
                    </div>
                </div>
                <div class="rx-diagnosis">
                    <h4>Diagnosis / Condition:</h4>
                    <p><?= htmlspecialchars($viewRx['diagnosis'] ?? 'See notes below') ?></p>
                </div>
                <div class="rx-symbol"><span>℞</span></div>
                <div class="rx-medications">
                    <h4>Prescribed Medications:</h4>
                    <?php foreach ($viewMeds as $i => $med): ?>
                    <div class="rx-med-item">
                        <div class="rx-med-num"><?= $i+1 ?></div>
                        <div class="rx-med-detail">
                            <strong><?= htmlspecialchars($med['medication_name']) ?></strong>
                            <div class="rx-med-meta">
                                <span><i class="fas fa-pills"></i> <?= htmlspecialchars($med['dosage']) ?></span>
                                <span><i class="fas fa-clock"></i> <?= htmlspecialchars($med['frequency']) ?></span>
                                <span><i class="fas fa-calendar-day"></i> <?= htmlspecialchars($med['duration']) ?></span>
                            </div>
                            <?php if ($med['instructions']): ?>
                            <p class="rx-med-instructions"><i class="fas fa-info-circle"></i> <?= htmlspecialchars($med['instructions']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($viewRx['notes']): ?>
                <div class="rx-notes">
                    <h4>Doctor's Notes:</h4>
                    <p><?= nl2br(htmlspecialchars($viewRx['notes'])) ?></p>
                </div>
                <?php endif; ?>
                <div class="rx-footer">
                    <div class="rx-signature">
                        <div class="sig-line"></div>
                        <p><?= htmlspecialchars($viewRx['doctor_name']) ?></p>
                        <small>Digital Signature</small>
                    </div>
                    <div class="rx-stamp">
                        <div class="stamp-circle">
                            <span>VERIFIED</span>
                            <small>TeleCure</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="rx-actions-bar">
                <button onclick="window.print()" class="btn-primary"><i class="fas fa-print"></i> Print</button>
                <a href="prescriptions.php" class="btn-outline">← Back to List</a>
            </div>
        </div>
        <?php else: ?>
        <!-- Prescriptions List -->
        <div class="prescriptions-list">
            <?php if (empty($prescriptions)): ?>
            <div class="empty-state">
                <i class="fas fa-file-prescription"></i>
                <h3>No prescriptions yet</h3>
                <?php if ($role === 'doctor'): ?>
                <p>Write your first prescription for a patient</p>
                <button onclick="document.getElementById('writeRxModal').style.display='flex'" class="btn-primary">Write Prescription</button>
                <?php else: ?>
                <p>Your prescriptions from doctors will appear here</p>
                <?php endif; ?>
            </div>
            <?php else: foreach ($prescriptions as $rx): ?>
            <div class="rx-list-card">
                <div class="rx-list-icon">
                    <i class="fas fa-prescription"></i>
                </div>
                <div class="rx-list-info">
                    <h4><?= $role === 'patient' ? htmlspecialchars(doctorName($rx['doctor_name'])) : htmlspecialchars($rx['patient_name']) ?></h4>
                    <?php if (isset($rx['specialization'])): ?>
                    <span class="spec-badge"><?= htmlspecialchars($rx['specialization'] ?? '') ?></span>
                    <?php endif; ?>
                    <p class="rx-diagnosis-preview"><?= htmlspecialchars(substr($rx['diagnosis'] ?? 'General prescription', 0, 60)) ?></p>
                </div>
                <div class="rx-list-meta">
                    <span class="rx-date"><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($rx['issued_date'])) ?></span>
                    <span class="rx-id">#TC-<?= str_pad($rx['id'], 6, '0', STR_PAD_LEFT) ?></span>
                    <a href="prescriptions.php?view=<?= $rx['id'] ?>" class="btn-view-rx">
                        <i class="fas fa-eye"></i> View
                    </a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Write Prescription Modal (Doctor only) -->
<?php if ($role === 'doctor'): ?>
<div class="modal-overlay" id="writeRxModal" style="display:<?= ($_GET['action'] ?? '') === 'write' ? 'flex' : 'none' ?>">
    <div class="modal modal-large">
        <div class="modal-header">
            <h3><i class="fas fa-pen-nib"></i> Write Prescription</h3>
            <button onclick="document.getElementById('writeRxModal').style.display='none'"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="prescriptions.php" id="rxForm">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Patient</label>
                    <select name="patient_id" required>
                        <option value="">Select patient...</option>
                        <?php foreach ($patients as $pt): ?>
                        <option value="<?= $pt['id'] ?>"><?= htmlspecialchars($pt['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($patients)): ?>
                    <small class="field-hint"><i class="fas fa-info-circle"></i> Patients will appear here once they book an appointment with you</small>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <label>Diagnosis</label>
                    <input type="text" name="diagnosis" placeholder="e.g., Hypertension Stage 1, Upper Respiratory Infection..." required>
                </div>
                <div class="rx-medications-section">
                    <div class="medications-header">
                        <label>Medications</label>
                        <button type="button" onclick="addMedication()" class="btn-add-med">
                            <i class="fas fa-plus"></i> Add Medication
                        </button>
                    </div>
                    <div id="medicationsContainer">
                        <!-- medication rows added by JS -->
                    </div>
                </div>
                <div class="form-group">
                    <label>Doctor's Notes</label>
                    <textarea name="notes" placeholder="Additional instructions, dietary advice, follow-up notes..." rows="3"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" onclick="document.getElementById('writeRxModal').style.display='none'" class="btn-outline-cancel">Cancel</button>
                    <button type="submit" name="write_prescription" class="btn-primary">
                        <i class="fas fa-file-medical"></i> Issue Prescription
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
let medCount = 0;

function addMedication() {
    medCount++;
    const container = document.getElementById('medicationsContainer');
    const div = document.createElement('div');
    div.className = 'medication-row';
    div.innerHTML = `
        <div class="med-row-header">
            <span>Medication #${medCount}</span>
            <button type="button" onclick="this.closest('.medication-row').remove()" class="btn-remove-med">
                <i class="fas fa-trash"></i>
            </button>
        </div>
        <div class="form-row">
            <div class="form-group">
                <input type="text" name="medications[${medCount}][name]" placeholder="Medication name *" required>
            </div>
            <div class="form-group">
                <input type="text" name="medications[${medCount}][dosage]" placeholder="Dosage (e.g. 500mg)">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <input type="text" name="medications[${medCount}][frequency]" placeholder="Frequency (e.g. Twice daily)">
            </div>
            <div class="form-group">
                <input type="text" name="medications[${medCount}][duration]" placeholder="Duration (e.g. 7 days)">
            </div>
        </div>
        <div class="form-group">
            <input type="text" name="medications[${medCount}][instructions]" placeholder="Special instructions (e.g. Take with food)">
        </div>
    `;
    container.appendChild(div);
}

// Add first medication by default
addMedication();

document.getElementById('writeRxModal')?.addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
</body>
</html>
