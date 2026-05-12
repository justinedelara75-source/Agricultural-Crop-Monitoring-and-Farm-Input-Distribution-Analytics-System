<?php
require "../config/database.php";

$error = "";
$selected_farmer_crop_id = $_GET['farmer_crop_id'] ?? '';
$selected_stage          = $_GET['stage']          ?? 'Planting';
$selected_yield          = $_GET['yield']          ?? '';
$ret_crop     = $_GET['ret_crop']     ?? $_POST['ret_crop']     ?? '';
$ret_barangay = $_GET['ret_barangay'] ?? $_POST['ret_barangay'] ?? '';

$selected_expected_harvest = '';
if ($selected_farmer_crop_id) {
    $fcStmt = $pdo->prepare("SELECT expected_harvest FROM farmer_crops WHERE id = ?");
    $fcStmt->execute([$selected_farmer_crop_id]);
    $fcRow = $fcStmt->fetch();
    if (!empty($fcRow['expected_harvest'])) {
        $selected_expected_harvest = date('Y-m-d', strtotime($fcRow['expected_harvest']));
    }
}

// Fetch farmer info for context bar
$farmerInfo = null;
if ($selected_farmer_crop_id) {
    $fi = $pdo->prepare("
        SELECT f.first_name, f.last_name, f.barangay, fc.area_planted, fc.planting_date, c.crop_name
        FROM farmer_crops fc
        JOIN farmers f ON fc.farmer_id = f.id
        JOIN crops c ON fc.crop_id = c.crop_id
        WHERE fc.id = ?
    ");
    $fi->execute([$selected_farmer_crop_id]);
    $farmerInfo = $fi->fetch(PDO::FETCH_ASSOC);
}

$farmerCrops = $pdo->query("
    SELECT fc.id, c.crop_name, f.first_name, f.last_name
    FROM farmer_crops fc
    JOIN farmers f ON fc.farmer_id = f.id
    JOIN crops c ON fc.crop_id = c.crop_id
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmer_crop_id   = $_POST['farmer_crop_id'] ?? '';
    $date             = $_POST['monitoring_date'] ?? '';
    $stage            = $_POST['stage'] ?? '';
    $expected_harvest = $_POST['expected_harvest'] ?? '';

    // Yield computation from sacks
    $kg_per_sack  = isset($_POST['kg_per_sack'])  ? (float)$_POST['kg_per_sack']  : null;
    $num_of_sacks = isset($_POST['num_of_sacks']) ? (float)$_POST['num_of_sacks'] : null;
    $yield = null;
    if ($stage === 'Harvest' && $kg_per_sack > 0 && $num_of_sacks > 0) {
        $yield = ($kg_per_sack * $num_of_sacks) / 1000; // convert kg → tons
    }

    if (!$farmer_crop_id || !$date || !$stage) {
        $error = "Please fill all required fields.";
    } else {
        $check = $pdo->prepare("SELECT id FROM crop_monitoring WHERE farmer_crop_id = ? ORDER BY id DESC LIMIT 1");
        $check->execute([$farmer_crop_id]);
        $existing = $check->fetch();

        if ($existing) {
            $pdo->prepare("UPDATE crop_monitoring SET monitoring_date=?,stage=?,actual_yield=?,kg_per_sack=?,num_of_sacks=? WHERE id=?")
                ->execute([$date, $stage, $yield, $kg_per_sack, $num_of_sacks, $existing['id']]);
        } else {
            $pdo->prepare("INSERT INTO crop_monitoring (farmer_crop_id,monitoring_date,stage,actual_yield,kg_per_sack,num_of_sacks,created_at) VALUES (?,?,?,?,?,?,NOW())")
                ->execute([$farmer_crop_id, $date, $stage, $yield, $kg_per_sack, $num_of_sacks]);
        }

        if ($stage === 'Flowering' && !empty($expected_harvest)) {
            $pdo->prepare("UPDATE farmer_crops SET expected_harvest=? WHERE id=?")
                ->execute([$expected_harvest, $farmer_crop_id]);
        }

        $qs = http_build_query(array_filter(['crop'=>$ret_crop,'barangay'=>$ret_barangay]));
        header("Location: crop.php" . ($qs ? "?$qs" : ""));
        exit;
    }
}
?>
<?php require "../includes/layout_top.php"; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap');
:root {
    --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;
    --a50:#fffbeb;--a100:#fef3c7;--a400:#fbbf24;--a500:#f59e0b;--a600:#d97706;--a700:#b45309;
    --b100:#dbeafe;--b600:#2563eb;--b700:#1d4ed8;
    --r100:#fee2e2;--r500:#ef4444;--r600:#dc2626;
    --gr50:#f9fafb;--gr100:#f3f4f6;--gr200:#e5e7eb;--gr300:#d1d5db;--gr400:#9ca3af;--gr500:#6b7280;--gr700:#374151;--gr900:#111827;
    --rad:12px;--rad-s:8px;--shadow:0 1px 3px rgba(0,0,0,.07);--shadow-m:0 4px 16px rgba(0,0,0,.08);--tr:.18s cubic-bezier(.4,0,.2,1);
}
.mon-wrap { font-family:'DM Sans',sans-serif; padding:24px 28px 60px; }
.mon-wrap * { box-sizing:border-box; }

.page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; gap:12px; }
.page-header-left { display:flex; align-items:center; gap:12px; }
.page-icon { width:44px; height:44px; background:var(--b100); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.page-header h2 { margin:0 0 2px; font-size:1.25rem; font-weight:700; color:var(--gr900); }
.page-header p { margin:0; font-size:.8rem; color:var(--gr400); }

.btn-back { display:inline-flex; align-items:center; gap:5px; padding:8px 15px; border:1.5px solid var(--gr200); border-radius:var(--rad-s); background:#fff; color:var(--gr700); font-size:.82rem; font-weight:600; text-decoration:none; font-family:'DM Sans',sans-serif; cursor:pointer; transition:var(--tr); }
.btn-back:hover { border-color:var(--b600); color:var(--b700); background:var(--b100); }

/* Context bar */
.ctx-bar { background:#fff; border:1px solid var(--gr200); border-radius:var(--rad); padding:14px 20px; margin-bottom:18px; box-shadow:var(--shadow); display:flex; flex-wrap:wrap; gap:0; }
.ctx-item { padding:6px 20px 6px 0; border-right:1px solid var(--gr200); margin-right:20px; }
.ctx-item:last-child { border-right:none; margin-right:0; }
.ctx-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--gr400); display:block; margin-bottom:1px; }
.ctx-val { font-size:.9rem; font-weight:700; color:var(--gr900); }

/* Form card */
.form-card { background:#fff; border:1px solid var(--gr200); border-radius:var(--rad); box-shadow:var(--shadow-m); overflow:hidden; }
.form-card-head { padding:16px 22px 13px; border-bottom:1px solid var(--gr100); display:flex; align-items:center; gap:8px; }
.form-card-head h5 { margin:0; font-size:.92rem; font-weight:700; color:var(--gr900); }
.form-card-body { padding:22px 24px; }

.sec-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--gr400); margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid var(--gr100); display:flex; align-items:center; gap:6px; }
.sec-divider { border:none; border-top:1px solid var(--gr100); margin:18px 0; }

