<?php
require "../config/database.php";

// ── FETCH DATA ─────────────────────────────────────────────────────────────────
$input_types = $pdo->query("SELECT * FROM input_types ORDER BY name ASC")->fetchAll();

$items_raw = $pdo->query("
    SELECT id, input_type_id, item_name, unit FROM input_items ORDER BY item_name ASC
")->fetchAll();

$itemsByType = [];
foreach ($items_raw as $row) {
    $itemsByType[$row['input_type_id']][] = $row;
}

$inventory = $pdo->query("
    SELECT inv.input_item_id, it.name AS input_type, ii.item_name, ii.unit, inv.quantity
    FROM  inputs_inventory inv
    JOIN  input_items  ii ON ii.id = inv.input_item_id
    JOIN  input_types  it ON it.id = ii.input_type_id
    ORDER BY it.name ASC, ii.item_name ASC
")->fetchAll();

$grouped  = [];
$stockMap = [];
foreach ($inventory as $item) {
    $grouped[$item['input_type']][]   = $item;
    $stockMap[$item['input_item_id']] = $item['quantity'];
}

$error = '';

// ── HANDLE POST ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_type_id = (int)($_POST['input_type_id'] ?? 0);
    // ✅ item_name — from typed input or selected from datalist (auto-capitalized client-side)
    $item_name     = ucwords(strtolower(trim($_POST['item_name'] ?? '')));
    $unit          = trim($_POST['item_unit'] ?? '');
    $qty           = (float)($_POST['quantity'] ?? 0);
    $item_id       = null;

    if (!$input_type_id || !$item_name || !$unit) {
        $error = "Please fill all required fields.";
    } elseif ($qty <= 0) {
        $error = "Quantity must be greater than 0.";
    } else {
        // ✅ Look up item by name — if exists use ID, else create new
        $ck = $pdo->prepare("SELECT id, unit FROM input_items WHERE input_type_id = ? AND item_name = ?");
        $ck->execute([$input_type_id, $item_name]);
        $existingItem = $ck->fetch();

        if ($existingItem) {
            $item_id = $existingItem['id'];
        } else {
            $pdo->prepare("INSERT INTO input_items (input_type_id, item_name, unit) VALUES (?, ?, ?)")
                ->execute([$input_type_id, $item_name, $unit]);
            $item_id = (int) $pdo->lastInsertId();
        }
    }

    if (!$error && $item_id) {
        $ck = $pdo->prepare("SELECT id FROM inputs_inventory WHERE input_item_id = ?");
        $ck->execute([$item_id]);
        if ($ck->rowCount() > 0) {
            $pdo->prepare("UPDATE inputs_inventory SET quantity = quantity + ?, updated_at = NOW() WHERE input_item_id = ?")
                ->execute([$qty, $item_id]);
        } else {
            $pdo->prepare("INSERT INTO inputs_inventory (input_item_id, quantity) VALUES (?, ?)")
                ->execute([$item_id, $qty]);
        }
        header("Location: inputs.php");
        exit;
    }
}

require "../includes/layout_top.php";
$icons = ['Fertilizer' => '🌾', 'Seeds' => '🌽', 'Equipment' => '🚜'];
?>

<style>
/* ── IMPORTS ──────────────────────────────────────────────── */
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap');

/* ── ROOT TOKENS ──────────────────────────────────────────── */
:root {
    --green-50:  #f0fdf4;
    --green-100: #dcfce7;
    --green-200: #bbf7d0;
    --green-400: #4ade80;
    --green-500: #22c55e;
    --green-600: #16a34a;
    --green-700: #15803d;
    --green-800: #166534;
    --green-900: #14532d;
    --gray-50:   #f9fafb;
    --gray-100:  #f3f4f6;
    --gray-200:  #e5e7eb;
    --gray-300:  #d1d5db;
    --gray-400:  #9ca3af;
    --gray-500:  #6b7280;
    --gray-700:  #374151;
    --gray-900:  #111827;
    --amber-100: #fef3c7;
    --amber-700: #b45309;
    --red-100:   #fee2e2;
    --red-600:   #dc2626;
    --shadow-sm: 0 1px 3px rgba(0,0,0,.06), 0 1px 2px rgba(0,0,0,.04);
    --shadow-md: 0 4px 12px rgba(0,0,0,.08), 0 2px 4px rgba(0,0,0,.05);
    --shadow-lg: 0 10px 30px rgba(0,0,0,.10), 0 4px 8px rgba(0,0,0,.06);
    --radius:    12px;
    --radius-sm: 8px;
    --transition: .18s cubic-bezier(.4,0,.2,1);
}

.inv-page * { font-family: 'DM Sans', sans-serif; box-sizing: border-box; }

/* ── PAGE LAYOUT ──────────────────────────────────────────── */
.inv-page { padding: 28px 20px 60px; }

.inv-page-header {
    display: flex; align-items: flex-start;
    justify-content: space-between; margin-bottom: 28px;
}
.inv-page-header h2 {
    font-size: 1.45rem; font-weight: 700;
    color: var(--gray-900); margin: 0 0 3px;
    display: flex; align-items: center; gap: 8px;
}
.inv-page-header p { margin: 0; font-size: .85rem; color: var(--gray-500); }

.btn-back {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 18px; border-radius: var(--radius-sm);
    border: 1.5px solid var(--gray-200); background: #fff;
    color: var(--gray-700); font-size: .85rem; font-weight: 600;
    text-decoration: none; transition: var(--transition);
    white-space: nowrap;
}
.btn-back:hover { border-color: var(--green-500); color: var(--green-700); background: var(--green-50); }

/* ── GRID ─────────────────────────────────────────────────── */
.inv-grid {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 24px; align-items: start;
}
@media (max-width: 900px) { .inv-grid { grid-template-columns: 1fr; } }

/* ── CARDS ────────────────────────────────────────────────── */
.inv-card {
    background: #fff;
    border: 1px solid var(--gray-200);
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
}
.inv-card-header {
    padding: 18px 22px 14px;
    border-bottom: 1px solid var(--gray-100);
    display: flex; align-items: center; gap: 10px;
}
.inv-card-header h5 {
    margin: 0; font-size: .95rem;
    font-weight: 700; color: var(--gray-900);
}
.inv-card-body { padding: 18px 22px 22px; }

/* ── STOCK PANEL ──────────────────────────────────────────── */
.type-section { margin-bottom: 20px; }
.type-label {
    display: flex; align-items: center; gap: 6px;
    font-size: .72rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .07em;
    color: var(--gray-400); margin-bottom: 8px;
    padding-bottom: 6px; border-bottom: 1px solid var(--gray-100);
}
.stock-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 12px 9px 14px;
    border-radius: var(--radius-sm);
    margin-bottom: 4px; cursor: default;
    background: var(--gray-50);
    border: 1px solid transparent;
    transition: var(--transition);
}
.stock-row:hover { background: var(--green-50); border-color: var(--green-200); }
.stock-row-name { font-size: .875rem; color: var(--gray-700); font-weight: 500; }
.stock-row-qty {
    display: flex; align-items: center; gap: 6px;
}
.qty-num {
    font-family: 'DM Mono', monospace;
    font-size: .875rem; font-weight: 500; color: var(--green-800);
}
.qty-unit {
    font-size: .7rem; font-weight: 600;
    background: var(--green-100); color: var(--green-700);
    padding: 2px 7px; border-radius: 20px;
}
.qty-zero .qty-num { color: var(--gray-400); }
.qty-zero .qty-unit { background: var(--gray-100); color: var(--gray-400); }

