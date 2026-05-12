<?php
require "../config/database.php";

$error = "";
$farmers = $pdo->query("SELECT id, first_name, last_name, barangay, farm_size FROM farmers ORDER BY last_name ASC")->fetchAll();
$crops   = $pdo->query("SELECT crop_id, crop_name FROM crops ORDER BY crop_name ASC")->fetchAll();

$barangays = ['Atate','Aulo','Bagong Buhay','Bo. Militar (Fort Magsaysay)','Caballero (Poblacion)','Caimito (Poblacion)','Doña Josefa','Ganaderia (Poblacion)','Imelda Valley I','Imelda Valley II','Langka','Malate (Poblacion)','Maligaya','Manacnac','Mapaet','Marcos Village','Popolon (Pagas)','Santolan (Poblacion)','Sapang Buho','Singalat'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $farmer_id     = $_POST['farmer_id']     ?? '';
    $crop_id       = $_POST['crop_id']       ?? '';
    $barangay      = $_POST['barangay']      ?? '';
    $area          = $_POST['area']          ?? '';
    $planting_date = $_POST['planting_date'] ?? '';

    if (!$farmer_id || !$crop_id || !$barangay || !$area || !$planting_date) {
        $error = "Please fill all required fields.";
    } elseif (!is_numeric($area) || $area <= 0) {
        $error = "Area must be a valid number greater than 0.";
    } else {
        $farm = $pdo->prepare("SELECT farm_size FROM farmers WHERE id = ?");
        $farm->execute([$farmer_id]);
        $farmRow = $farm->fetch();

        $total = $pdo->prepare("SELECT COALESCE(SUM(area_planted),0) as t FROM farmer_crops WHERE farmer_id = ?");
        $total->execute([$farmer_id]);
        $totalRow = $total->fetch();

        if (($totalRow['t'] + $area) > $farmRow['farm_size']) {
            $remaining = $farmRow['farm_size'] - $totalRow['t'];
            $error = "Total area exceeds farmer's farm size! Remaining: " . number_format($remaining, 2) . " ha";
        } else {
            $pdo->beginTransaction();
            try {
                $pdo->prepare("INSERT INTO farmer_crops (farmer_id,crop_id,barangay,area_planted,planting_date,created_at) VALUES (?,?,?,?,?,NOW())")
                    ->execute([$farmer_id,$crop_id,$barangay,$area,$planting_date]);
                $fc_id = $pdo->lastInsertId();
                $pdo->prepare("INSERT INTO crop_monitoring (farmer_crop_id,monitoring_date,stage,remarks,created_at) VALUES (?,?,?,?,NOW())")
                    ->execute([$fc_id,$planting_date,'Planting','Initial planting record']);
                $pdo->commit();
                header("Location: crop.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Error saving data. Please try again.";
            }
        }
    }
}

// Build farmer data for JS (farm_size per farmer)
$farmerData = [];
foreach ($farmers as $f) {
    $farmerData[$f['id']] = ['barangay' => $f['barangay'], 'farm_size' => $f['farm_size']];
}
?>
<?php require "../includes/layout_top.php"; ?>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
:root {
    --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;
    --a50:#fffbeb;--a100:#fef3c7;--a400:#fbbf24;
    --r100:#fee2e2;--r500:#ef4444;--r600:#dc2626;
    --gr50:#f9fafb;--gr100:#f3f4f6;--gr200:#e5e7eb;--gr300:#d1d5db;--gr400:#9ca3af;--gr500:#6b7280;--gr700:#374151;--gr900:#111827;
    --rad:12px;--rad-s:8px;--shadow:0 1px 3px rgba(0,0,0,.07);--shadow-m:0 4px 16px rgba(0,0,0,.08);--tr:.18s cubic-bezier(.4,0,.2,1);
}
.ac-wrap { font-family:'DM Sans',sans-serif; padding:24px 28px 60px; }
.ac-wrap * { box-sizing:border-box; }

.page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; gap:12px; }
.page-header-left { display:flex; align-items:center; gap:12px; }
.page-icon { width:44px; height:44px; background:var(--g100); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
.page-header h2 { margin:0 0 2px; font-size:1.25rem; font-weight:700; color:var(--gr900); }
.page-header p { margin:0; font-size:.8rem; color:var(--gr400); }
.btn-back { display:inline-flex; align-items:center; gap:5px; padding:8px 15px; border:1.5px solid var(--gr200); border-radius:var(--rad-s); background:#fff; color:var(--gr700); font-size:.82rem; font-weight:600; font-family:'DM Sans',sans-serif; cursor:pointer; transition:var(--tr); }
.btn-back:hover { border-color:var(--g500); color:var(--g700); background:var(--g50); }

/* Farm size meter */
.farm-meter { background:var(--g50); border:1px solid var(--g200); border-radius:var(--rad-s); padding:10px 14px; margin-top:5px; }
.farm-meter-label { font-size:.75rem; font-weight:600; color:var(--g700); margin-bottom:6px; }
.farm-bar { height:6px; background:var(--gr200); border-radius:3px; overflow:hidden; }
.farm-bar-fill { height:100%; border-radius:3px; background:var(--g500); transition:width .4s ease; }
.farm-meter-nums { display:flex; justify-content:space-between; font-size:.72rem; color:var(--gr400); margin-top:4px; font-family:'DM Mono',monospace; }

.form-card { background:#fff; border:1px solid var(--gr200); border-radius:var(--rad); box-shadow:var(--shadow-m); overflow:hidden; }
.form-card-head { padding:16px 22px 13px; border-bottom:1px solid var(--gr100); display:flex; align-items:center; gap:8px; }
.form-card-head h5 { margin:0; font-size:.92rem; font-weight:700; color:var(--gr900); }
.form-card-body { padding:22px 24px; }

.sec-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.07em; color:var(--gr400); margin-bottom:14px; padding-bottom:8px; border-bottom:1px solid var(--gr100); }
.form-row-4 { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:16px; }
.form-row-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:16px; }
@media(max-width:860px){.form-row-4,.form-row-3{grid-template-columns:repeat(2,1fr);}}
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
.f-input:focus,.f-select:focus { border-color:var(--g500); box-shadow:0 0 0 3px rgba(34,197,94,.1); }

.da-actions { display:flex; gap:10px; margin-top:24px; padding-top:18px; border-top:1px solid var(--gr100); }
.btn-save { display:inline-flex; align-items:center; gap:6px; padding:10px 24px; background:var(--g600); color:#fff; border:none; border-radius:var(--rad-s); font-size:.875rem; font-weight:700; cursor:pointer; font-family:'DM Sans',sans-serif; transition:var(--tr); }
.btn-save:hover { background:var(--g700); transform:translateY(-1px); box-shadow:0 4px 12px rgba(22,163,74,.3); }
.btn-cancel-btn { display:inline-flex; align-items:center; gap:6px; padding:10px 20px; background:#fff; color:var(--gr500); border:1.5px solid var(--gr200); border-radius:var(--rad-s); font-size:.875rem; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:var(--tr); }
.btn-cancel-btn:hover { border-color:var(--gr400); color:var(--gr900); }

.da-alert { display:flex; gap:8px; padding:10px 14px; border-radius:var(--rad-s); font-size:.83rem; margin-bottom:16px; }
.da-alert.err { background:var(--r100); border:1px solid #fca5a5; color:var(--r600); }
</style>

<div class="ac-wrap">

    <div class="page-header">
        <div class="page-header-left">
            <div class="page-icon">🌾</div>
            <div>
                <h2>Add Farmer Crop</h2>
                <p>Register a new crop planting record for a farmer.</p>
            </div>
        </div>
        <button type="button" class="btn-back" data-bs-toggle="modal" data-bs-target="#cancelModal">← Cancel</button>
    </div>

    <?php if ($error): ?>
        <div class="da-alert err"><span>⚠️</span><span><?= htmlspecialchars($error) ?></span></div>
    <?php endif; ?>

    <div class="form-card">
        <div class="form-card-head"><span>📋</span><h5>Crop Registration Details</h5></div>
        <div class="form-card-body">
            <form method="POST">

                <!-- ROW 1: Farmer + Crop + Barangay -->
                <div class="sec-title">👤 Farmer & Crop Information</div>
                <div class="form-row-3">
                    <div class="fg">
                        <label class="fl">Farmer <span class="fl-req">*</span></label>
                        <select name="farmer_id" id="farmerSelect" class="f-select" required>
                            <option value="">Select Farmer</option>
                            <?php foreach ($farmers as $f): ?>
                                <option value="<?= $f['id'] ?>" data-barangay="<?= htmlspecialchars($f['barangay']) ?>" data-farmsize="<?= $f['farm_size'] ?>">
                                    <?= htmlspecialchars($f['last_name'].', '.$f['first_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl">Crop <span class="fl-req">*</span></label>
                        <select name="crop_id" class="f-select" required>
                            <option value="">Select Crop</option>
                            <?php foreach ($crops as $c): ?>
                                <option value="<?= $c['crop_id'] ?>"><?= htmlspecialchars($c['crop_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl">Barangay <span class="fl-req">*</span></label>
                        <select name="barangay" id="barangaySelect" class="f-select" required>
                            <option value="">Select Barangay</option>
                            <?php foreach ($barangays as $b): ?>
                                <option value="<?= $b ?>"><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- ROW 2: Area + Planting Date + Farm size meter -->
                <div class="sec-title">📏 Area & Planting Schedule</div>
                <div class="form-row-3">
                    <div class="fg">
                        <label class="fl">Area to Plant (ha) <span class="fl-req">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="area" id="areaInput"
                               class="f-input" placeholder="0.00" required>
                        <span class="fl-hint" id="area-hint" style="display:none;"></span>
                    </div>
                    <div class="fg">
                        <label class="fl">Planting Date <span class="fl-req">*</span></label>
                        <input type="date" name="planting_date" class="f-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="fg">
                        <label class="fl">Farm Size Usage</label>
                        <div class="farm-meter" id="farmMeter" style="display:none;">
                            <div class="farm-meter-label" id="meterLabel">0 / 0 ha used</div>
                            <div class="farm-bar"><div class="farm-bar-fill" id="meterFill" style="width:0%"></div></div>
                            <div class="farm-meter-nums">
                                <span>0 ha</span>
                                <span id="meterMax">0 ha total</span>
                            </div>
                        </div>
                        <span class="fl-hint" style="margin-top:8px;">Auto-shown when farmer is selected</span>
                    </div>
                </div>

                <div class="da-actions">
                    <button type="submit" class="btn-save">💾 Save Crop</button>
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
                    <a href="crop.php" class="btn btn-danger btn-sm">Yes, Cancel</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const farmerData   = <?= json_encode($farmerData) ?>;
const farmerSelect = document.getElementById('farmerSelect');
const barangaySel  = document.getElementById('barangaySelect');
const areaInput    = document.getElementById('areaInput');
const farmMeter    = document.getElementById('farmMeter');
const meterFill    = document.getElementById('meterFill');
const meterLabel   = document.getElementById('meterLabel');
const meterMax     = document.getElementById('meterMax');
const areaHint     = document.getElementById('area-hint');

// Fetch used area per farmer via AJAX (approximate — just show farm_size for now)
farmerSelect.addEventListener('change', function() {
    const d = farmerData[this.value];
    if (!d) { farmMeter.style.display='none'; return; }

    // Auto-select barangay
    for (let opt of barangaySel.options) {
        if (opt.value === d.barangay) { barangaySel.value = d.barangay; break; }
    }

    areaInput.max = d.farm_size;
    areaHint.textContent  = 'Farm size: ' + d.farm_size + ' ha';
    areaHint.style.display = 'block';
    meterMax.textContent  = d.farm_size + ' ha total';
    meterLabel.textContent = '0 / ' + d.farm_size + ' ha registered';
    meterFill.style.width  = '0%';
    farmMeter.style.display = 'block';
});

areaInput.addEventListener('input', function() {
    const d = farmerData[farmerSelect.value];
    if (!d) return;
    const val = parseFloat(this.value) || 0;
    const pct = Math.min((val / d.farm_size) * 100, 100);
    meterFill.style.width      = pct + '%';
    meterFill.style.background = pct > 90 ? '#ef4444' : pct > 70 ? '#f59e0b' : '#22c55e';
    meterLabel.textContent     = val.toFixed(2) + ' / ' + d.farm_size + ' ha';
});
</script>

<?php require "../includes/footer.php"; ?>