.form-row-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px; }
.form-row-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:16px; }
@media(max-width:900px){.form-row-4,.form-row-3{grid-template-columns:repeat(2,1fr);}}
@media(max-width:540px){.form-row-4,.form-row-3{grid-template-columns:1fr;}}

.fg { display:flex; flex-direction:column; }
.fl { font-size:.78rem; font-weight:600; color:var(--gr700); margin-bottom:5px; }
.fl-req { color:var(--r500); margin-left:2px; }
.fl-hint { font-size:.72rem; color:var(--gr400); margin-top:3px; display:block; }

.f-input,.f-select {
    width:100%; padding:9px 12px; border:1.5px solid var(--gr200); border-radius:var(--rad-s);
    font-size:.875rem; color:var(--gr900); background:#fff; outline:none; appearance:none;
    font-family:'DM Sans',sans-serif; transition:var(--tr);
}
.f-select { background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 10px center; padding-right:30px; cursor:pointer; }
.f-input:focus,.f-select:focus { border-color:var(--b600); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
.f-input:disabled,.f-select:disabled { background:var(--gr50); color:var(--gr500); cursor:not-allowed; }
.f-input.readonly { background:var(--gr50); color:var(--gr500); cursor:default; font-family:'DM Mono',monospace; font-size:.85rem; }

/* Computed field — same pattern as add_damage_assessment.php */
.computed-wrap { position:relative; }
.computed-wrap .f-input { padding-right:80px; }
.computed-badge {
    position:absolute; right:8px; top:50%; transform:translateY(-50%);
    font-size:.72rem; font-weight:700; padding:2px 8px; border-radius:20px;
    background:var(--g100); color:var(--g700);
    font-family:'DM Mono',monospace;
}
.computed-badge.warn   { background:var(--a100); color:var(--a700); }
.computed-badge.danger { background:var(--r100); color:var(--r600); }

/* Stage pills */
.stage-pills { display:flex; gap:8px; flex-wrap:wrap; }
.stage-pill {
    display:inline-flex; align-items:center; gap:6px;
    padding:8px 16px; border-radius:var(--rad-s); cursor:pointer;
    font-size:.85rem; font-weight:600; border:1.5px solid var(--gr200);
    background:#fff; color:var(--gr500); transition:var(--tr); user-select:none;
}
.stage-pill:hover:not(.disabled-pill) { border-color:var(--b600); color:var(--b700); background:var(--b100); }
.stage-pill.selected { border-color:var(--b600); background:var(--b600); color:#fff; }
.stage-pill.disabled-pill { opacity:.35; cursor:not-allowed; }
input[name="stage"] { display:none; }

/* Conditional section */
.cond-section { background:var(--g50); border:1px solid var(--g200); border-radius:var(--rad); padding:18px 20px; margin-top:4px; animation:fadeIn .2s ease; }
@keyframes fadeIn { from{opacity:0;transform:translateY(-4px)} to{opacity:1;transform:translateY(0)} }
.cond-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--g700); margin-bottom:14px; display:flex; align-items:center; gap:6px; padding-bottom:8px; border-bottom:1px solid var(--g200); }