.empty-state {
    text-align: center; padding: 40px 20px; color: var(--gray-400);
}
.empty-state .empty-icon { font-size: 2.8rem; margin-bottom: 10px; }
.empty-state p { font-size: .85rem; margin: 0; line-height: 1.5; }

/* ── FORM ─────────────────────────────────────────────────── */
.form-group { margin-bottom: 18px; }
.form-label-custom {
    display: block; margin-bottom: 6px;
    font-size: .82rem; font-weight: 600; color: var(--gray-700);
}
.form-label-custom .req { color: var(--red-600); margin-left: 2px; }
.form-label-custom .hint {
    font-weight: 400; color: var(--gray-400); font-size: .78rem; margin-left: 4px;
}

.form-select-custom,
.form-input-custom {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid var(--gray-200);
    border-radius: var(--radius-sm);
    font-size: .875rem; color: var(--gray-900);
    background: #fff;
    transition: var(--transition);
    outline: none; appearance: none;
    font-family: 'DM Sans', sans-serif;
}
.form-select-custom { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; padding-right: 36px; cursor: pointer; }
.form-select-custom:focus,
.form-input-custom:focus { border-color: var(--green-500); box-shadow: 0 0 0 3px rgba(34,197,94,.12); }
.form-select-custom:disabled,
.form-input-custom:disabled { background: var(--gray-50); color: var(--gray-400); cursor: not-allowed; }

