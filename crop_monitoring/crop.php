<?php
require "../config/database.php";
require "../includes/layout_top.php";

$cropList     = $pdo->query("SELECT crop_id, crop_name FROM crops ORDER BY crop_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$barangayList = $pdo->query("SELECT DISTINCT barangay FROM farmers ORDER BY barangay ASC")->fetchAll(PDO::FETCH_ASSOC);

$filter_crop     = $_GET['crop']     ?? 'Rice';
$filter_barangay = $_GET['barangay'] ?? '';

$sql = "
    SELECT cm.id, cm.farmer_crop_id, cm.monitoring_date, cm.stage, cm.actual_yield,
           fc.planting_date, fc.expected_harvest, fc.area_planted,
           f.first_name, f.last_name, f.barangay, c.crop_name
    FROM crop_monitoring cm
    JOIN farmer_crops fc ON cm.farmer_crop_id = fc.id
    JOIN farmers f ON fc.farmer_id = f.id
    JOIN crops c ON fc.crop_id = c.crop_id
    WHERE 1=1
";
$params = [];
if ($filter_crop)     { $sql .= " AND c.crop_name = ?"; $params[] = $filter_crop; }
if ($filter_barangay) { $sql .= " AND f.barangay = ?";  $params[] = $filter_barangay; }
$sql .= " ORDER BY cm.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$crops = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
:root {
    --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;
    --a100:#fef3c7;--a500:#f59e0b;--a600:#d97706;--a700:#b45309;
    --b100:#dbeafe;--b600:#2563eb;--b700:#1d4ed8;
    --r100:#fee2e2;--r500:#ef4444;--r600:#dc2626;
    --gr50:#f9fafb;--gr100:#f3f4f6;--gr200:#e5e7eb;--gr300:#d1d5db;
    --gr400:#9ca3af;--gr500:#6b7280;--gr700:#374151;--gr900:#111827;
    --rad:12px;--rad-s:8px;--shadow:0 1px 3px rgba(0,0,0,.07);--shadow-m:0 4px 16px rgba(0,0,0,.08);--tr:.18s cubic-bezier(.4,0,.2,1);
}
.cm-wrap { font-family:'DM Sans',sans-serif; padding:24px 28px 60px; }
.cm-wrap * { box-sizing:border-box; }

/* Header */
.cm-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; gap:12px; }
.cm-header-left { display:flex; align-items:center; gap:10px; }
.cm-header-left h2 { margin:0; font-size:1.35rem; font-weight:700; color:var(--gr900); }
.cm-actions { display:flex; gap:8px; flex-wrap:wrap; }

