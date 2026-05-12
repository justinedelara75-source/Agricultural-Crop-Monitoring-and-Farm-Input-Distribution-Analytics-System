<?php
require "../config/database.php";

$error = "";

if (!isset($_GET['id'])) {
    header("Location: crop.php");
    exit;
}

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM crop_monitoring WHERE id=?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header("Location: crop.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $date     = $_POST['monitoring_date'];
    $stage    = $_POST['stage'];
    $yield    = $_POST['actual_yield']    ?? null;
    $expected = $_POST['expected_harvest'] ?? null;
    $remarks  = $_POST['remarks'];

    if (!$date || !$stage) {
        $error = "Fill required fields.";
    } else {
        if ($stage == "Vegetative") { $yield = null; $expected = null; }
        if ($stage == "Flowering" && !$expected) $error = "Expected harvest date is required.";
        if ($stage == "Harvest"   && !$yield)    $error = "Yield is required.";

        if (!$error) {
            $stmt = $pdo->prepare("
                UPDATE crop_monitoring SET
                    monitoring_date  = ?,
                    stage            = ?,
                    actual_yield     = ?,
                    expected_harvest = ?,
                    remarks          = ?
                WHERE id = ?
            ");
            $stmt->execute([$date, $stage, $yield ?: null, $expected ?: null, $remarks, $id]);
            header("Location: crop.php");
            exit;
        }
    }
}

require "../includes/layout_top.php";
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
    --rad:12px;--rad-s:8px;
    --shadow:0 1px 3px rgba(0,0,0,.07);--shadow-m:0 4px 16px rgba(0,0,0,.08);
    --tr:.18s cubic-bezier(.4,0,.2,1);
}
.ec-wrap { font-family:'DM Sans',sans-serif; padding:24px 28px 60px; max-width:680px; }
.ec-wrap * { box-sizing:border-box; }

/* Header */
.ec-header { display:flex; align-items:center; gap:10px; margin-bottom:24px; }
.ec-header h2 { margin:0; font-size:1.35rem; font-weight:700; color:var(--gr900); }

/* Alert */
.alert-err {
    background:var(--r100); border:1px solid #fca5a5; color:#991b1b;
    border-radius:var(--rad-s); padding:12px 16px; margin-bottom:20px;
    font-size:.875rem; font-weight:500; display:flex; align-items:center; gap:8px;
}

/* Card */
.form-card {
    background:#fff; border:1px solid var(--gr200); border-radius:var(--rad);
    box-shadow:var(--shadow-m); padding:28px 32px;
}

/* Field */
.field { margin-bottom:20px; }
.field label {
    display:block; font-size:.78rem; font-weight:600; color:var(--gr700);
    margin-bottom:6px; text-transform:uppercase; letter-spacing:.04em;
}
.field label .req { color:var(--r500); margin-left:2px; }
.field label .opt { font-weight:400; color:var(--gr400); text-transform:none; letter-spacing:0; margin-left:4px; }

.f-input, .f-select, .f-textarea {
    width:100%; padding:10px 14px; border:1.5px solid var(--gr200);
    border-radius:var(--rad-s); font-size:.875rem; color:var(--gr900);
    font-family:'DM Sans',sans-serif; transition:var(--tr); outline:none; background:#fff;
}
.f-select {
    appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 12px center; padding-right:36px; cursor:pointer;
}
.f-textarea { resize:vertical; min-height:90px; }
.f-input:focus, .f-select:focus, .f-textarea:focus {
    border-color:var(--g500); box-shadow:0 0 0 3px rgba(34,197,94,.12);
}

