<?php
require_once 'includes/config.php';
requireLogin();

$user = currentUser();
$db   = getDB();

// Filters
$search   = sanitize($_GET['search']   ?? '');
$spec     = sanitize($_GET['spec']     ?? '');
$maxFee   = (int)($_GET['max_fee']     ?? 0);
$minRating= (float)($_GET['min_rating']?? 0);
$sortBy   = sanitize($_GET['sort']     ?? 'rating');

$params = array();
$where  = array("u.role = 'doctor'", "dp.is_verified = 1");

if ($search) {
    $where[]  = "(u.full_name LIKE ? OR dp.specialization LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($spec) {
    $where[]  = "dp.specialization = ?";
    $params[] = $spec;
}
if ($maxFee > 0) {
    $where[]  = "dp.consultation_fee <= ?";
    $params[] = $maxFee;
}
if ($minRating > 0) {
    $where[]  = "dp.rating >= ?";
    $params[] = $minRating;
}

$orderMap = array(
    'rating'  => 'dp.rating DESC',
    'fee_asc' => 'dp.consultation_fee ASC',
    'fee_desc'=> 'dp.consultation_fee DESC',
    'name'    => 'u.full_name ASC',
);
$orderSQL = isset($orderMap[$sortBy]) ? $orderMap[$sortBy] : 'dp.rating DESC';

$sql = "SELECT u.*, dp.specialization, dp.consultation_fee, dp.rating,
               dp.total_reviews, dp.years_experience, dp.is_verified
        FROM users u
        JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $orderSQL";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// All specializations for filter
