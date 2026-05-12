<?php
require "../config/database.php";

$error = "";

$crop_monitoring_id = $_GET['crop_monitoring_id'] ?? $_POST['crop_monitoring_id'] ?? '';
$ret_crop           = $_GET['ret_crop']           ?? $_POST['ret_crop']           ?? '';
$ret_barangay       = $_GET['ret_barangay']       ?? $_POST['ret_barangay']       ?? '';

if (!$crop_monitoring_id) { header("Location: crop.php"); exit; }

$infoStmt = $pdo->prepare("
    SELECT cm.id AS monitoring_id, cm.monitoring_date, cm.stage, cm.actual_yield,
           fc.area_planted, fc.planting_date, fc.expected_harvest,
           f.first_name, f.last_name, f.barangay, c.crop_name
    FROM crop_monitoring cm
    JOIN farmer_crops fc ON cm.farmer_crop_id = fc.id
    JOIN farmers      f  ON fc.farmer_id      = f.id
    JOIN crops        c  ON fc.crop_id        = c.crop_id
    WHERE cm.id = ?
");
$infoStmt->execute([$crop_monitoring_id]);
$info = $infoStmt->fetch(PDO::FETCH_ASSOC);
if (!$info) { header("Location: crop.php"); exit; }

$causes = $pdo->query("SELECT id, cause_name FROM damage_causes ORDER BY cause_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$existStmt = $pdo->prepare("SELECT * FROM damage_assessment WHERE crop_monitoring_id = ? LIMIT 1");
$existStmt->execute([$crop_monitoring_id]);
$existing = $existStmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $totally_damaged   = $_POST['totally_damaged']   ?? 0;
    $partially_damaged = $_POST['partially_damaged'] ?? 0;
    $damage_area       = $totally_damaged + $partially_damaged;
    $yield_before      = $_POST['yield_before']      ?? 0;
    $yield_after       = $_POST['yield_after']       ?? 0;
    $damage_cause_id   = (int)($_POST['damage_cause_id'] ?? 0);
    $cost_of_input     = $_POST['cost_of_input']     ?? 0;

    if (!$damage_cause_id) {
        $error = "Cause of damage is required.";
    } else {
        if ($existing) {
            $pdo->prepare("UPDATE damage_assessment SET damage_area=?,totally_damaged=?,partially_damaged=?,yield_before=?,yield_after=?,damage_cause_id=?,cost_of_input=? WHERE crop_monitoring_id=?")
                ->execute([$damage_area,$totally_damaged,$partially_damaged,$yield_before,$yield_after,$damage_cause_id,$cost_of_input,$crop_monitoring_id]);
        } else {
            $pdo->prepare("INSERT INTO damage_assessment (crop_monitoring_id,damage_area,totally_damaged,partially_damaged,yield_before,yield_after,damage_cause_id,cost_of_input,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())")
                ->execute([$crop_monitoring_id,$damage_area,$totally_damaged,$partially_damaged,$yield_before,$yield_after,$damage_cause_id,$cost_of_input]);
        }
        $qs = http_build_query(array_filter(['crop'=>$ret_crop,'barangay'=>$ret_barangay]));
        header("Location: crop.php" . ($qs ? "?$qs" : ""));
        exit;
    }
}

$val_totally   = $existing['totally_damaged']   ?? '';
$val_partially = $existing['partially_damaged'] ?? '';
$val_before    = $existing['yield_before']      ?? ($info['actual_yield'] ?? '');
$val_after     = $existing['yield_after']       ?? '';
$val_cause_id  = $existing['damage_cause_id']   ?? '';
$val_cost      = $existing['cost_of_input']     ?? '';
?>
<?php require "../includes/layout_top.php"; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap');

:root {
    --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;
    --a50:#fffbeb;--a100:#fef3c7;--a400:#fbbf24;--a500:#f59e0b;--a600:#d97706;--a700:#b45309;
    --r100:#fee2e2;--r500:#ef4444;--r600:#dc2626;
    --gr50:#f9fafb;--gr100:#f3f4f6;--gr200:#e5e7eb;--gr300:#d1d5db;--gr400:#9ca3af;--gr500:#6b7280;--gr700:#374151;--gr900:#111827;
    --rad:12px;--rad-s:8px;--shadow:0 1px 3px rgba(0,0,0,.07),0 1px 2px rgba(0,0,0,.04);--shadow-m:0 4px 16px rgba(0,0,0,.08);--tr:.18s cubic-bezier(.4,0,.2,1);
}

.da-wrap { font-family:'DM Sans',sans-serif; padding:24px 28px 60px; }
.da-wrap * { box-sizing:border-box; }

/* Page header */
.da-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:20px; gap:12px; }
.da-header-left { display:flex; align-items:center; gap:12px; }
.da-header-icon { width:44px; height:44px; background:var(--a100); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.da-header h2 { margin:0 0 2px; font-size:1.25rem; font-weight:700; color:var(--gr900); }
.da-header p  { margin:0; font-size:.8rem; color:var(--gr400); }
.btn-back { display:inline-flex; align-items:center; gap:5px; padding:8px 15px; border:1.5px solid var(--gr200); border-radius:var(--rad-s); background:#fff; color:var(--gr700); font-size:.82rem; font-weight:600; text-decoration:none; transition:var(--tr); white-space:nowrap; font-family:'DM Sans',sans-serif; cursor:pointer; }
.btn-back:hover { border-color:var(--a400); color:var(--a700); background:var(--a50); }

/* Context bar */
.ctx-bar { background:#fff; border:1px solid var(--gr200); border-radius:var(--rad); padding:14px 20px; margin-bottom:18px; box-shadow:var(--shadow); display:flex; flex-wrap:wrap; gap:0; }
.ctx-item { display:flex; align-items:center; gap:7px; padding:6px 20px 6px 0; border-right:1px solid var(--gr200); margin-right:20px; }
.ctx-item:last-child { border-right:none; margin-right:0; }
.ctx-label { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--gr400); display:block; margin-bottom:1px; }
.ctx-val { font-size:.9rem; font-weight:700; color:var(--gr900); }
.ctx-badge { display:inline-flex; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; background:var(--a100); color:var(--a700); }

/* Alert */
.da-alert { display:flex; align-items:flex-start; gap:8px; padding:10px 14px; border-radius:var(--rad-s); font-size:.83rem; margin-bottom:16px; }
.da-alert.info { background:var(--a50); border:1px solid var(--a400); color:var(--a700); }
.da-alert.err  { background:var(--r100); border:1px solid #fca5a5; color:var(--r600); }

/* Main form card */
.da-card { background:#fff; border:1px solid var(--gr200); border-radius:var(--rad); box-shadow:var(--shadow-m); overflow:hidden; }
.da-card-head { padding:16px 22px 13px; border-bottom:1px solid var(--gr100); display:flex; align-items:center; gap:8px; }
.da-card-head h5 { margin:0; font-size:.92rem; font-weight:700; color:var(--gr900); }
.da-card-body { padding:22px 24px; }

/* Section titles */
.sec-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--gr400); margin-bottom:14px; display:flex; align-items:center; gap:6px; padding-bottom:8px; border-bottom:1px solid var(--gr100); }

/* Row grid — 3 or 4 columns */
.form-row-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:16px; }
.form-row-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px; }
.form-row-2 { display:grid; grid-template-columns:repeat(2,1fr); gap:16px; margin-bottom:16px; }
@media(max-width:900px){.form-row-4,.form-row-3{grid-template-columns:repeat(2,1fr);}}
@media(max-width:560px){.form-row-4,.form-row-3,.form-row-2{grid-template-columns:1fr;}}

