<?php
require "../config/database.php";

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: inputs.php"); exit; }

$stmt = $pdo->prepare("
    SELECT fi.id, fi.farmer_id, fi.status, fi.est_date_application,
           f.last_name, f.first_name, f.middle_name, f.barangay, f.farm_size
    FROM  farm_inputs fi
    JOIN  farmers f ON fi.farmer_id = f.id
    WHERE fi.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();
if (!$record) { header("Location: inputs.php"); exit; }

$inventoryAll = $pdo->query("
    SELECT inv.input_item_id, inv.quantity, ii.item_name, ii.unit,
           it.id AS type_id, it.name AS input_type
    FROM  inputs_inventory inv
    JOIN  input_items  ii ON ii.id = inv.input_item_id
    JOIN  input_types  it ON it.id = ii.input_type_id
    WHERE inv.quantity > 0
    ORDER BY it.name ASC, ii.item_name ASC
")->fetchAll();

$inventoryGrouped = [];
$availableTypes   = [];
foreach ($inventoryAll as $inv) {
    $inventoryGrouped[$inv['type_id']][] = $inv;
    $availableTypes[$inv['type_id']] = $inv['input_type'];
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_item_id = (int)  ($_POST['input_item_id'] ?? 0);
    $quantity      = (float)($_POST['quantity']      ?? 0);
    $date          = $_POST['date_received'] ?? '';

    if ($record['status'] === 'Distributed') {
        $error = "This farmer has already received inputs!";
    } elseif (!$input_item_id || $quantity <= 0 || !$date) {
        $error = "Please fill in all required fields.";
    } else {
        $check = $pdo->prepare("
            SELECT inv.quantity, ii.unit FROM inputs_inventory inv
            JOIN input_items ii ON ii.id = inv.input_item_id
            WHERE inv.input_item_id = ?
        ");
        $check->execute([$input_item_id]);
        $invRow = $check->fetch();

        if (!$invRow) {
            $error = "Item not found in inventory.";
        } elseif ($quantity > $invRow['quantity']) {
            $error = "Not enough stock! Available: " . number_format($invRow['quantity'], 2) . " " . $invRow['unit'];
        } else {
            $pdo->prepare("INSERT INTO distribution (farm_input_id, input_item_id, quantity, date_received) VALUES (?, ?, ?, ?)")
                ->execute([$id, $input_item_id, $quantity, $date]);
            $pdo->prepare("UPDATE farm_inputs SET status = 'Distributed' WHERE id = ?")
                ->execute([$id]);
            $pdo->prepare("UPDATE inputs_inventory SET quantity = quantity - ? WHERE input_item_id = ?")
                ->execute([$quantity, $input_item_id]);
            header("Location: inputs.php");
            exit;
        }
    }
}

require "../includes/layout_top.php";
$icons = ['Fertilizer' => '🌾', 'Seeds' => '🌽', 'Equipment' => '🚜'];
$savedType = $_POST['type_id'] ?? '';
$savedItem = $_POST['input_item_id'] ?? '';
?>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root {
    --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g400:#4ade80;
    --g500:#22c55e;--g600:#16a34a;--g700:#15803d;--g800:#166534;
    --a50:#fffbeb;--a100:#fef3c7;--a400:#fbbf24;--a700:#b45309;
    --r100:#fee2e2;--r500:#ef4444;
    --gr100:#f3f4f6;--gr200:#e5e7eb;--gr300:#d1d5db;
    --gr400:#9ca3af;--gr500:#6b7280;--gr700:#374151;--gr900:#111827;
    --rad:12px;--rad-s:8px;--rad-xs:6px;
    --shadow-m:0 4px 16px rgba(0,0,0,.08);
    --tr:.18s cubic-bezier(.4,0,.2,1);
}
.di-wrap { font-family:"DM Sans",sans-serif; padding:24px 28px 60px; }
.di-wrap * { box-sizing:border-box; }
.di-header { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:24px; }
.di-header-left { display:flex; align-items:center; gap:10px; }
.di-header h2 { margin:0; font-size:1.35rem; font-weight:700; color:var(--gr900); }
.btn-back { display:inline-flex; align-items:center; gap:5px; padding:8px 16px; border:1.5px solid var(--gr200); border-radius:var(--rad-s); background:#fff; color:var(--gr700); font-size:.82rem; font-weight:600; text-decoration:none; transition:var(--tr); white-space:nowrap; }
.btn-back:hover { border-color:var(--g400); color:var(--g700); background:var(--g50); }
.alert-err { background:var(--r100); border:1px solid #fca5a5; color:#991b1b; border-radius:var(--rad-s); padding:12px 16px; margin-bottom:20px; font-size:.875rem; font-weight:500; display:flex; align-items:center; gap:8px; }
.alert-warn { background:var(--a50); border:1px solid var(--a400); color:var(--a700); border-radius:var(--rad-s); padding:12px 16px; margin-bottom:20px; font-size:.875rem; font-weight:500; display:flex; align-items:center; gap:8px; }
.form-card { background:#fff; border:1px solid var(--gr200); border-radius:var(--rad); box-shadow:var(--shadow-m); overflow:hidden; }
.section-row { display:grid; grid-template-columns:220px 1fr; border-bottom:1px solid var(--gr100); }
.section-row:last-of-type { border-bottom:none; }
.section-label { padding:28px 24px; background:var(--g50); border-right:1px solid var(--gr100); display:flex; flex-direction:column; gap:4px; }
.section-label .s-icon  { font-size:1.3rem; margin-bottom:2px; }
.section-label .s-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--g700); }
.section-label .s-desc  { font-size:.73rem; color:var(--gr400); line-height:1.45; margin-top:2px; }
.section-fields { padding:24px 28px; display:grid; grid-template-columns:1fr 1fr 1fr; gap:0 20px; align-content:start; }
.field-span2 { grid-column:span 2; }
.field-span3 { grid-column:1/-1; }
@media(max-width:860px){ .section-row{grid-template-columns:1fr;} .section-label{border-right:none;border-bottom:1px solid var(--gr100);padding:14px 20px;flex-direction:row;align-items:center;gap:10px;} .section-fields{grid-template-columns:1fr 1fr;} .field-span2,.field-span3{grid-column:1/-1;} }
@media(max-width:520px){ .section-fields{grid-template-columns:1fr;} }
.field { margin-bottom:18px; }
.field label { display:block; font-size:.78rem; font-weight:600; color:var(--gr700); margin-bottom:6px; text-transform:uppercase; letter-spacing:.04em; }
.field label .req { color:var(--r500); margin-left:2px; }
.f-input, .f-select { width:100%; padding:10px 14px; border:1.5px solid var(--gr200); border-radius:var(--rad-s); font-size:.875rem; color:var(--gr900); font-family:"DM Sans",sans-serif; transition:var(--tr); outline:none; background:#fff; }
.f-input[readonly] { background:var(--g50); color:var(--gr500); cursor:default; }
.f-select { appearance:none; cursor:pointer; padding-right:36px; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 12px center; }
.f-input:focus,.f-select:focus { border-color:var(--g600); box-shadow:0 0 0 3px rgba(34,197,94,.12); }
.f-input[readonly]:focus { box-shadow:none; border-color:var(--gr200); }
.f-select:disabled { background:var(--gr100); color:var(--gr400); cursor:not-allowed; }
.auto-chip { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700; background:var(--g100); color:var(--g700); margin-left:6px; }
.stock-strip { display:flex; align-items:center; gap:7px; padding:8px 12px; margin-top:6px; border-radius:var(--rad-xs); background:var(--g50); border:1px solid var(--g200); font-size:.82rem; flex-wrap:wrap; }
.stock-strip.low { background:var(--a50); border-color:var(--a400); }
.stock-strip .ss-label { color:var(--gr500); }
.stock-strip .ss-val { font-weight:700; color:var(--g800); font-family:monospace; }
.stock-strip.low .ss-val { color:var(--a700); }
.stock-strip .ss-unit { font-size:.68rem; font-weight:700; background:var(--g200); color:var(--g800); padding:1px 7px; border-radius:20px; }
.stock-strip.low .ss-unit { background:var(--a100); color:var(--a700); }
.stock-strip .ss-max { margin-left:auto; font-size:.76rem; color:var(--gr400); }
.qty-grp { display:flex; }
.qty-grp .f-input { border-radius:var(--rad-s) 0 0 var(--rad-s); border-right:none; flex:1; }
.qty-unit-tag { padding:10px 14px; min-width:58px; text-align:center; background:var(--gr100); border:1.5px solid var(--gr200); border-left:none; border-radius:0 var(--rad-s) var(--rad-s) 0; font-size:.8rem; font-weight:700; color:var(--gr500); transition:var(--tr); }
.qty-grp.has-unit .f-input { border-color:var(--g400); }
.qty-grp.has-unit .qty-unit-tag { background:var(--g50); border-color:var(--g400); border-left:none; color:var(--g700); }
.qty-prog { height:3px; background:var(--gr200); border-radius:2px; margin-top:5px; overflow:hidden; }
.qty-prog-bar { height:100%; border-radius:2px; width:0; transition:width .25s ease,background .25s; }
.action-bar { padding:20px 28px; background:var(--g50); border-top:1px solid var(--gr100); display:flex; gap:10px; }
.btn-act { display:inline-flex; align-items:center; gap:6px; padding:10px 22px; border:none; border-radius:var(--rad-s); font-size:.875rem; font-weight:700; cursor:pointer; font-family:"DM Sans",sans-serif; transition:var(--tr); text-decoration:none; white-space:nowrap; }
.btn-green { background:var(--g600); color:#fff; }
.btn-green:hover:not(:disabled) { background:var(--g700); transform:translateY(-1px); box-shadow:0 3px 10px rgba(22,163,74,.3); }
.btn-green:disabled { background:var(--gr300); cursor:not-allowed; }
.btn-ghost { background:#fff; color:var(--gr500); border:1.5px solid var(--gr200); }
.btn-ghost:hover { border-color:var(--gr400); color:var(--gr700); }
</style>

<div class="di-wrap">

    <div class="di-header">
        <div class="di-header-left">
            <span style="font-size:1.5rem;">🌾</span>
            <h2>Distribute Input</h2>
        </div>
        <a href="inputs.php" class="btn-back">&#8592; Back to List</a>
    </div>

    <?php if ($error): ?>
        <div class="alert-err">&#9888;&#65039; <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($record['status'] === 'Distributed'): ?>
        <div class="alert-warn">
            &#9989; This farmer has already received inputs. No further distribution allowed.
        </div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST">
            <input type="hidden" name="type_id" id="hidden_type_id" value="<?= htmlspecialchars($savedType) ?>">

            <!-- FARMER INFO -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">&#128104;&#8205;&#127806;</span>
                    <span class="s-title">Farmer</span>
                    <span class="s-desc">Beneficiary receiving the distribution</span>
                </div>
                <div class="section-fields">
                    <div class="field field-span3">
                        <label>Farmer Name <span class="auto-chip">&#128274; Auto</span></label>
                        <input type="text" class="f-input" readonly
                            value="<?= htmlspecialchars($record['last_name'] . ', ' . $record['first_name'] . ' ' . ($record['middle_name'] ?? '')) ?>">
                    </div>
                    <div class="field">
                        <label>Barangay <span class="auto-chip">&#128274; Auto</span></label>
                        <input type="text" class="f-input" readonly value="<?= htmlspecialchars($record['barangay']) ?>">
                    </div>
                    <div class="field">
                        <label>Farm Size <span class="auto-chip">&#128274; Auto</span></label>
                        <input type="text" class="f-input" readonly value="<?= number_format($record['farm_size'], 2) ?> ha">
                    </div>
                    <div class="field">
                        <label>Status <span class="auto-chip">&#128274; Auto</span></label>
                        <input type="text" class="f-input" readonly value="<?= htmlspecialchars($record['status']) ?>">
                    </div>
                </div>
            </div>

            <!-- INPUT SELECTION -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">&#128230;</span>
                    <span class="s-title">Input Selection</span>
                    <span class="s-desc">Choose type then select specific item</span>
                </div>
                <div class="section-fields" style="grid-template-columns:1fr 1fr;">
                    <div class="field">
                        <label>Input Type <span class="req">*</span></label>
                        <select id="type_select" class="f-select" required
                            <?= $record['status'] === 'Distributed' ? 'disabled' : '' ?>>
                            <option value="">&#8212; Select Input Type &#8212;</option>
                            <?php foreach ($availableTypes as $typeId => $typeName): ?>
                                <option value="<?= $typeId ?>" <?= ($savedType == $typeId) ? 'selected' : '' ?>>
                                    <?= ($icons[$typeName] ?? '&#128230;') . ' ' . htmlspecialchars($typeName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Specific Item <span class="req">*</span></label>
                        <select id="input_item_id" name="input_item_id" class="f-select" required
                            <?= $record['status'] === 'Distributed' ? 'disabled' : '' ?>>
                            <option value="">&#8212; Select Input Type First &#8212;</option>
                        </select>
                    </div>
                    <div class="field field-span2" id="stock_strip_wrap" style="display:none; margin-top:-8px;">
                        <div id="stock_strip" class="stock-strip">
                            <span class="ss-label">&#128230; Available:</span>
                            <span class="ss-val" id="ss_val">0</span>
                            <span class="ss-unit" id="ss_unit"></span>
                            <span class="ss-max" id="ss_max"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DISTRIBUTION DETAILS -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">&#128203;</span>
                    <span class="s-title">Details</span>
                    <span class="s-desc">Quantity and date of distribution</span>
                </div>
                <div class="section-fields" style="grid-template-columns:1fr 1fr;">
                    <div class="field">
                        <label>Quantity <span class="req">*</span></label>
                        <div class="qty-grp" id="qty_grp">
                            <input id="qty_inp" type="number" step="0.01" name="quantity"
                                class="f-input" required min="0.01" placeholder="0.00"
                                value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>"
                                <?= $record['status'] === 'Distributed' ? 'disabled' : '' ?>>
                            <span class="qty-unit-tag" id="qty_unit_tag">&#8212;</span>
                        </div>
                        <div class="qty-prog" id="qty_prog" style="display:none;">
                            <div class="qty-prog-bar" id="qty_prog_bar"></div>
                        </div>
                    </div>
                    <div class="field">
                        <label>Date Received <span class="req">*</span></label>
                        <input type="date" name="date_received" class="f-input" required
                            value="<?= htmlspecialchars($_POST['date_received'] ?? date('Y-m-d')) ?>"
                            <?= $record['status'] === 'Distributed' ? 'disabled' : '' ?>>
                    </div>
                </div>
            </div>

            <!-- ACTION BAR -->
            <div class="action-bar">
                <button type="submit" class="btn-act btn-green"
                    <?= $record['status'] === 'Distributed' ? 'disabled' : '' ?>>
                    &#9989; Confirm Distribution
                </button>
                <a href="inputs.php" class="btn-act btn-ghost">&#10005; Cancel</a>
            </div>

        </form>
    </div>
</div>

<script>
const inventory  = <?= json_encode($inventoryGrouped) ?>;
const typeSelect = document.getElementById('type_select');
const itemSelect = document.getElementById('input_item_id');
const hiddenType = document.getElementById('hidden_type_id');
const stockWrap  = document.getElementById('stock_strip_wrap');
const stockStrip = document.getElementById('stock_strip');
const ssVal      = document.getElementById('ss_val');
const ssUnit     = document.getElementById('ss_unit');
const ssMax      = document.getElementById('ss_max');
const qtyGrp     = document.getElementById('qty_grp');
const qtyInp     = document.getElementById('qty_inp');
const qtyUnitTag = document.getElementById('qty_unit_tag');
const qtyProg    = document.getElementById('qty_prog');
const qtyProgBar = document.getElementById('qty_prog_bar');
let maxQty = 0;

function clearUI() {
    stockWrap.style.display = 'none';
    qtyProg.style.display   = 'none';
    qtyUnitTag.textContent  = '\u2014';
    qtyGrp.classList.remove('has-unit');
    maxQty = 0;
}
function showStockUI() {
    const sel = itemSelect.options[itemSelect.selectedIndex];
    if (!sel || !sel.value) { clearUI(); return; }
    const qty = parseFloat(sel.dataset.quantity);
    const unit = sel.dataset.unit;
    maxQty = qty;
    ssVal.textContent       = qty.toLocaleString(undefined,{minimumFractionDigits:2});
    ssUnit.textContent      = unit;
    ssMax.textContent       = 'Max: ' + qty.toLocaleString(undefined,{minimumFractionDigits:2}) + ' ' + unit;
    stockStrip.className    = 'stock-strip' + (qty < 10 ? ' low' : '');
    stockWrap.style.display = 'block';
    qtyInp.max              = qty;
    qtyUnitTag.textContent  = unit;
    qtyGrp.classList.add('has-unit');
    qtyProg.style.display   = 'block';
    updateBar();
}
function updateBar() {
    if (!maxQty) return;
    const pct = Math.min(((parseFloat(qtyInp.value)||0) / maxQty)*100, 100);
    qtyProgBar.style.width      = pct+'%';
    qtyProgBar.style.background = pct>90?'#ef4444':pct>65?'#f59e0b':'var(--g500)';
}
function populateItems(typeId, savedId='') {
    itemSelect.innerHTML = '<option value="">\u2014 Select Item \u2014</option>';
    clearUI();
    if (!typeId || !inventory[typeId]) return;
    inventory[typeId].forEach(inv => {
        const o = document.createElement('option');
        o.value = inv.input_item_id;
        o.textContent = inv.item_name;
        o.dataset.quantity = inv.quantity;
        o.dataset.unit = inv.unit;
        if (String(inv.input_item_id) === String(savedId)) o.selected = true;
        itemSelect.appendChild(o);
    });
    if (itemSelect.value) showStockUI();
}
typeSelect.addEventListener('change', function(){ hiddenType.value = this.value; populateItems(this.value); });
itemSelect.addEventListener('change', showStockUI);
qtyInp.addEventListener('input', updateBar);
window.addEventListener('load', function(){
    const sT = "<?= addslashes($savedType) ?>";
    const sI = "<?= addslashes($savedItem) ?>";
    if (sT) { typeSelect.value = sT; populateItems(sT, sI); }
});
</script>

<?php require "../includes/footer.php"; ?>