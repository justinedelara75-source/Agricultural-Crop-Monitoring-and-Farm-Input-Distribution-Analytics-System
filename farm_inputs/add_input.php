<?php
require "../config/database.php";

$farmers = $pdo->query("
    SELECT f.* FROM farmers f
    WHERE f.id NOT IN (SELECT farmer_id FROM farm_inputs)
    ORDER BY f.last_name ASC
")->fetchAll();

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $farmer_id = $_POST['farmer_id'] ?? '';

    if (!$farmer_id) {
        $error = "Please select a farmer.";
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM farm_inputs WHERE farmer_id = ?");
        $check->execute([$farmer_id]);

        if ($check->fetchColumn() > 0) {
            $error = "⚠️ This farmer already has a record!";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO farm_inputs
                (farmer_id, rsbsa_no, gender, senior_citizen, ip, pwd, est_date_application)
                VALUES (?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $farmer_id,
                $_POST['rsbsa_no'],
                $_POST['gender'],
                $_POST['senior_citizen'],
                $_POST['ip'],
                $_POST['pwd'],
                $_POST['est_date_application']
            ]);
            header("Location: inputs.php");
            exit;
        }
    }
}

require "../includes/layout_top.php";
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
:root {
    --g50:#f0fdf4;--g100:#dcfce7;--g600:#16a34a;--g700:#15803d;
    --a100:#fef3c7;--a700:#b45309;
    --b100:#dbeafe;--b700:#1d4ed8;
    --r100:#fee2e2;--r500:#ef4444;--r600:#dc2626;
    --gr100:#f3f4f6;--gr200:#e5e7eb;
    --gr400:#9ca3af;--gr500:#6b7280;--gr700:#374151;--gr900:#111827;
    --rad:12px;--rad-s:8px;
    --shadow-m:0 4px 16px rgba(0,0,0,.08);
    --tr:.18s cubic-bezier(.4,0,.2,1);
}
.ai-wrap { font-family:'DM Sans',sans-serif; padding:24px 28px 60px; }
.ai-wrap * { box-sizing:border-box; }

.ai-header { display:flex; align-items:center; gap:10px; margin-bottom:24px; }
.ai-header h2 { margin:0; font-size:1.35rem; font-weight:700; color:var(--gr900); }

.alert-err {
    background:var(--r100); border:1px solid #fca5a5; color:#991b1b;
    border-radius:var(--rad-s); padding:12px 16px; margin-bottom:20px;
    font-size:.875rem; font-weight:500; display:flex; align-items:center; gap:8px;
}
.alert-warn {
    background:var(--a100); border:1px solid #fcd34d; color:var(--a700);
    border-radius:var(--rad-s); padding:14px 18px; margin-bottom:20px;
    font-size:.875rem; font-weight:500;
}
.alert-warn a { color:var(--g700); font-weight:700; }

/* Card */
.form-card {
    background:#fff; border:1px solid var(--gr200); border-radius:var(--rad);
    box-shadow:var(--shadow-m); overflow:hidden;
}

/* Horizontal section rows */
.section-row {
    display:grid;
    grid-template-columns:220px 1fr;
    border-bottom:1px solid var(--gr100);
}
.section-row:last-of-type { border-bottom:none; }

.section-label {
    padding:28px 24px; background:var(--g50);
    border-right:1px solid var(--gr100);
    display:flex; flex-direction:column; gap:4px;
}
.section-label .s-icon  { font-size:1.3rem; margin-bottom:2px; }
.section-label .s-title { font-size:.75rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--g700); }
.section-label .s-desc  { font-size:.73rem; color:var(--gr400); line-height:1.45; margin-top:2px; }

.section-fields {
    padding:24px 28px;
    display:grid;
    grid-template-columns:1fr 1fr 1fr;
    gap:0 20px;
    align-content:start;
}
.field-span2 { grid-column:span 2; }
.field-span3 { grid-column:1/-1; }

@media(max-width:860px){
    .section-row { grid-template-columns:1fr; }
    .section-label { border-right:none; border-bottom:1px solid var(--gr100); padding:14px 20px; flex-direction:row; align-items:center; gap:10px; }
    .section-fields { grid-template-columns:1fr 1fr; }
    .field-span2,.field-span3 { grid-column:1/-1; }
}
@media(max-width:520px){ .section-fields { grid-template-columns:1fr; } }