/* Form fields */
.fg { display:flex; flex-direction:column; }
.fl { font-size:.78rem; font-weight:600; color:var(--gr700); margin-bottom:5px; }
.fl-req { color:var(--r500); margin-left:2px; }
.fl-hint { font-size:.72rem; color:var(--gr400); font-weight:400; display:block; margin-top:3px; }

.f-input, .f-select {
    width:100%; padding:9px 12px; border:1.5px solid var(--gr200); border-radius:var(--rad-s);
    font-size:.875rem; color:var(--gr900); background:#fff; outline:none;
    font-family:'DM Sans',sans-serif; transition:var(--tr); appearance:none;
}
.f-select {
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 10px center; padding-right:30px; cursor:pointer;
}
.f-input:focus,.f-select:focus { border-color:var(--a400); box-shadow:0 0 0 3px rgba(251,191,36,.15); }
.f-input.readonly { background:var(--gr50); color:var(--gr500); cursor:default; font-family:'DM Mono',monospace; font-size:.85rem; }

/* Computed field with colored badge */
.computed-wrap { position:relative; }
.computed-wrap .f-input { padding-right:80px; }
.computed-badge {
    position:absolute; right:8px; top:50%; transform:translateY(-50%);
    font-size:.72rem; font-weight:700; padding:2px 8px; border-radius:20px;
    background:var(--g100); color:var(--g700);
    font-family:'DM Mono',monospace;
}
.computed-badge.warn { background:var(--a100); color:var(--a700); }
.computed-badge.danger { background:var(--r100); color:var(--r600); }

/* Section divider */
.sec-divider { border:none; border-top:1px solid var(--gr100); margin:18px 0; }

