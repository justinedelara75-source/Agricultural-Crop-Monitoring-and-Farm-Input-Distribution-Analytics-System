<?php
require "../config/database.php";
require "../includes/layout_top.php";

$stmt = $pdo->query("
    SELECT 
        fi.id, fi.farmer_id, fi.est_date_application, fi.status,
        f.last_name, f.first_name, f.middle_name, f.barangay, f.farm_size
    FROM farm_inputs fi
    JOIN farmers f ON fi.farmer_id = f.id
    ORDER BY fi.id DESC
");
$inputs = $stmt->fetchAll();
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
:root {
    --g50:#f0fdf4;--g100:#dcfce7;--g200:#bbf7d0;--g500:#22c55e;--g600:#16a34a;--g700:#15803d;
    --a100:#fef3c7;--a500:#f59e0b;--a600:#d97706;--a700:#b45309;
    --b100:#dbeafe;--b600:#2563eb;--b700:#1d4ed8;
    --r100:#fee2e2;--r500:#ef4444;--r600:#dc2626;
    --p100:#ede9fe;--p600:#7c3aed;--p700:#6d28d9;
    --gr50:#f9fafb;--gr100:#f3f4f6;--gr200:#e5e7eb;--gr300:#d1d5db;
    --gr400:#9ca3af;--gr500:#6b7280;--gr700:#374151;--gr900:#111827;
    --rad:12px;--rad-s:8px;
    --shadow:0 1px 3px rgba(0,0,0,.07);--shadow-m:0 4px 16px rgba(0,0,0,.08);
    --tr:.18s cubic-bezier(.4,0,.2,1);
}
.fi-wrap { font-family:'DM Sans',sans-serif; padding:24px 28px 60px; }
.fi-wrap * { box-sizing:border-box; }

/* Header */
.fi-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; gap:12px; flex-wrap:wrap; }
.fi-header-left { display:flex; align-items:center; gap:10px; }
.fi-header-left h2 { margin:0; font-size:1.35rem; font-weight:700; color:var(--gr900); }
.fi-actions { display:flex; gap:8px; flex-wrap:wrap; }