$specs = $db->query("SELECT DISTINCT specialization FROM doctor_profiles WHERE specialization != '' ORDER BY specialization")->fetchAll(PDO::FETCH_COLUMN, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Doctors - TeleCure</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
    .find-layout { display: grid; grid-template-columns: 280px 1fr; gap: 28px; align-items: start; }

    /* Filter panel */
    .filter-panel {
        background: white; border-radius: var(--radius-lg);
        border: 1px solid var(--border); padding: 24px;
        box-shadow: var(--shadow-sm); position: sticky; top: 88px;
    }
    .filter-panel h3 { font-size: 0.95rem; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; color: var(--primary); }
    .filter-section { margin-bottom: 22px; padding-bottom: 22px; border-bottom: 1px solid var(--border); }
    .filter-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .filter-section label { display: block; font-size: 0.82rem; font-weight: 600; color: var(--text-mid); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.8px; }
    .filter-section input[type=range] { width: 100%; accent-color: var(--primary); }
    .range-labels { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-light); margin-top: 4px; }
    .spec-list { display: flex; flex-direction: column; gap: 6px; max-height: 200px; overflow-y: auto; }
    .spec-radio { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; cursor: pointer; padding: 4px 0; }
    .spec-radio input { accent-color: var(--primary); }
    .rating-filter { display: flex; gap: 6px; }
    .rating-btn {
        flex: 1; padding: 6px 4px; border: 1.5px solid var(--border); border-radius: 8px;
        font-size: 0.75rem; font-weight: 600; background: white; cursor: pointer;
        transition: var(--transition); text-align: center; color: var(--text-mid);
    }
    .rating-btn.active, .rating-btn:hover { border-color: var(--primary); background: var(--primary-pale); color: var(--primary); }
    .btn-clear-filters { width: 100%; padding: 9px; border-radius: 50px; border: 1.5px solid var(--border); background: none; font-size: 0.85rem; color: var(--text-light); cursor: pointer; transition: var(--transition); font-family: inherit; }
    .btn-clear-filters:hover { border-color: var(--primary); color: var(--primary); }

    /* Doctors grid */
    .doctors-results-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .results-count { font-size: 0.9rem; color: var(--text-light); }
    .results-count strong { color: var(--text-dark); }
    .sort-select { padding: 8px 14px; border-radius: 50px; border: 1.5px solid var(--border); font-family: inherit; font-size: 0.85rem; color: var(--text-mid); background: white; cursor: pointer; outline: none; }
    .sort-select:focus { border-color: var(--primary); }

    .doctors-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }

    .doc-card {
        background: white; border-radius: var(--radius-lg); border: 1px solid var(--border);
        box-shadow: var(--shadow-sm); overflow: hidden; transition: var(--transition);
        display: flex; flex-direction: column;
    }
    .doc-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: rgba(21,101,192,0.2); }

    .doc-card-header {
        background: linear-gradient(135deg, var(--primary-pale), #EEF7FF);
        padding: 28px 20px 16px; text-align: center; position: relative;
    }
    .doc-card-header::after {
        content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 20px;
        background: white; border-radius: 20px 20px 0 0;
    }
    .doc-avatar { width: 80px; height: 80px; border-radius: 50%; border: 3px solid white; margin: 0 auto 12px; box-shadow: var(--shadow-md); }
    .doc-verified-badge { position: absolute; top: 12px; right: 12px; background: #10B981; color: white; font-size: 0.68rem; font-weight: 700; padding: 3px 8px; border-radius: 50px; display: flex; align-items: center; gap: 4px; }
    .doc-name { font-size: 1rem; font-weight: 700; position: relative; z-index: 1; }
    .doc-spec { display: inline-block; background: rgba(21,101,192,0.1); color: var(--primary); font-size: 0.75rem; font-weight: 600; padding: 3px 10px; border-radius: 50px; margin-top: 4px; position: relative; z-index: 1; }

    .doc-card-body { padding: 16px 20px; flex: 1; }
    .doc-meta-row { display: flex; align-items: center; gap: 8px; font-size: 0.82rem; color: var(--text-mid); margin-bottom: 8px; }
    .doc-meta-row i { color: var(--primary-light); width: 16px; text-align: center; }
    .doc-stars { display: flex; gap: 2px; }
    .doc-stars i { color: #D1D5DB; font-size: 0.75rem; }
    .doc-stars i.lit { color: #F59E0B; }

    .doc-card-footer { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; gap: 8px; }
    .btn-book-doc {
        flex: 1; padding: 10px; border-radius: 50px; text-align: center;
        background: linear-gradient(135deg, var(--primary), var(--primary-mid));
        color: white; font-weight: 600; font-size: 0.85rem; transition: var(--transition);
        display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .btn-book-doc:hover { box-shadow: 0 6px 16px rgba(21,101,192,0.35); color: white; transform: translateY(-1px); }
    .btn-chat-doc {
        width: 40px; height: 40px; border-radius: 50%; border: 1.5px solid var(--border);
        display: flex; align-items: center; justify-content: center; color: var(--text-mid);
        transition: var(--transition); flex-shrink: 0;
    }
    .btn-chat-doc:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-pale); }

    .search-hero {
        background: linear-gradient(135deg, var(--primary), var(--primary-mid));
        border-radius: var(--radius-lg); padding: 28px 32px; margin-bottom: 28px;
        display: flex; align-items: center; gap: 24px;
    }
    .search-hero-input {
        flex: 1; display: flex; background: white; border-radius: 50px;
        overflow: hidden; box-shadow: var(--shadow-md);
    }
    .search-hero-input i { padding: 14px 16px; color: var(--text-light); }
    .search-hero-input input {
        flex: 1; border: none; outline: none; font-family: inherit; font-size: 0.95rem; padding: 14px 0;
    }
    .search-hero-input button {
        background: linear-gradient(135deg, var(--primary), var(--primary-mid));
        color: white; padding: 0 24px; font-weight: 600; font-size: 0.9rem;
        display: flex; align-items: center; gap: 8px; transition: var(--transition);
    }
    .search-hero-input button:hover { opacity: 0.9; }
    .search-hero-text h2 { color: white; font-size: 1.3rem; margin-bottom: 4px; }
    .search-hero-text p { color: rgba(255,255,255,0.75); font-size: 0.88rem; }

    .no-results { text-align: center; padding: 60px 24px; background: white; border-radius: var(--radius-lg); border: 1px solid var(--border); grid-column: 1/-1; }
    .no-results i { font-size: 3rem; color: var(--border); display: block; margin-bottom: 16px; }

    @media(max-width: 900px) { .find-layout { grid-template-columns: 1fr; } .filter-panel { position: static; } }
    </style>
</head>
<body class="dashboard-page">

<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
    <?php include 'includes/topbar.php'; ?>
    <div class="dashboard-body">

        <!-- Search Hero -->
        <div class="search-hero">
            <div class="search-hero-text">
                <h2><i class="fas fa-stethoscope"></i> Find Your Doctor</h2>
                <p>Browse <?= count($doctors) ?> verified specialists ready to help</p>
            </div>
            <form method="GET" action="find_doctors.php" class="search-hero-input">
                <i class="fas fa-search"></i>
                <input type="text" name="search" placeholder="Search by name or specialization..." value="<?= htmlspecialchars($search) ?>">
                <?php if ($spec): ?><input type="hidden" name="spec" value="<?= htmlspecialchars($spec) ?>"><?php endif; ?>
                <?php if ($sortBy): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>"><?php endif; ?>
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>

        <div class="find-layout">
            <!-- Filter Panel -->
            <div class="filter-panel">
                <h3><i class="fas fa-sliders-h"></i> Filters</h3>
                <form method="GET" action="find_doctors.php" id="filterForm">
                    <?php if ($search): ?><input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>"><?php endif; ?>

                    <!-- Specialization -->
                    <div class="filter-section">
                        <label>Specialization</label>
                        <div class="spec-list">
                            <label class="spec-radio">
                                <input type="radio" name="spec" value="" <?= !$spec ? 'checked' : '' ?> onchange="this.form.submit()"> All
                            </label>
                            <?php foreach ($specs as $s): ?>
                            <label class="spec-radio">
                                <input type="radio" name="spec" value="<?= htmlspecialchars($s) ?>" <?= $spec === $s ? 'checked' : '' ?> onchange="this.form.submit()">
                                <?= htmlspecialchars($s) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Max Fee -->
                    <div class="filter-section">
                        <label>Max Fee: <strong id="feeVal">R<?= $maxFee ?: 2000 ?></strong></label>
                        <input type="range" name="max_fee" min="200" max="2000" step="50"
                               value="<?= $maxFee ?: 2000 ?>" id="feeRange"
                               oninput="document.getElementById('feeVal').textContent='R'+this.value"
                               onchange="this.form.submit()">
                        <div class="range-labels"><span>R200</span><span>R2000</span></div>
                    </div>

                    <!-- Min Rating -->
                    <div class="filter-section">
                        <label>Minimum Rating</label>
                        <div class="rating-filter">
                            <?php foreach (array(0, 3, 4, 4.5) as $r): ?>
                            <button type="submit" name="min_rating" value="<?= $r ?>" class="rating-btn <?= (string)$minRating === (string)$r ? 'active' : '' ?>">
                                <?= $r ? $r.'★' : 'Any' ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Sort -->
                    <div class="filter-section">
                        <label>Sort By</label>
                        <select name="sort" class="sort-select" style="width:100%;border-radius:8px;padding:8px;" onchange="this.form.submit()">
                            <option value="rating"   <?= $sortBy==='rating'   ? 'selected':'' ?>>Top Rated</option>
                            <option value="fee_asc"  <?= $sortBy==='fee_asc'  ? 'selected':'' ?>>Fee: Low to High</option>
                            <option value="fee_desc" <?= $sortBy==='fee_desc' ? 'selected':'' ?>>Fee: High to Low</option>
                            <option value="name"     <?= $sortBy==='name'     ? 'selected':'' ?>>Name A–Z</option>
                        </select>
                    </div>

                    <a href="find_doctors.php" class="btn-clear-filters">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                </form>
            </div>

            <!-- Results -->
            <div>
                <div class="doctors-results-header">
                    <div class="results-count">
                        Showing <strong><?= count($doctors) ?></strong> doctor<?= count($doctors) !== 1 ? 's' : '' ?>
                        <?= $spec ? ' in <strong>' . htmlspecialchars($spec) . '</strong>' : '' ?>
                    </div>
                </div>

                <div class="doctors-cards">
                    <?php if (empty($doctors)): ?>
                    <div class="no-results">
                        <i class="fas fa-user-md"></i>
                        <h3>No doctors found</h3>
                        <p>Try adjusting your filters or search terms</p>
                        <a href="find_doctors.php" class="btn-primary" style="margin-top:16px;display:inline-flex;">Clear All Filters</a>
                    </div>
                    <?php else: foreach ($doctors as $doc):
                        $avatarUrl = avatarUrl($doc, 80);
                        $stars = round($doc['rating'] * 2) / 2;
                    ?>
                    <div class="doc-card">
                        <div class="doc-card-header">
                            <?php if ($doc['is_verified']): ?>
                            <div class="doc-verified-badge"><i class="fas fa-check"></i> Verified</div>
                            <?php endif; ?>
                            <img src="<?= $avatarUrl ?>" alt="<?= htmlspecialchars($doc['full_name']) ?>" class="doc-avatar">
                            <div class="doc-name"><?= htmlspecialchars($doc['full_name']) ?></div>
                            <div class="doc-spec"><?= htmlspecialchars($doc['specialization'] ?? 'General') ?></div>
                        </div>
                        <div class="doc-card-body">
                            <div class="doc-meta-row">
                                <i class="fas fa-star"></i>
                                <div class="doc-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= $stars ? 'lit' : '' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span><?= number_format($doc['rating'], 1) ?> (<?= (int)$doc['total_reviews'] ?> reviews)</span>
                            </div>
                            <div class="doc-meta-row">
                                <i class="fas fa-clock"></i>
                                <?= (int)$doc['years_experience'] ?> years experience
                            </div>
                            <div class="doc-meta-row">
                                <i class="fas fa-coins"></i>
                                <strong style="color:var(--primary)">R<?= number_format($doc['consultation_fee'], 0) ?></strong> per consultation
                            </div>
                            <?php if ($doc['bio']): ?>
                            <p style="font-size:0.8rem;color:var(--text-light);margin-top:8px;line-height:1.5;">
                                <?= htmlspecialchars(substr($doc['bio'], 0, 90)) ?>...
                            </p>
                            <?php endif; ?>
                        </div>
                        <div class="doc-card-footer">
                            <a href="appointments.php?action=book&doctor=<?= $doc['id'] ?>" class="btn-book-doc">
                                <i class="fas fa-calendar-plus"></i> Book Appointment
                            </a>
                            <a href="chat.php?user=<?= $doc['id'] ?>" class="btn-chat-doc" title="Message">
                                <i class="fas fa-comment"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="js/dashboard.js"></script>
</body>
</html>