/* Actions */
.da-actions { display:flex; align-items:center; gap:10px; margin-top:24px; padding-top:18px; border-top:1px solid var(--gr100); }
.btn-save {
    display:inline-flex; align-items:center; gap:6px; padding:10px 24px;
    background:var(--a500); color:#fff; border:none; border-radius:var(--rad-s);
    font-size:.875rem; font-weight:700; cursor:pointer; font-family:'DM Sans',sans-serif; transition:var(--tr);
}
.btn-save:hover { background:var(--a600); transform:translateY(-1px); box-shadow:0 4px 12px rgba(245,158,11,.35); }
.btn-cancel {
    display:inline-flex; align-items:center; gap:6px; padding:10px 20px;
    background:#fff; color:var(--gr500); border:1.5px solid var(--gr200);
    border-radius:var(--rad-s); font-size:.875rem; font-weight:600; cursor:pointer;
    font-family:'DM Sans',sans-serif; transition:var(--tr); text-decoration:none;
}
.btn-cancel:hover { border-color:var(--gr400); color:var(--gr800); }
</style>

<div class="da-wrap">

    <!-- PAGE HEADER -->
    <div class="da-header">
        <div class="da-header-left">
            <div class="da-header-icon">⚠️</div>
            <div>
                <h2><?= $existing ? 'Update' : 'Record' ?> Damage Assessment</h2>
                <p>Fill in the crop damage details for this monitoring record.</p>
            </div>
        </div>
        <button type="button" class="btn-back" data-bs-toggle="modal" data-bs-target="#cancelModal">
            ← Cancel
        </button>
    </div>

    <!-- CONTEXT BAR -->
    <div class="ctx-bar">
        <div class="ctx-item">
            <div>
                <span class="ctx-label">👤 Farmer</span>
                <span class="ctx-val"><?= htmlspecialchars($info['last_name'].', '.$info['first_name']) ?></span>
            </div>
        </div>
        <div class="ctx-item">
            <div>
                <span class="ctx-label">📍 Barangay</span>
                <span class="ctx-val"><?= htmlspecialchars($info['barangay']) ?></span>
            </div>
        </div>
        <div class="ctx-item">
            <div>
                <span class="ctx-label">🌱 Crop</span>
                <span class="ctx-val"><?= htmlspecialchars($info['crop_name']) ?></span>
            </div>
        </div>
        <div class="ctx-item">
            <div>
                <span class="ctx-label">📏 Area Planted</span>
                <span class="ctx-val"><?= number_format($info['area_planted'],2) ?> ha</span>
            </div>
        </div>
        <div class="ctx-item">
            <div>
                <span class="ctx-label">📅 Stage</span>
                <span class="ctx-badge"><?= htmlspecialchars($info['stage']) ?></span>
            </div>
        </div>
        <div class="ctx-item">
            <div>
                <span class="ctx-label">🗓 Monitoring Date</span>
                <span class="ctx-val"><?= date('M d, Y', strtotime($info['monitoring_date'])) ?></span>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="da-alert err"><span>⚠️</span><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>
    <?php if ($existing): ?>
        <div class="da-alert info"><span>ℹ️</span><span>May existing na damage record para sa crop na ito. I-update mo lang ang values sa ibaba.</span></div>
    <?php endif; ?>

    <!-- FORM CARD -->
    <div class="da-card">
        <div class="da-card-head">
            <span>📋</span>
            <h5>Damage Assessment Details</h5>
        </div>
        <div class="da-card-body">

            <form method="POST">
                <input type="hidden" name="crop_monitoring_id" value="<?= $crop_monitoring_id ?>">
                <input type="hidden" name="ret_crop"           value="<?= htmlspecialchars($ret_crop) ?>">
                <input type="hidden" name="ret_barangay"       value="<?= htmlspecialchars($ret_barangay) ?>">

                <!-- ROW 1: Cause of damage (wide) + Area fields -->
                <div class="sec-title">🌪️ Cause & Area Affected</div>
                <div class="form-row-4">
                    <div class="fg" style="grid-column: span 1;">
                        <label class="fl">Cause of Damage <span class="fl-req">*</span></label>
                        <select name="damage_cause_id" class="f-select" required>
                            <option value="">-- Select Cause --</option>
                            <?php foreach ($causes as $cause): ?>
                                <option value="<?= $cause['id'] ?>" <?= $val_cause_id == $cause['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cause['cause_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl">Totally Damaged (ha)</label>
                        <input type="number" step="0.01" min="0" max="<?= $info['area_planted'] ?>"
                               name="totally_damaged" id="totally_damaged" class="f-input"
                               value="<?= htmlspecialchars($val_totally) ?>" placeholder="0.00">
                        <span class="fl-hint">Max: <?= number_format($info['area_planted'],2) ?> ha</span>
                    </div>
                    <div class="fg">
                        <label class="fl">Partially Damaged (ha)</label>
                        <input type="number" step="0.01" min="0" max="<?= $info['area_planted'] ?>"
                               name="partially_damaged" id="partially_damaged" class="f-input"
                               value="<?= htmlspecialchars($val_partially) ?>" placeholder="0.00">
                    </div>
                    <div class="fg">
                        <label class="fl">Total Area Affected (ha)</label>
                        <div class="computed-wrap">
                            <input type="text" id="total_area_display" class="f-input readonly" value="0.00" readonly>
                            <span class="computed-badge" id="total_badge">auto</span>
                        </div>
                        <span class="fl-hint">Totally + Partially</span>
                    </div>
                </div>

                <hr class="sec-divider">

                <!-- ROW 2: Yield fields -->
                <div class="sec-title">🌾 Yield per Hectare (MT) & Cost</div>
                <div class="form-row-4">
                    <div class="fg">
                        <label class="fl">Before Calamity (MT/ha)</label>
                        <input type="number" step="0.01" min="0"
                               name="yield_before" id="yield_before" class="f-input"
                               value="<?= htmlspecialchars($val_before) ?>" placeholder="0.00">
                    </div>
                    <div class="fg">
                        <label class="fl">After Calamity (MT/ha)</label>
                        <input type="number" step="0.01" min="0"
                               name="yield_after" id="yield_after" class="f-input"
                               value="<?= htmlspecialchars($val_after) ?>" placeholder="0.00">
                    </div>
                    <div class="fg">
                        <label class="fl">Yield Loss (%)</label>
                        <div class="computed-wrap">
                            <input type="text" id="yield_loss_display" class="f-input readonly" value="0%" readonly>
                            <span class="computed-badge" id="loss_badge">auto</span>
                        </div>
                        <span class="fl-hint">From Before &amp; After values</span>
                    </div>
                    <div class="fg">
                        <label class="fl">Cost of Input / ha (₱)</label>
                        <input type="number" step="0.01" min="0"
                               name="cost_of_input" class="f-input"
                               value="<?= htmlspecialchars($val_cost) ?>" placeholder="e.g. 30000.00">
                        <span class="fl-hint">Seeds, fertilizer, labor</span>
                    </div>
                </div>

                <!-- ACTIONS -->
                <div class="da-actions">
                    <button type="submit" class="btn-save">
                        ⚠️ <?= $existing ? 'Update' : 'Save' ?> Damage Assessment
                    </button>
                    <button type="button" class="btn-cancel" data-bs-toggle="modal" data-bs-target="#cancelModal">
                        Cancel
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Cancel Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Cancel Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body py-3">
                    Are you sure you want to cancel? All unsaved changes will be lost.
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No, Continue</button>
                    <?php $cancelQs = http_build_query(array_filter(['crop'=>$ret_crop,'barangay'=>$ret_barangay])); ?>
                    <a href="crop.php<?= $cancelQs ? '?'.$cancelQs : '' ?>" class="btn btn-danger btn-sm">
                        Yes, Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
const totallyInput   = document.getElementById('totally_damaged');
const partiallyInput = document.getElementById('partially_damaged');
const totalDisplay   = document.getElementById('total_area_display');
const totalBadge     = document.getElementById('total_badge');
const beforeInput    = document.getElementById('yield_before');
const afterInput     = document.getElementById('yield_after');
const lossDisplay    = document.getElementById('yield_loss_display');
const lossBadge      = document.getElementById('loss_badge');
const maxArea        = <?= (float)$info['area_planted'] ?>;

function updateTotalArea() {
    const t = parseFloat(totallyInput.value)   || 0;
    const p = parseFloat(partiallyInput.value) || 0;
    const total = t + p;
    totalDisplay.value = total.toFixed(2);
    // Color badge based on severity vs planted area
    const pct = maxArea > 0 ? (total / maxArea) * 100 : 0;
    totalBadge.textContent = (pct > 0 ? pct.toFixed(0)+'%' : 'auto');
    totalBadge.className = 'computed-badge' + (pct > 75 ? ' danger' : pct > 40 ? ' warn' : '');
}

function updateYieldLoss() {
    const before = parseFloat(beforeInput.value) || 0;
    const after  = parseFloat(afterInput.value)  || 0;
    if (before > 0) {
        const loss = ((before - after) / before) * 100;
        lossDisplay.value = loss.toFixed(1) + '%';
        lossBadge.textContent = loss.toFixed(0) + '%';
        lossBadge.className = 'computed-badge' + (loss > 75 ? ' danger' : loss > 40 ? ' warn' : '');
    } else {
        lossDisplay.value = '0%';
        lossBadge.textContent = 'auto';
        lossBadge.className = 'computed-badge';
    }
}

totallyInput.addEventListener('input',   updateTotalArea);
partiallyInput.addEventListener('input', updateTotalArea);
beforeInput.addEventListener('input',    updateYieldLoss);
afterInput.addEventListener('input',     updateYieldLoss);

updateTotalArea();
updateYieldLoss();
</script>

<?php require "../includes/footer.php"; ?>