/* Stage badges preview */
.stage-hint { display:flex; gap:8px; flex-wrap:wrap; margin-top:8px; }
.s-chip {
    padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; cursor:default;
}
.s-planting   { background:#f3f4f6; color:#374151; }
.s-vegetative { background:var(--b100); color:#1e40af; }
.s-flowering  { background:var(--a100); color:var(--a700); }
.s-harvest    { background:var(--g100); color:var(--g700); }

/* Conditional fields */
.cond-field { display:none; }

/* Divider */
.divider { border:none; border-top:1px solid var(--gr100); margin:24px 0; }

/* Actions */
.form-actions { display:flex; gap:10px; flex-wrap:wrap; }
.btn-act {
    display:inline-flex; align-items:center; gap:6px; padding:10px 22px;
    border:none; border-radius:var(--rad-s); font-size:.875rem; font-weight:700;
    cursor:pointer; font-family:'DM Sans',sans-serif; transition:var(--tr);
    text-decoration:none; white-space:nowrap;
}
.btn-green { background:var(--g600); color:#fff; }
.btn-green:hover { background:var(--g700); transform:translateY(-1px); box-shadow:0 3px 10px rgba(22,163,74,.3); }
.btn-ghost {
    background:#fff; color:var(--gr500); border:1.5px solid var(--gr200);
}
.btn-ghost:hover { border-color:var(--gr400); color:var(--gr700); }
</style>

<div class="ec-wrap">

    <!-- HEADER -->
    <div class="ec-header">
        <span style="font-size:1.5rem;">📋</span>
        <h2>Edit Monitoring Record</h2>
    </div>

    <?php if ($error): ?>
    <div class="alert-err">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST">

            <!-- Monitoring Date -->
            <div class="field">
                <label>Monitoring Date <span class="req">*</span></label>
                <input type="date" name="monitoring_date" class="f-input"
                    value="<?= htmlspecialchars($data['monitoring_date']) ?>" required>
            </div>

            <!-- Stage -->
            <div class="field">
                <label>Growth Stage <span class="req">*</span></label>
                <select name="stage" id="stageSelect" class="f-select" required>
                    <option value="Planting"   <?= $data['stage']=="Planting"  ?'selected':'' ?>>Planting</option>
                    <option value="Vegetative" <?= $data['stage']=="Vegetative"?'selected':'' ?>>Vegetative</option>
                    <option value="Flowering"  <?= $data['stage']=="Flowering" ?'selected':'' ?>>Flowering</option>
                    <option value="Harvest"    <?= $data['stage']=="Harvest"   ?'selected':'' ?>>Harvest</option>
                </select>
                <div class="stage-hint">
                    <span class="s-chip s-planting">Planting</span>
                    <span class="s-chip s-vegetative">Vegetative</span>
                    <span class="s-chip s-flowering">Flowering</span>
                    <span class="s-chip s-harvest">Harvest</span>
                </div>
            </div>

            <!-- Expected Harvest (Flowering only) -->
            <div class="field cond-field" id="expectedGroup">
                <label>Expected Harvest Date <span class="req">*</span></label>
                <input type="date" name="expected_harvest" class="f-input"
                    value="<?= htmlspecialchars($data['expected_harvest'] ?? '') ?>">
            </div>

            <!-- Yield (Harvest only) -->
            <div class="field cond-field" id="yieldGroup">
                <label>Actual Yield <span class="req">*</span> <span class="opt">(metric tons)</span></label>
                <input type="number" step="0.01" min="0" name="actual_yield" class="f-input"
                    value="<?= htmlspecialchars($data['actual_yield'] ?? '') ?>"
                    placeholder="e.g. 4.50">
            </div>

            <!-- Remarks -->
            <div class="field">
                <label>Remarks <span class="opt">(optional)</span></label>
                <textarea name="remarks" class="f-textarea"
                    placeholder="Add any notes or observations..."><?= htmlspecialchars($data['remarks'] ?? '') ?></textarea>
            </div>

            <hr class="divider">

            <div class="form-actions">
                <button type="submit" class="btn-act btn-green">✓ Update Record</button>
                <a href="crop.php" class="btn-act btn-ghost">✕ Cancel</a>
            </div>

        </form>
    </div>
</div>

<script>
const stageSelect    = document.getElementById('stageSelect');
const expectedGroup  = document.getElementById('expectedGroup');
const yieldGroup     = document.getElementById('yieldGroup');

function handleStage() {
    const s = stageSelect.value;
    expectedGroup.style.display = s === 'Flowering' ? 'block' : 'none';
    yieldGroup.style.display    = s === 'Harvest'   ? 'block' : 'none';
}

function controlStage() {
    const s = stageSelect.value;
    const rules = {
        Planting:   ['Flowering','Harvest'],
        Vegetative: ['Planting','Harvest'],
        Flowering:  ['Planting','Vegetative'],
        Harvest:    ['Planting','Vegetative','Flowering']
    };
    for (let opt of stageSelect.options) {
        opt.disabled = (rules[s] || []).includes(opt.value);
    }
}

stageSelect.addEventListener('change', () => { handleStage(); controlStage(); });
handleStage();
controlStage();
</script>

<?php require "../includes/footer.php"; ?>