/* Input + unit group */
.qty-group { display: flex; gap: 0; }
.qty-group .form-input-custom {
    border-right: none; border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    flex: 1;
}
.qty-group .unit-tag {
    padding: 10px 14px; background: var(--gray-100);
    border: 1.5px solid var(--gray-200); border-left: none;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    font-size: .82rem; font-weight: 600; color: var(--gray-500);
    min-width: 60px; text-align: center;
    transition: var(--transition);
}
.qty-group.active .unit-tag {
    background: var(--green-50); color: var(--green-700);
    border-color: var(--green-500); border-left: none;
}
.qty-group.active .form-input-custom { border-color: var(--green-500); }

/* Stock info strip */
.stock-info-strip {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 12px; margin-top: 6px;
    background: var(--green-50); border: 1px solid var(--green-200);
    border-radius: var(--radius-sm); font-size: .82rem;
}
.stock-info-strip.low { background: var(--amber-100); border-color: #fcd34d; }
.stock-info-strip span { color: var(--green-800); font-weight: 600; }
.stock-info-strip.low span { color: var(--amber-700); }

/* Progress bar */
.qty-progress { height: 3px; background: var(--gray-200); border-radius: 2px; margin-top: 5px; overflow: hidden; }
.qty-progress-fill { height: 100%; border-radius: 2px; width: 0; transition: width .3s ease, background .3s; background: var(--green-500); }

/* ── NEW ITEM PANEL ───────────────────────────────────────── */
.new-item-panel {
    margin-top: 6px; padding: 16px 18px;
    background: var(--green-50);
    border: 1.5px dashed var(--green-300);
    border-radius: var(--radius);
    animation: slideDown .2s ease;
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}
.new-item-panel-title {
    font-size: .8rem; font-weight: 700;
    color: var(--green-700); text-transform: uppercase;
    letter-spacing: .05em; margin-bottom: 14px;
    display: flex; align-items: center; gap: 6px;
}
.panel-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

/* Unit pills */
.unit-pills { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 2px; }
.unit-pill {
    padding: 5px 12px; border-radius: 20px; cursor: pointer;
    font-size: .8rem; font-weight: 600;
    border: 1.5px solid var(--gray-200);
    background: #fff; color: var(--gray-600);
    transition: var(--transition); user-select: none;
}
.unit-pill:hover { border-color: var(--green-400); color: var(--green-700); background: var(--green-50); }
.unit-pill.selected { background: var(--green-600); border-color: var(--green-600); color: #fff; }
input[name="new_unit"] { display: none; }

/* ── ALERTS ───────────────────────────────────────────────── */
.inv-alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 16px; border-radius: var(--radius-sm);
    margin-bottom: 20px; font-size: .875rem;
}
.inv-alert-danger { background: var(--red-100); border: 1px solid #fca5a5; color: var(--red-600); }
.inv-alert-icon { font-size: 1rem; flex-shrink: 0; margin-top: 1px; }

/* ── BUTTONS ──────────────────────────────────────────────── */
.btn-actions { display: flex; gap: 10px; margin-top: 24px; }
.btn-primary-inv {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 11px 24px; border-radius: var(--radius-sm);
    background: var(--green-600); color: #fff;
    font-size: .875rem; font-weight: 700;
    border: none; cursor: pointer; transition: var(--transition);
    font-family: 'DM Sans', sans-serif;
}
.btn-primary-inv:hover { background: var(--green-700); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(22,163,74,.3); }
.btn-primary-inv:active { transform: translateY(0); }
.btn-secondary-inv {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 11px 22px; border-radius: var(--radius-sm);
    background: #fff; color: var(--gray-600);
    font-size: .875rem; font-weight: 600;
    border: 1.5px solid var(--gray-200); text-decoration: none;
    cursor: pointer; transition: var(--transition);
    font-family: 'DM Sans', sans-serif;
}
.btn-secondary-inv:hover { border-color: var(--gray-400); color: var(--gray-800); }

/* ── DIVIDER ──────────────────────────────────────────────── */
.or-divider {
    display: flex; align-items: center; gap: 10px;
    margin: 4px 0 8px; color: var(--gray-400); font-size: .78rem;
}
.or-divider::before, .or-divider::after {
    content: ''; flex: 1; height: 1px; background: var(--gray-200);
}

/* ── ITEM INPUT + DROPDOWN ────────────────────────────────── */
.item-input-wrap {
    position: relative; display: flex; gap: 0;
}
.item-input-wrap .form-input-custom {
    border-radius: var(--radius-sm) 0 0 var(--radius-sm);
    border-right: none; flex: 1;
}
.item-input-wrap .form-input-custom:focus {
    border-color: var(--green-500);
    box-shadow: 0 0 0 3px rgba(34,197,94,.12);
}
/* Trigger button */
.item-dd-btn {
    display: flex; align-items: center; justify-content: center;
    padding: 0 13px; cursor: pointer;
    background: var(--gray-50);
    border: 1.5px solid var(--gray-200); border-left: none;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    color: var(--gray-500); transition: var(--transition);
    user-select: none; position: relative; min-width: 40px;
}
.item-dd-btn:hover { background: var(--green-50); border-color: var(--green-400); color: var(--green-700); }
.item-dd-btn.open  { background: var(--green-600); border-color: var(--green-600); color: #fff; }
.item-dd-btn svg   { width: 15px; height: 15px; flex-shrink: 0; }
/* Count badge on the button */
.item-dd-count {
    position: absolute; top: 4px; right: 4px;
    font-size: .58rem; font-weight: 800; line-height: 1;
    background: var(--green-500); color: #fff;
    padding: 1px 4px; border-radius: 8px; min-width: 14px; text-align: center;
}
.item-dd-btn.open .item-dd-count { background: rgba(255,255,255,.35); color: #fff; }

/* Dropdown panel */
.item-dd-panel {
    position: absolute; top: calc(100% + 5px); left: 0; right: 0; z-index: 999;
    background: #fff; border: 1.5px solid var(--green-300);
    border-radius: var(--radius-sm);
    box-shadow: 0 8px 24px rgba(0,0,0,.12), 0 2px 6px rgba(0,0,0,.06);
    overflow: hidden;
    animation: ddOpen .15s cubic-bezier(.4,0,.2,1);
}
@keyframes ddOpen {
    from { opacity:0; transform: translateY(-4px); }
    to   { opacity:1; transform: translateY(0); }
}
.item-dd-header {
    padding: 8px 12px 6px;
    background: var(--green-50);
    border-bottom: 1px solid var(--green-100);
    font-size: .7rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .06em; color: var(--green-700);
}
.item-dd-list { max-height: 210px; overflow-y: auto; }
.item-dd-option {
    display: flex; align-items: center; justify-content: space-between;
    padding: 9px 14px; cursor: pointer;
    border-bottom: 1px solid var(--gray-100);
    transition: var(--transition);
}
.item-dd-option:last-child { border-bottom: none; }
.item-dd-option:hover { background: var(--green-50); }
.item-dd-option.selected { background: var(--green-50); }
.item-dd-option .opt-name {
    font-size: .875rem; font-weight: 500; color: var(--gray-800);
    display: flex; align-items: center; gap: 7px;
}
.item-dd-option.selected .opt-name { color: var(--green-700); font-weight: 700; }
.item-dd-option .opt-check {
    color: var(--green-600); font-size: .9rem; opacity: 0;
}
.item-dd-option.selected .opt-check { opacity: 1; }
.item-dd-option .opt-unit {
    font-size: .7rem; font-weight: 700;
    background: var(--gray-100); color: var(--gray-500);
    padding: 2px 7px; border-radius: 20px;
}
.item-dd-option:hover .opt-unit,
.item-dd-option.selected .opt-unit {
    background: var(--green-100); color: var(--green-700);
}
.item-dd-empty {
    padding: 14px 14px; font-size: .82rem;
    color: var(--gray-400); text-align: center;
}
.suggestion-chip.active .chip-unit { opacity: .85; }
</style>

<div class="inv-page">

    <!-- PAGE HEADER -->
    <div class="inv-page-header">
        <div>
            <h2>📦 Manage Inventory</h2>
            <p>Add stock to existing items or register new input items</p>
        </div>
        <a href="inputs.php" class="btn-back">← Back to Distribution</a>
    </div>

    <?php if ($error): ?>
        <div class="inv-alert inv-alert-danger">
            <span class="inv-alert-icon">⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="inv-grid">

        <!-- ── LEFT: CURRENT STOCK ──────────────────────────────────────────── -->
        <div class="inv-card">
            <div class="inv-card-header">
                <span style="font-size:1.1rem;">📊</span>
                <h5>Current Stock</h5>
            </div>
            <div class="inv-card-body">
                <?php if (count($inventory) > 0): ?>
                    <?php foreach ($grouped as $type => $items): ?>
                        <div class="type-section">
                            <div class="type-label">
                                <?= $icons[$type] ?? '📦' ?> <?= htmlspecialchars($type) ?>
                            </div>
                            <?php foreach ($items as $item): ?>
                                <div class="stock-row <?= $item['quantity'] == 0 ? 'qty-zero' : '' ?>">
                                    <span class="stock-row-name"><?= htmlspecialchars($item['item_name']) ?></span>
                                    <div class="stock-row-qty">
                                        <span class="qty-num"><?= number_format($item['quantity'], 2) ?></span>
                                        <span class="qty-unit"><?= htmlspecialchars($item['unit']) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>No inventory yet.<br>Add your first stock using the form.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── RIGHT: ADD STOCK FORM ────────────────────────────────────────── -->
        <div class="inv-card">
            <div class="inv-card-header">
                <span style="font-size:1.1rem;">➕</span>
                <h5>Add Stock</h5>
            </div>
            <div class="inv-card-body">

                <form method="POST" id="addForm">

                    <!-- INPUT TYPE -->
                    <div class="form-group">
                        <label class="form-label-custom">Input Type <span class="req">*</span></label>
                        <select id="sel_type" name="input_type_id" class="form-select-custom" required>
                            <option value="">— Select Type —</option>
                            <?php foreach ($input_types as $type): ?>
                                <option value="<?= $type['id'] ?>"
                                    <?= (($_POST['input_type_id'] ?? '') == $type['id']) ? 'selected' : '' ?>>
                                    <?= ($icons[$type['name']] ?? '📦') . ' ' . htmlspecialchars($type['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- ✅ ITEM — text input + inline dropdown picker -->
                    <div class="form-group" id="item_select_group">
                        <label class="form-label-custom">
                            Item Name <span class="req">*</span>
                            <span class="hint">— type new or pick existing</span>
                        </label>
                        <div class="item-input-wrap">
                            <input
                                id="item_name_input"
                                name="item_name"
                                class="form-input-custom"
                                autocomplete="off"
                                placeholder="e.g. Folimac 14-14-14+TE, Urea…"
                                value="<?= htmlspecialchars($_POST['item_name'] ?? '') ?>"
                                required>

                            <!-- Dropdown trigger button — hidden until type is selected -->
                            <div class="item-dd-btn" id="item_dd_btn" style="display:none;" title="Pick existing item">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
                                </svg>
                                <span class="item-dd-count" id="item_dd_count"></span>
                            </div>

                            <!-- Dropdown panel -->
                            <div class="item-dd-panel" id="item_dd_panel" style="display:none;">
                                <div class="item-dd-header">📋 Existing Items</div>
                                <div class="item-dd-list" id="item_dd_list"></div>
                            </div>
                        </div>

                        <!-- Stock info strip -->
                        <div id="stock_strip" style="display:none;" class="stock-info-strip">
                            <span>📦</span>
                            <span>Current stock:</span>
                            <span id="stock_num"></span>
                            <span id="stock_unit_badge" style="font-size:.75rem;background:var(--green-200);color:var(--green-800);padding:2px 7px;border-radius:20px;font-weight:700;"></span>
                        </div>
                    </div>

                    <!-- UNIT PILLS — always visible once type is selected -->
                    <div class="form-group" id="unit_group" style="display:none;">
                        <label class="form-label-custom">Unit <span class="req">*</span></label>
                        <input type="hidden" name="item_unit" id="item_unit_hidden"
                            value="<?= htmlspecialchars($_POST['item_unit'] ?? '') ?>">
                        <div class="unit-pills" id="unit_pills">
                            <?php foreach (['kg','bags','packs','bottles','liters','units'] as $u): ?>
                                <div class="unit-pill <?= (($_POST['item_unit'] ?? '') === $u) ? 'selected' : '' ?>"
                                     data-unit="<?= $u ?>"><?= $u ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- QUANTITY -->
                    <div class="form-group">
                        <label class="form-label-custom">Quantity to Add <span class="req">*</span></label>
                        <div class="qty-group" id="qty_group">
                            <input name="quantity" type="number" step="0.01" min="0.01"
                                id="qty_input" class="form-input-custom" required
                                placeholder="0.00"
                                value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
                            <span class="unit-tag" id="unit_tag">—</span>
                        </div>
                        <div class="qty-progress" id="qty_bar" style="display:none;">
                            <div class="qty-progress-fill" id="qty_bar_fill"></div>
                        </div>
                    </div>

                    <!-- ACTIONS -->
                    <div class="btn-actions">
                        <button type="submit" class="btn-primary-inv">
                            💾 Save / Add Stock
                        </button>
                        <a href="inputs.php" class="btn-secondary-inv">Cancel</a>
                    </div>

                </form>

            </div>
        </div>

    </div>
</div>

<script>
const itemsByType = <?= json_encode($itemsByType) ?>;
const stockMap    = <?= json_encode($stockMap) ?>;

const selType        = document.getElementById('sel_type');
const itemInput      = document.getElementById('item_name_input');
const unitGroup      = document.getElementById('unit_group');
const unitHidden     = document.getElementById('item_unit_hidden');
const stockStrip     = document.getElementById('stock_strip');
const stockNum       = document.getElementById('stock_num');
const stockUnitBadge = document.getElementById('stock_unit_badge');
const unitTag        = document.getElementById('unit_tag');
const qtyGroup       = document.getElementById('qty_group');
const qtyInput       = document.getElementById('qty_input');
const qtyBar         = document.getElementById('qty_bar');
const qtyBarFill     = document.getElementById('qty_bar_fill');

// Dropdown elements
const ddBtn   = document.getElementById('item_dd_btn');
const ddPanel = document.getElementById('item_dd_panel');
const ddList  = document.getElementById('item_dd_list');
const ddCount = document.getElementById('item_dd_count');

let currentMax  = 0;
let currentUnit = '';
let itemNameMap = {};   // lowercase name → {id, unit, qty}
let ddOpen      = false;

// ── Unit pill selector ──────────────────────────────────────
document.getElementById('unit_pills').addEventListener('click', function(e) {
    const pill = e.target.closest('.unit-pill');
    if (!pill) return;
    document.querySelectorAll('.unit-pill').forEach(p => p.classList.remove('selected'));
    pill.classList.add('selected');
    unitHidden.value = pill.dataset.unit;
    currentUnit = pill.dataset.unit;
    if (unitTag) unitTag.textContent = pill.dataset.unit;
    qtyGroup.classList.add('active');
});

function resetStockUI() {
    stockStrip.style.display = 'none';
    qtyBar.style.display     = 'none';
    if (unitTag) unitTag.textContent = '—';
    qtyGroup.classList.remove('active');
    currentMax = 0; currentUnit = '';
}

function updateBar() {
    if (!currentMax) return;
    const val = parseFloat(qtyInput.value) || 0;
    const pct = Math.min((val / currentMax) * 100, 100);
    qtyBarFill.style.width      = pct + '%';
    qtyBarFill.style.background = pct > 90 ? '#ef4444' : pct > 65 ? '#f59e0b' : 'var(--green-500)';
}

// ── Pick an item by name ────────────────────────────────────
function pickItem(name) {
    const found = itemNameMap[name.trim().toLowerCase()];
    if (!found) { resetStockUI(); return false; }

    const { qty, unit } = found;
    currentMax = qty; currentUnit = unit;

    stockNum.textContent       = parseFloat(qty).toLocaleString(undefined, {minimumFractionDigits:2});
    stockUnitBadge.textContent = unit;
    stockStrip.className       = 'stock-info-strip' + (qty < 10 ? ' low' : '');
    stockStrip.style.display   = 'flex';
    if (unitTag) unitTag.textContent = unit;
    qtyGroup.classList.add('active');
    qtyBar.style.display = 'block';
    updateBar();

    // Auto-select unit pill
    document.querySelectorAll('.unit-pill').forEach(p =>
        p.classList.toggle('selected', p.dataset.unit === unit));
    unitHidden.value = unit;

    // Highlight dropdown row
    document.querySelectorAll('.item-dd-option').forEach(opt =>
        opt.classList.toggle('selected', opt.dataset.name.toLowerCase() === name.trim().toLowerCase()));

    return true;
}

// ── Toggle dropdown ─────────────────────────────────────────
function openDD()  {
    ddPanel.style.display = 'block';
    ddBtn.classList.add('open');
    ddOpen = true;
}
function closeDD() {
    ddPanel.style.display = 'none';
    ddBtn.classList.remove('open');
    ddOpen = false;
}

ddBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    ddOpen ? closeDD() : openDD();
});

// Close when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.item-input-wrap')) closeDD();
});

// ── Build dropdown when type changes ───────────────────────
function buildDropdown(typeId) {
    ddList.innerHTML = '';
    itemNameMap = {};
    unitGroup.style.display  = 'none';
    ddBtn.style.display      = 'none';
    ddCount.textContent      = '';
    closeDD();
    resetStockUI();
    itemInput.value = '';

    if (!typeId || !itemsByType[typeId]) return;

    const items = itemsByType[typeId];

    if (items.length === 0) {
        ddList.innerHTML = '<div class="item-dd-empty">No saved items yet for this type.</div>';
    } else {
        items.forEach(item => {
            itemNameMap[item.item_name.toLowerCase()] = {
                id: item.id, unit: item.unit, qty: stockMap[item.id] ?? 0
            };

            const row = document.createElement('div');
            row.className    = 'item-dd-option';
            row.dataset.name = item.item_name;
            row.innerHTML    = `
                <span class="opt-name">
                    <span class="opt-check">✓</span>
                    ${item.item_name}
                </span>
                <span class="opt-unit">${item.unit}</span>`;

            row.addEventListener('click', function() {
                itemInput.value = this.dataset.name;
                // Auto-capitalize
                itemInput.value = itemInput.value.replace(/\b\w/g, c => c.toUpperCase());
                pickItem(this.dataset.name);
                closeDD();
            });

            ddList.appendChild(row);
        });

        // Show the dropdown button with count badge
        ddBtn.style.display  = 'flex';
        ddCount.textContent  = items.length;
    }

    unitGroup.style.display = 'block';
}

selType.addEventListener('change', function() { buildDropdown(this.value); });

// ── Typing handler ──────────────────────────────────────────
itemInput.addEventListener('input', function() {
    // Auto-capitalize
    const pos = this.selectionStart;
    this.value = this.value.replace(/\b\w/g, c => c.toUpperCase());
    this.setSelectionRange(pos, pos);

    const typed = this.value.trim();
    const matched = pickItem(typed);

    if (!matched) {
        resetStockUI();
        document.querySelectorAll('.item-dd-option').forEach(o => o.classList.remove('selected'));
        if (typed) unitGroup.style.display = 'block';
    }
});

qtyInput.addEventListener('input', updateBar);

// ── Restore after validation error ─────────────────────────
window.addEventListener('load', function() {
    const savedType = "<?= addslashes($_POST['input_type_id'] ?? '') ?>";
    const savedUnit = "<?= addslashes($_POST['item_unit'] ?? '') ?>";
    const savedName = "<?= addslashes($_POST['item_name'] ?? '') ?>";

    if (savedType) {
        selType.value = savedType;
        buildDropdown(savedType);
    }
    if (savedName) {
        itemInput.value = savedName;
        pickItem(savedName);
    }
    if (savedUnit) {
        document.querySelectorAll('.unit-pill').forEach(p =>
            p.classList.toggle('selected', p.dataset.unit === savedUnit));
        unitHidden.value = savedUnit;
        currentUnit = savedUnit;
        if (unitTag) unitTag.textContent = savedUnit;
        qtyGroup.classList.add('active');
    }
});
</script>

<?php require "../includes/footer.php"; ?>