.btn-act {
    display:inline-flex; align-items:center; gap:6px; padding:9px 18px;
    border:none; border-radius:var(--rad-s); font-size:.85rem; font-weight:700;
    cursor:pointer; font-family:'DM Sans',sans-serif; transition:var(--tr);
    white-space:nowrap; text-decoration:none;
}
.btn-act:disabled { opacity:.45; cursor:not-allowed; pointer-events:none; }
.btn-green  { background:var(--g600); color:#fff; }
.btn-green:hover  { background:var(--g700); transform:translateY(-1px); box-shadow:0 3px 10px rgba(22,163,74,.3); }
.btn-blue   { background:var(--b600); color:#fff; }
.btn-blue:hover   { background:var(--b700); transform:translateY(-1px); box-shadow:0 3px 10px rgba(37,99,235,.3); }
.btn-amber  { background:var(--a500); color:#fff; }
.btn-amber:hover  { background:var(--a600); transform:translateY(-1px); box-shadow:0 3px 10px rgba(245,158,11,.3); }
.btn-red    { background:var(--r500); color:#fff; }
.btn-red:hover    { background:var(--r600); transform:translateY(-1px); box-shadow:0 3px 10px rgba(239,68,68,.3); }

/* Filter bar */
.filter-bar { background:#fff; border:1px solid var(--gr200); border-radius:var(--rad); padding:16px 20px; margin-bottom:16px; box-shadow:var(--shadow); }
.filter-grid { display:grid; grid-template-columns:1fr 1fr auto; gap:14px; align-items:end; }
@media(max-width:640px){.filter-grid{grid-template-columns:1fr;}}
.fl { font-size:.78rem; font-weight:600; color:var(--gr700); margin-bottom:5px; display:block; }
.f-select {
    width:100%; padding:9px 30px 9px 12px; border:1.5px solid var(--gr200); border-radius:var(--rad-s);
    font-size:.875rem; color:var(--gr900); background:#fff; outline:none; appearance:none; cursor:pointer;
    font-family:'DM Sans',sans-serif; transition:var(--tr);
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 10px center;
}
.f-select:focus { border-color:var(--g500); box-shadow:0 0 0 3px rgba(34,197,94,.1); }
.btn-clear { display:inline-flex; align-items:center; padding:9px 16px; border:1.5px solid var(--gr200); border-radius:var(--rad-s); background:#fff; color:var(--gr500); font-size:.85rem; font-weight:600; text-decoration:none; font-family:'DM Sans',sans-serif; transition:var(--tr); white-space:nowrap; }
.btn-clear:hover { border-color:var(--gr400); color:var(--gr800); }

/* Active filters */
.active-filters { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
.filter-badge { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:20px; font-size:.78rem; font-weight:700; }
.filter-badge.green { background:var(--g100); color:var(--g700); }
.filter-badge.blue  { background:var(--b100); color:var(--b700); }
.filter-count { font-size:.78rem; color:var(--gr400); }

/* Table card */
.tbl-card { background:#fff; border:1px solid var(--gr200); border-radius:var(--rad); box-shadow:var(--shadow-m); overflow:hidden; }
.tbl-wrap  { overflow-x:auto; }
table { width:100%; border-collapse:collapse; font-size:.855rem; }
thead { background:var(--g700); }
thead th { padding:12px 14px; text-align:left; font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:rgba(255,255,255,.9); border:none; white-space:nowrap; }
thead th.center { text-align:center; }
tbody tr { border-bottom:1px solid var(--gr100); transition:var(--tr); cursor:pointer; }
tbody tr:last-child { border-bottom:none; }
tbody tr:hover { background:var(--g50); }
tbody tr.selected { background:var(--g100) !important; }
td { padding:11px 14px; color:var(--gr700); vertical-align:middle; }
td.center { text-align:center; }
td.num { font-family:'DM Mono',monospace; font-size:.83rem; text-align:right; padding-right:20px; }

.farmer-name { font-weight:700; color:var(--gr900); }
.crop-badge { display:inline-flex; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; background:var(--g100); color:var(--g800); }

/* Stage badges */
.stage-badge { display:inline-flex; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
.stage-planting   { background:#f3f4f6; color:#374151; }
.stage-vegetative { background:#dbeafe; color:#1e40af; }
.stage-flowering  { background:var(--a100); color:var(--a700); }
.stage-harvest    { background:var(--g100); color:var(--g800); }

.empty-row td { text-align:center; padding:40px; color:var(--gr400); font-size:.875rem; }

/* Row num */
.row-num { font-size:.78rem; color:var(--gr400); text-align:center; }
</style>

<div class="cm-wrap">

    <!-- HEADER -->
    <div class="cm-header">
        <div class="cm-header-left">
            <span style="font-size:1.5rem;">🌾</span>
            <h2>Crop Monitoring</h2>
        </div>
        <div class="cm-actions">
            <a href="add_crop.php" class="btn-act btn-green">+ Add Crop</a>
            <button id="monitorBtn" class="btn-act btn-blue" disabled>+ Monitor Crop</button>
            <button id="damageBtn"  class="btn-act btn-amber" disabled>⚠️ Damage Assessment</button>
            <button id="deleteBtn"  class="btn-act btn-red"   disabled>🗑 Delete</button>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" class="filter-bar">
        <div class="filter-grid">
            <div>
                <label class="fl">🌱 Crop</label>
                <select name="crop" class="f-select" onchange="this.form.submit()">
                    <option value="">-- All Crops --</option>
                    <?php foreach ($cropList as $c): ?>
                        <option value="<?= htmlspecialchars($c['crop_name']) ?>" <?= $filter_crop === $c['crop_name'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c['crop_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="fl">📍 Barangay <span style="font-weight:400;color:var(--gr400);">(optional)</span></label>
                <select name="barangay" class="f-select" onchange="this.form.submit()">
                    <option value="">-- All Barangays --</option>
                    <?php foreach ($barangayList as $b): ?>
                        <option value="<?= htmlspecialchars($b['barangay']) ?>" <?= $filter_barangay === $b['barangay'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['barangay']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <a href="crop.php" class="btn-clear">✕ Clear</a>
            </div>
        </div>
    </form>

    <!-- ACTIVE FILTERS -->
    <?php if ($filter_crop || $filter_barangay): ?>
    <div class="active-filters">
        <?php if ($filter_crop):     ?><span class="filter-badge green">🌱 <?= htmlspecialchars($filter_crop) ?></span><?php endif; ?>
        <?php if ($filter_barangay): ?><span class="filter-badge blue">📍 <?= htmlspecialchars($filter_barangay) ?></span><?php endif; ?>
        <span class="filter-count"><?= count($crops) ?> record(s) found</span>
    </div>
    <?php endif; ?>

    <!-- TABLE -->
    <div class="tbl-card">
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="center">#</th>
                        <th>Farmer</th>
                        <th>Barangay</th>
                        <th class="center">Crop</th>
                        <th class="center">Planting Date</th>
                        <th class="center">Expected Harvest</th>
                        <th class="center">Monitoring Date</th>
                        <th class="center">Area</th>
                        <th class="center">Yield</th>
                        <th class="center">Stage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($crops)): ?>
                        <tr class="empty-row"><td colspan="10">No records found for the selected filters.</td></tr>
                    <?php else: ?>
                        <?php foreach ($crops as $i => $crop): ?>
                            <tr class="clickable-row"
                                data-id="<?= $crop['id'] ?>"
                                data-farmer-crop-id="<?= $crop['farmer_crop_id'] ?>"
                                data-stage="<?= htmlspecialchars($crop['stage']) ?>"
                                data-yield="<?= $crop['actual_yield'] ?? '' ?>"
                                data-monitoring-id="<?= $crop['id'] ?>">

                                <td class="row-num"><?= $i + 1 ?></td>
                                <td><span class="farmer-name"><?= htmlspecialchars($crop['last_name'].', '.$crop['first_name']) ?></span></td>
                                <td><?= htmlspecialchars($crop['barangay']) ?></td>
                                <td class="center"><span class="crop-badge"><?= htmlspecialchars($crop['crop_name']) ?></span></td>
                                <td class="center"><?= date("M d, Y", strtotime($crop['planting_date'])) ?></td>
                                <td class="center">
                                    <?php $eh = $crop['expected_harvest'] ?? ''; ?>
                                    <?= (!empty($eh) && $eh !== '0000-00-00') ? date("M d, Y", strtotime($eh)) : '<span style="color:var(--gr300);">—</span>' ?>
                                </td>
                                <td class="center"><?= date("M d, Y", strtotime($crop['monitoring_date'])) ?></td>
                                <td class="num"><?= number_format($crop['area_planted'], 2) ?> ha</td>
                                <td class="num"><?= $crop['actual_yield'] ? number_format($crop['actual_yield'], 2).' T' : '<span style="color:var(--gr300);">—</span>' ?></td>
                                <td class="center">
                                    <?php
                                    $s = $crop['stage'];
                                    $cls = match($s) {
                                        'Planting'   => 'stage-planting',
                                        'Vegetative' => 'stage-vegetative',
                                        'Flowering'  => 'stage-flowering',
                                        'Harvest'    => 'stage-harvest',
                                        default      => 'stage-planting'
                                    };
                                    echo "<span class='stage-badge $cls'>$s</span>";
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let selId=null, selFCId=null, selStage=null, selYield=null, selMId=null;

document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', function() {
        document.querySelectorAll('.clickable-row').forEach(r => r.classList.remove('selected'));
        this.classList.add('selected');
        selId    = this.dataset.id;
        selFCId  = this.dataset.farmerCropId;
        selStage = this.dataset.stage;
        selYield = this.dataset.yield;
        selMId   = this.dataset.monitoringId;
        document.getElementById('deleteBtn').disabled  = false;
        document.getElementById('monitorBtn').disabled = false;
        document.getElementById('damageBtn').disabled  = false;
    });
});

document.getElementById('deleteBtn').onclick = () => {
    if (selId && confirm('Delete selected record?')) location.href = 'delete_crop.php?id=' + selId;
};
document.getElementById('monitorBtn').onclick = () => {
    if (selFCId) location.href = 'add_monitoring.php?farmer_crop_id='+selFCId+'&stage='+encodeURIComponent(selStage)+'&yield='+encodeURIComponent(selYield)+'&ret_crop=<?= urlencode($filter_crop) ?>&ret_barangay=<?= urlencode($filter_barangay) ?>';
};
document.getElementById('damageBtn').onclick = () => {
    if (selMId) location.href = 'add_damage_assessment.php?crop_monitoring_id='+selMId+'&ret_crop=<?= urlencode($filter_crop) ?>&ret_barangay=<?= urlencode($filter_barangay) ?>';
};
</script>

<?php require "../includes/footer.php"; ?>