.btn-act {
    display:inline-flex; align-items:center; gap:6px; padding:9px 16px;
    border:none; border-radius:var(--rad-s); font-size:.835rem; font-weight:700;
    cursor:pointer; font-family:'DM Sans',sans-serif; transition:var(--tr);
    white-space:nowrap; text-decoration:none;
}
.btn-act:disabled { opacity:.45; cursor:not-allowed; pointer-events:none; }
.btn-green  { background:var(--g600);  color:#fff; }
.btn-green:hover  { background:var(--g700);  transform:translateY(-1px); box-shadow:0 3px 10px rgba(22,163,74,.3); }
.btn-blue   { background:var(--b600);  color:#fff; }
.btn-blue:hover   { background:var(--b700);  transform:translateY(-1px); box-shadow:0 3px 10px rgba(37,99,235,.3); }
.btn-purple { background:var(--p600);  color:#fff; }
.btn-purple:hover { background:var(--p700);  transform:translateY(-1px); box-shadow:0 3px 10px rgba(124,58,237,.3); }
.btn-amber  { background:var(--a500);  color:#fff; }
.btn-amber:hover  { background:var(--a600);  transform:translateY(-1px); box-shadow:0 3px 10px rgba(245,158,11,.3); }
.btn-red    { background:var(--r500);  color:#fff; }
.btn-red:hover    { background:var(--r600);  transform:translateY(-1px); box-shadow:0 3px 10px rgba(239,68,68,.3); }
.btn-outline-red {
    background:#fff; color:var(--r600); border:1.5px solid var(--r500);
}
.btn-outline-red:hover { background:var(--r100); }

/* Summary */
.summary-strip { display:flex; align-items:center; gap:8px; margin-bottom:14px; flex-wrap:wrap; }
.summary-badge {
    display:inline-flex; align-items:center; gap:5px; padding:4px 12px;
    border-radius:20px; font-size:.78rem; font-weight:700;
    background:var(--g100); color:var(--g700);
}
.summary-count { font-size:.78rem; color:var(--gr400); }

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
td.num { font-size:.83rem; text-align:right; padding-right:20px; }

.farmer-name { font-weight:700; color:var(--gr900); }
.farmer-mid  { font-size:.75rem; color:var(--gr400); display:block; margin-top:1px; }
.brgy-badge  { display:inline-flex; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; background:var(--b100); color:var(--b700); }

/* Status badges */
.status-distributed { display:inline-flex; padding:4px 12px; border-radius:20px; font-size:.72rem; font-weight:700; background:var(--g100); color:var(--g700); }
.status-pending     { display:inline-flex; padding:4px 12px; border-radius:20px; font-size:.72rem; font-weight:700; background:var(--a100); color:var(--a700); }

.row-num  { font-size:.78rem; color:var(--gr400); text-align:center; }
.empty-row td { text-align:center; padding:40px; color:var(--gr400); font-size:.875rem; }
</style>

<div class="fi-wrap">

    <!-- HEADER -->
    <div class="fi-header">
        <div class="fi-header-left">
            <span style="font-size:1.5rem;">🌱</span>
            <h2>Farm Input Distribution</h2>
        </div>
        <div class="fi-actions">
            <a href="add_input.php"      class="btn-act btn-green">+ Add Distribution</a>
            <a href="add_inventory.php"  class="btn-act btn-blue">📦 Inventory</a>
            <button id="distributeBtn"   class="btn-act btn-purple" disabled>🚚 Distribute</button>
            <button id="editBtn"         class="btn-act btn-amber"  disabled>✎ Edit</button>
            <button id="deleteBtn"       class="btn-act btn-red"    disabled>🗑 Delete</button>
            <a href="reset_distribution.php" class="btn-act btn-outline-red"
                onclick="return confirm('Reset all distribution?')">🔄 Reset</a>
        </div>
    </div>

    <!-- SUMMARY -->
    <div class="summary-strip">
        <span class="summary-badge">🌱 All Records</span>
        <span class="summary-count"><?= count($inputs) ?> record(s) found</span>
    </div>

    <!-- TABLE -->
    <div class="tbl-card">
        <div class="tbl-wrap">
            <table>
                <thead>
                    <tr>
                        <th class="center">#</th>
                        <th>Farmer</th>
                        <th class="center">Barangay</th>
                        <th class="center">Farm Size</th>
                        <th class="center">Application Date</th>
                        <th class="center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inputs)): ?>
                        <tr class="empty-row">
                            <td colspan="6">No records found. Click <strong>+ Add Distribution</strong> to get started.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inputs as $i => $input): ?>
                            <tr class="clickable-row"
                                data-id="<?= $input['id'] ?>"
                                data-status="<?= htmlspecialchars($input['status']) ?>">
                                <td class="row-num"><?= $i + 1 ?></td>
                                <td>
                                    <span class="farmer-name">
                                        <?= htmlspecialchars($input['last_name'].', '.$input['first_name']) ?>
                                    </span>
                                    <?php if (!empty($input['middle_name'])): ?>
                                        <span class="farmer-mid"><?= htmlspecialchars($input['middle_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="center">
                                    <span class="brgy-badge"><?= htmlspecialchars($input['barangay']) ?></span>
                                </td>
                                <td class="num"><?= number_format($input['farm_size'], 2) ?> ha</td>
                                <td class="center">
                                    <?php
                                    $edate = $input['est_date_application'] ?? '';
                                    echo (!empty($edate) && $edate !== '0000-00-00')
                                        ? date("M d, Y", strtotime($edate))
                                        : '<span style="color:var(--gr300);">—</span>';
                                    ?>
                                </td>
                                <td class="center">
                                    <?php if ($input['status'] === 'Distributed'): ?>
                                        <span class="status-distributed">✓ Distributed</span>
                                    <?php else: ?>
                                        <span class="status-pending">⏳ Pending</span>
                                    <?php endif; ?>
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
let selectedId = null, selectedStatus = null;

document.querySelectorAll('.clickable-row').forEach(row => {
    row.addEventListener('click', function () {
        document.querySelectorAll('.clickable-row').forEach(r => r.classList.remove('selected'));
        this.classList.add('selected');
        selectedId     = this.dataset.id;
        selectedStatus = this.dataset.status;

        document.getElementById('distributeBtn').disabled = (selectedStatus === 'Distributed');
        document.getElementById('editBtn').disabled       = false;
        document.getElementById('deleteBtn').disabled     = false;
    });
});

document.getElementById('distributeBtn').onclick = () => { if (selectedId) location.href = 'distribute.php?id=' + selectedId; };
document.getElementById('editBtn').onclick       = () => { if (selectedId) location.href = 'edit_input.php?id=' + selectedId; };
document.getElementById('deleteBtn').onclick     = () => {
    if (selectedId && confirm('Delete this record?')) location.href = 'delete_input.php?id=' + selectedId;
};
</script>

<?php require "../includes/footer.php"; ?>