/* Yield summary box */
.yield-summary {
    display:flex; align-items:center; gap:10px;
    background:#fff; border:1.5px solid var(--g200); border-radius:var(--rad-s);
    padding:12px 16px; margin-top:14px;
}
.yield-summary-icon { font-size:1.4rem; }
.yield-summary-text { flex:1; }
.yield-summary-text .label { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--g700); }
.yield-summary-text .value { font-size:1.1rem; font-weight:700; color:var(--g800); font-family:'DM Mono',monospace; }
.yield-summary-text .sub { font-size:.72rem; color:var(--gr400); }

/* Actions */
.da-actions { display:flex; align-items:center; gap:10px; margin-top:24px; padding-top:18px; border-top:1px solid var(--gr100); }
.btn-save { display:inline-flex; align-items:center; gap:6px; padding:10px 24px; background:var(--g600); color:#fff; border:none; border-radius:var(--rad-s); font-size:.875rem; font-weight:700; cursor:pointer; font-family:'DM Sans',sans-serif; transition:var(--tr); }
.btn-save:hover { background:var(--g700); transform:translateY(-1px); box-shadow:0 4px 12px rgba(22,163,74,.3); }
.btn-cancel-btn { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; background:#fff; color:var(--gr500); border:1.5px solid var(--gr200); border-radius:var(--rad-s); font-size:.875rem; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:var(--tr); }
.btn-cancel-btn:hover { border-color:var(--gr400); color:var(--gr900); }

.da-alert { display:flex; gap:8px; padding:10px 14px; border-radius:var(--rad-s); font-size:.83rem; margin-bottom:16px; }
.da-alert.err { background:var(--r100); border:1px solid #fca5a5; color:var(--r600); }
</style>

<div class="mon-wrap">

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-icon">🌱</div>
            <div>
                <h2>Monitor Crop</h2>
                <p>Update crop growth stage and yield for this monitoring record.</p>
            </div>
        </div>
        <button type="button" class="btn-back" data-bs-toggle="modal" data-bs-target="#cancelModal">← Cancel</button>
    </div>

    <?php if ($farmerInfo): ?>
    <div class="ctx-bar">
        <div class="ctx-item"><span class="ctx-label">👤 Farmer</span><span class="ctx-val"><?= htmlspecialchars($farmerInfo['last_name'].', '.$farmerInfo['first_name']) ?></span></div>
        <div class="ctx-item"><span class="ctx-label">📍 Barangay</span><span class="ctx-val"><?= htmlspecialchars($farmerInfo['barangay']) ?></span></div>
        <div class="ctx-item"><span class="ctx-label">🌱 Crop</span><span class="ctx-val"><?= htmlspecialchars($farmerInfo['crop_name']) ?></span></div>
        <div class="ctx-item"><span class="ctx-label">📏 Area Planted</span><span class="ctx-val"><?= number_format($farmerInfo['area_planted'],2) ?> ha</span></div>
        <div class="ctx-item"><span class="ctx-label">📅 Planting Date</span><span class="ctx-val"><?= date('M d, Y', strtotime($farmerInfo['planting_date'])) ?></span></div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="da-alert err"><span>⚠️</span><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-card-head"><span>📋</span><h5>Monitoring Details</h5></div>
        <div class="form-card-body">
            <form method="POST">
                <input type="hidden" name="ret_crop"     value="<?= htmlspecialchars($ret_crop) ?>">
                <input type="hidden" name="ret_barangay" value="<?= htmlspecialchars($ret_barangay) ?>">
                <input type="hidden" name="stage"        id="stageHidden" value="<?= htmlspecialchars($selected_stage) ?>">

                <?php if ($selected_farmer_crop_id): ?>
                    <input type="hidden" name="farmer_crop_id" value="<?= $selected_farmer_crop_id ?>">
                <?php endif; ?>

                <!-- ROW 1: Farmer Crop + Monitoring Date + Stage pills -->
                <div class="sec-title">📅 Monitoring Information</div>
                <div class="form-row-3">
                    <div class="fg">
                        <label class="fl">Farmer Crop <span class="fl-req">*</span></label>
                        <select name="<?= $selected_farmer_crop_id ? '_farmer_crop_id_disabled' : 'farmer_crop_id' ?>"
                                class="f-select" <?= $selected_farmer_crop_id ? 'disabled' : 'required' ?>>
                            <option value="">Select Farmer Crop</option>
                            <?php foreach ($farmerCrops as $fc): ?>
                                <option value="<?= $fc['id'] ?>" <?= $selected_farmer_crop_id == $fc['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fc['last_name'].' - '.$fc['crop_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl">Monitoring Date <span class="fl-req">*</span></label>
                        <input type="date" name="monitoring_date" class="f-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="fg">
                        <label class="fl">Growth Stage <span class="fl-req">*</span></label>
                        <div class="stage-pills" id="stagePills">
                            <?php
                            $stageOrder = ['Planting','Vegetative','Flowering','Harvest'];
                            $stageIcons = ['Planting'=>'🌱','Vegetative'=>'🌿','Flowering'=>'🌸','Harvest'=>'🌾'];
                            $curIdx = array_search($selected_stage, $stageOrder);
                            foreach ($stageOrder as $i => $s):
                                $isSelected  = $s === $selected_stage;
                                $isDisabled  = $i < $curIdx;
                            ?>
                                <div class="stage-pill <?= $isSelected ? 'selected' : '' ?> <?= $isDisabled ? 'disabled-pill' : '' ?>"
                                     data-stage="<?= $s ?>" data-disabled="<?= $isDisabled ? '1' : '0' ?>">
                                    <?= $stageIcons[$s] ?> <?= $s ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Conditional: Flowering → Expected Harvest -->
                <div id="floweringSection" class="cond-section" style="display:none;">
                    <div class="cond-title">🌸 Flowering Stage — Set Expected Harvest Date</div>
                    <div class="form-row-3">
                        <div class="fg">
                            <label class="fl">Expected Harvest Date</label>
                            <input type="date" name="expected_harvest" class="f-input"
                                   value="<?= htmlspecialchars($selected_expected_harvest) ?>">
                            <span class="fl-hint">Will be saved to the crop record</span>
                        </div>
                    </div>
                </div>

                <!-- Conditional: Harvest → Sack-based Yield Input -->
                <div id="harvestSection" class="cond-section" style="display:none;">
                    <div class="cond-title">🌾 Harvest Stage — Record Actual Yield</div>

                    <div class="form-row-4">
                        <!-- Input 1: kg per sack -->
                        <div class="fg">
                            <label class="fl">kg per Sack <span class="fl-req">*</span></label>
                            <input type="number" step="0.01" min="0"
                                   name="kg_per_sack" id="kgPerSack"
                                   class="f-input" placeholder="e.g. 50.00">
                            <span class="fl-hint">Weight (kg) of one sack</span>
                        </div>

                        <!-- Input 2: number of sacks -->
                        <div class="fg">
                            <label class="fl">Number of Sacks <span class="fl-req">*</span></label>
                            <input type="number" step="1" min="0"
                                   name="num_of_sacks" id="numOfSacks"
                                   class="f-input" placeholder="e.g. 120">
                            <span class="fl-hint">Total sacks harvested</span>
                        </div>

                        <!-- Computed: Total kg -->
                        <div class="fg">
                            <label class="fl">Total Weight (kg)</label>
                            <div class="computed-wrap">
                                <input type="text" id="totalKgDisplay" class="f-input readonly" value="0.00" readonly>
                                <span class="computed-badge" id="kgBadge">auto</span>
                            </div>
                            <span class="fl-hint">kg/sack × no. of sacks</span>
                        </div>

                        <!-- Computed: Actual Yield in tons -->
                        <div class="fg">
                            <label class="fl">Actual Yield (tons)</label>
                            <div class="computed-wrap">
                                <input type="text" id="yieldTonsDisplay" class="f-input readonly" value="0.000" readonly>
                                <span class="computed-badge" id="tonsBadge">auto</span>
                            </div>
                            <span class="fl-hint">Total kg ÷ 1,000</span>
                        </div>
                    </div>

                    <!-- Yield summary card -->
                    <div class="yield-summary" id="yieldSummary" style="display:none;">
                        <div class="yield-summary-icon">🏆</div>
                        <div class="yield-summary-text">
                            <div class="label">Computed Actual Yield</div>
                            <div class="value" id="yieldSummaryVal">0.000 tons</div>
                            <div class="sub" id="yieldSummaryBreakdown">—</div>
                        </div>
                    </div>

                    <!-- Hidden field to submit computed yield -->
                    <input type="hidden" name="actual_yield" id="actualYieldHidden" value="">
                </div>

                <div class="da-actions">
                    <button type="submit" class="btn-save">💾 Save Monitoring</button>
                    <button type="button" class="btn-cancel-btn" data-bs-toggle="modal" data-bs-target="#cancelModal">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Cancel Confirmation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body py-3">Are you sure? All unsaved changes will be lost.</div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No, Continue</button>
                    <?php $cqs = http_build_query(array_filter(['crop'=>$ret_crop,'barangay'=>$ret_barangay])); ?>
                    <a href="crop.php<?= $cqs ? '?'.$cqs : '' ?>" class="btn btn-danger btn-sm">Yes, Cancel</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const stageHidden     = document.getElementById('stageHidden');
const flowerSection   = document.getElementById('floweringSection');
const harvestSection  = document.getElementById('harvestSection');

const kgPerSackInput  = document.getElementById('kgPerSack');
const numSacksInput   = document.getElementById('numOfSacks');
const totalKgDisplay  = document.getElementById('totalKgDisplay');
const yieldTonsDisplay= document.getElementById('yieldTonsDisplay');
const kgBadge         = document.getElementById('kgBadge');
const tonsBadge       = document.getElementById('tonsBadge');
const yieldSummary    = document.getElementById('yieldSummary');
const yieldSummaryVal = document.getElementById('yieldSummaryVal');
const yieldSummaryBreakdown = document.getElementById('yieldSummaryBreakdown');
const actualYieldHidden = document.getElementById('actualYieldHidden');

// Stage pills
document.querySelectorAll('.stage-pill').forEach(pill => {
    pill.addEventListener('click', function() {
        if (this.dataset.disabled === '1') return;
        document.querySelectorAll('.stage-pill').forEach(p => p.classList.remove('selected'));
        this.classList.add('selected');
        stageHidden.value = this.dataset.stage;
        updateConditional(this.dataset.stage);
    });
});

function updateConditional(stage) {
    flowerSection.style.display  = stage === 'Flowering' ? 'block' : 'none';
    harvestSection.style.display = stage === 'Harvest'   ? 'block' : 'none';
    if (stage !== 'Harvest') {
        // Clear computed values when not on harvest
        actualYieldHidden.value = '';
    }
}

// Yield computation
function computeYield() {
    const kgPerSack  = parseFloat(kgPerSackInput.value)  || 0;
    const numSacks   = parseFloat(numSacksInput.value)   || 0;
    const totalKg    = kgPerSack * numSacks;
    const totalTons  = totalKg / 1000;

    // Update total kg display
    totalKgDisplay.value = totalKg.toFixed(2);
    kgBadge.textContent  = totalKg > 0 ? totalKg.toLocaleString() + ' kg' : 'auto';

    // Update tons display
    yieldTonsDisplay.value = totalTons.toFixed(3);
    tonsBadge.textContent  = totalTons > 0 ? totalTons.toFixed(3) + ' t' : 'auto';

    // Color the badge based on amount
    if (totalTons > 50) {
        tonsBadge.className = 'computed-badge';
    } else if (totalTons > 10) {
        tonsBadge.className = 'computed-badge warn';
    } else {
        tonsBadge.className = 'computed-badge';
    }

    // Update hidden input for submission
    actualYieldHidden.value = totalTons > 0 ? totalTons.toFixed(6) : '';

    // Summary card
    if (kgPerSack > 0 && numSacks > 0) {
        yieldSummary.style.display = 'flex';
        yieldSummaryVal.textContent = totalTons.toFixed(3) + ' tons';
        yieldSummaryBreakdown.textContent =
            numSacks + ' sacks × ' + kgPerSack.toFixed(2) + ' kg/sack = ' +
            totalKg.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}) + ' kg';
    } else {
        yieldSummary.style.display = 'none';
    }
}

kgPerSackInput.addEventListener('input', computeYield);
numSacksInput.addEventListener('input',  computeYield);

window.addEventListener('load', () => {
    updateConditional("<?= addslashes($selected_stage) ?>");
    computeYield();
});
</script>

<?php require "../includes/footer.php"; ?>