/* Fields */
.field { margin-bottom:18px; }
.field label {
    display:block; font-size:.78rem; font-weight:600; color:var(--gr700);
    margin-bottom:6px; text-transform:uppercase; letter-spacing:.04em;
}
.field label .req { color:var(--r500); margin-left:2px; }
.field label .opt { font-weight:400; color:var(--gr400); text-transform:none; letter-spacing:0; font-size:.73rem; }

.f-input, .f-select {
    width:100%; padding:10px 14px; border:1.5px solid var(--gr200);
    border-radius:var(--rad-s); font-size:.875rem; color:var(--gr900);
    font-family:'DM Sans',sans-serif; transition:var(--tr); outline:none; background:#fff;
}
.f-input[readonly] { background:var(--g50); color:var(--gr500); cursor:default; }
.f-select {
    appearance:none; cursor:pointer; padding-right:36px;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 12px center;
}
.f-input:focus, .f-select:focus { border-color:var(--g600); box-shadow:0 0 0 3px rgba(34,197,94,.12); }
.f-input[readonly]:focus { box-shadow:none; border-color:var(--gr200); }

/* Auto-fill chip */
.autofill-label {
    display:inline-flex; align-items:center; gap:4px;
    padding:2px 8px; border-radius:20px; font-size:.68rem; font-weight:700;
    background:var(--b100); color:var(--b700); margin-left:6px;
}

/* Flag grid — 3 flags inline */
.flags-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:0 20px; }
@media(max-width:520px){ .flags-grid { grid-template-columns:1fr; } }

/* Select2 */
.select2-container .select2-selection--single {
    height:42px !important; border:1.5px solid var(--gr200) !important;
    border-radius:var(--rad-s) !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height:42px !important; padding-left:14px !important; font-size:.875rem; color:var(--gr900);
}
.select2-container--default .select2-selection--single .select2-selection__arrow { height:42px !important; right:8px !important; }
.select2-container--default.select2-container--focus .select2-selection--single,
.select2-container--default.select2-container--open .select2-selection--single {
    border-color:var(--g600) !important; box-shadow:0 0 0 3px rgba(34,197,94,.12) !important;
}
.select2-dropdown { border:1.5px solid var(--gr200) !important; border-radius:var(--rad-s) !important; }
.select2-results__option--highlighted { background:var(--g600) !important; }

/* Action bar */
.action-bar {
    padding:20px 28px; background:var(--g50);
    border-top:1px solid var(--gr100);
    display:flex; gap:10px;
}
.btn-act {
    display:inline-flex; align-items:center; gap:6px; padding:10px 22px;
    border:none; border-radius:var(--rad-s); font-size:.875rem; font-weight:700;
    cursor:pointer; font-family:'DM Sans',sans-serif; transition:var(--tr);
    text-decoration:none; white-space:nowrap;
}
.btn-green { background:var(--g600); color:#fff; }
.btn-green:hover { background:var(--g700); transform:translateY(-1px); box-shadow:0 3px 10px rgba(22,163,74,.3); }
.btn-ghost { background:#fff; color:var(--gr500); border:1.5px solid var(--gr200); }
.btn-ghost:hover { border-color:var(--gr400); color:var(--gr700); }
</style>

<div class="ai-wrap">

    <div class="ai-header">
        <span style="font-size:1.5rem;">🌱</span>
        <h2>Add Distribution Record</h2>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert-err">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($farmers)): ?>
        <div class="alert-warn">
            All registered farmers already have a distribution record.
            <a href="inputs.php">← Go back to list</a>
        </div>
    <?php else: ?>

    <div class="form-card">
        <form method="POST">

            <!-- FARMER SELECTION -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">👨‍🌾</span>
                    <span class="s-title">Farmer</span>
                    <span class="s-desc">Select a farmer — info auto-fills below</span>
                </div>
                <div class="section-fields">
                    <div class="field field-span3">
                        <label>Farmer Name <span class="req">*</span></label>
                        <select id="farmer_id" name="farmer_id" class="f-select" required>
                            <option value="">-- Select Farmer --</option>
                            <?php foreach ($farmers as $f): ?>
                                <option value="<?= $f['id'] ?>"
                                    data-barangay="<?= htmlspecialchars($f['barangay'] ?? '') ?>"
                                    data-birthdate="<?= htmlspecialchars($f['birthdate'] ?? '') ?>"
                                    data-farm-size="<?= htmlspecialchars($f['farm_size'] ?? '') ?>"
                                    <?= (isset($_POST['farmer_id']) && $_POST['farmer_id'] == $f['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(($f['last_name'] ?? '').', '.($f['first_name'] ?? '').' '.($f['middle_name'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Auto-filled info -->
                    <div class="field">
                        <label>Barangay <span class="autofill-label">⚡ Auto</span></label>
                        <input type="text" id="barangay_display" class="f-input" readonly placeholder="Select farmer first">
                    </div>
                    <div class="field">
                        <label>Birthdate <span class="autofill-label">⚡ Auto</span></label>
                        <input type="text" id="birthdate_display" class="f-input" readonly placeholder="Select farmer first">
                    </div>
                    <div class="field">
                        <label>Farm Size <span class="autofill-label">⚡ Auto</span></label>
                        <input type="text" id="farm_size_display" class="f-input" readonly placeholder="Select farmer first">
                    </div>
                </div>
            </div>

            <!-- PROFILE INFO -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">📋</span>
                    <span class="s-title">Profile Info</span>
                    <span class="s-desc">Registry number and gender</span>
                </div>
                <div class="section-fields">
                    <div class="field field-span2">
                        <label>RSBSA Registry No. <span class="opt">(optional)</span></label>
                        <input class="f-input" name="rsbsa_no"
                            value="<?= htmlspecialchars($_POST['rsbsa_no'] ?? '') ?>"
                            placeholder="e.g. 040201-001234">
                    </div>
                    <div class="field">
                        <label>Gender <span class="opt">(optional)</span></label>
                        <select name="gender" class="f-select">
                            <option value="">Select Gender</option>
                            <option value="M" <?= (($_POST['gender'] ?? '') == 'M') ? 'selected' : '' ?>>Male</option>
                            <option value="F" <?= (($_POST['gender'] ?? '') == 'F') ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- BENEFICIARY FLAGS -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">🏷️</span>
                    <span class="s-title">Beneficiary Flags</span>
                    <span class="s-desc">Senior Citizen, IP, and PWD classification</span>
                </div>
                <div class="section-fields flags-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0 20px;padding:24px 28px;align-content:start;">
                    <div class="field">
                        <label>Senior Citizen</label>
                        <select name="senior_citizen" class="f-select">
                            <option value="N" <?= (($_POST['senior_citizen'] ?? 'N') == 'N') ? 'selected' : '' ?>>No</option>
                            <option value="Y" <?= (($_POST['senior_citizen'] ?? '') == 'Y') ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Indigenous People (IP)</label>
                        <select name="ip" class="f-select">
                            <option value="N" <?= (($_POST['ip'] ?? 'N') == 'N') ? 'selected' : '' ?>>No</option>
                            <option value="Y" <?= (($_POST['ip'] ?? '') == 'Y') ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                    <div class="field">
                        <label>Person w/ Disability (PWD)</label>
                        <select name="pwd" class="f-select">
                            <option value="N" <?= (($_POST['pwd'] ?? 'N') == 'N') ? 'selected' : '' ?>>No</option>
                            <option value="Y" <?= (($_POST['pwd'] ?? '') == 'Y') ? 'selected' : '' ?>>Yes</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- APPLICATION DATE -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">📅</span>
                    <span class="s-title">Schedule</span>
                    <span class="s-desc">Estimated application date</span>
                </div>
                <div class="section-fields">
                    <div class="field">
                        <label>Est. Date of Application <span class="opt">(optional)</span></label>
                        <input type="date" class="f-input" name="est_date_application"
                            value="<?= htmlspecialchars($_POST['est_date_application'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- ACTION BAR -->
            <div class="action-bar">
                <button type="submit" class="btn-act btn-green">✓ Save Record</button>
                <a href="inputs.php" class="btn-act btn-ghost">✕ Cancel</a>
            </div>

        </form>
    </div>

    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function () {
    $('#farmer_id').select2({ placeholder: 'Search farmer name...', allowClear: true, width: '100%' });

    function fillFarmerInfo(sel) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('barangay_display').value  = opt.getAttribute('data-barangay')  || '';
        const bd = opt.getAttribute('data-birthdate') || '';
        document.getElementById('birthdate_display').value = bd ? new Date(bd).toLocaleDateString('en-US', {month:'short',day:'2-digit',year:'numeric'}) : '';
        const fs = opt.getAttribute('data-farm-size');
        document.getElementById('farm_size_display').value = fs ? parseFloat(fs).toFixed(2) + ' ha' : '';
    }

    $('#farmer_id').on('change', function () {
        fillFarmerInfo(document.getElementById('farmer_id'));
    });

    const sel = document.getElementById('farmer_id');
    if (sel && sel.value) fillFarmerInfo(sel);
});
</script>

<?php require "../includes/footer.php"; ?>