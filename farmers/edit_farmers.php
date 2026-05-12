<?php
require "../config/database.php";

$error = "";

if (!isset($_GET['id'])) {
    header("Location: farmers.php");
    exit;
}

$id = $_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM farmers WHERE id=?");
$stmt->execute([$id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    header("Location: farmers.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $last      = ucwords(strtolower(trim($_POST['last_name'])));
    $first     = ucwords(strtolower(trim($_POST['first_name'])));
    $middle    = ucwords(strtolower(trim($_POST['middle_name'])));
    $barangay  = trim($_POST['barangay']);
    $contact   = trim($_POST['contact_number']);
    $email     = strtolower(trim($_POST['email']));
    $farm_size = trim($_POST['farm_size']);
    $birthdate = trim($_POST['birthdate']);

    if ($last == "" || $first == "" || $barangay == "" || $farm_size == "") {
        $error = "Please fill all required fields.";
    } elseif (!is_numeric($farm_size)) {
        $error = "Farm size must be a number.";
    } elseif (!empty($contact) && (!ctype_digit($contact) || strlen($contact) > 12)) {
        $error = "Contact number must be digits only and max 12 numbers.";
    } else {
        $stmt = $pdo->prepare("
            UPDATE farmers SET
                last_name=?, first_name=?, middle_name=?,
                barangay=?, contact_number=?, email=?,
                farm_size=?, birthdate=?
            WHERE id=?
        ");
        $stmt->execute([
            $last, $first, $middle, $barangay,
            $contact, $email, $farm_size,
            $birthdate ?: null, $id
        ]);
        header("Location: farmers.php");
        exit;
    }
}

require "../includes/layout_top.php";

$barangays = [
    "Atate","Aulo","Bagong Buhay","Bo. Militar (Fort Magsaysay)",
    "Caballero (Poblacion)","Caimito (Poblacion)","Doña Josefa",
    "Ganaderia (Poblacion)","Imelda Valley I","Imelda Valley II",
    "Langka","Malate (Poblacion)","Maligaya","Manacnac","Mapaet",
    "Marcos Village","Popolon (Pagas)","Santolan (Poblacion)",
    "Sapang Buho","Singalat"
];

// Use POST values on error, else DB values
$v = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $data;
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>

<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap');
:root {
    --g50:#f0fdf4;--g100:#dcfce7;--g600:#16a34a;--g700:#15803d;
    --r100:#fee2e2;--r500:#ef4444;--r600:#dc2626;
    --gr100:#f3f4f6;--gr200:#e5e7eb;
    --gr400:#9ca3af;--gr500:#6b7280;--gr700:#374151;--gr900:#111827;
    --rad:12px;--rad-s:8px;
    --shadow-m:0 4px 16px rgba(0,0,0,.08);
    --tr:.18s cubic-bezier(.4,0,.2,1);
}
.ef-wrap { font-family:'DM Sans',sans-serif; padding:24px 28px 60px; }
.ef-wrap * { box-sizing:border-box; }

.ef-header { display:flex; align-items:center; gap:10px; margin-bottom:24px; }
.ef-header h2 { margin:0; font-size:1.35rem; font-weight:700; color:var(--gr900); }

.alert-err {
    background:var(--r100); border:1px solid #fca5a5; color:#991b1b;
    border-radius:var(--rad-s); padding:12px 16px; margin-bottom:20px;
    font-size:.875rem; font-weight:500; display:flex; align-items:center; gap:8px;
}

.form-card {
    background:#fff; border:1px solid var(--gr200); border-radius:var(--rad);
    box-shadow:var(--shadow-m); overflow:hidden;
}

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
@media(max-width:520px){
    .section-fields { grid-template-columns:1fr; }
}

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
.f-select {
    appearance:none; cursor:pointer; padding-right:36px;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2.5'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat; background-position:right 12px center;
}
.f-input:focus,.f-select:focus { border-color:var(--g600); box-shadow:0 0 0 3px rgba(34,197,94,.12); }
.f-input.invalid { border-color:var(--r500); }
.field-hint { font-size:.73rem; color:var(--gr400); margin-top:5px; }
.field-err  { font-size:.73rem; color:var(--r600); margin-top:5px; display:none; }

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

<div class="ef-wrap">

    <div class="ef-header">
        <span style="font-size:1.5rem;">✎</span>
        <h2>Edit Farmer</h2>
    </div>

    <?php if ($error): ?>
    <div class="alert-err">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" id="editFarmerForm">

            <!-- PERSONAL INFO -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">👤</span>
                    <span class="s-title">Personal Info</span>
                    <span class="s-desc">Full name and date of birth</span>
                </div>
                <div class="section-fields">
                    <div class="field">
                        <label>Last Name <span class="req">*</span></label>
                        <input class="f-input" name="last_name" autocomplete="off" required
                            value="<?= htmlspecialchars($v['last_name'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>First Name <span class="req">*</span></label>
                        <input class="f-input" name="first_name" autocomplete="off" required
                            value="<?= htmlspecialchars($v['first_name'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Middle Name <span class="opt">(optional)</span></label>
                        <input class="f-input" name="middle_name" autocomplete="off"
                            value="<?= htmlspecialchars($v['middle_name'] ?? '') ?>">
                    </div>
                    <div class="field">
                        <label>Birthdate <span class="opt">(optional)</span></label>
                        <input type="date" class="f-input" name="birthdate"
                            value="<?= htmlspecialchars((!empty($v['birthdate']) && $v['birthdate'] !== '0000-00-00') ? $v['birthdate'] : '') ?>">
                    </div>
                </div>
            </div>

            <!-- LOCATION & CONTACT -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">📍</span>
                    <span class="s-title">Location & Contact</span>
                    <span class="s-desc">Barangay, phone number, and email</span>
                </div>
                <div class="section-fields">
                    <div class="field field-span3">
                        <label>Barangay <span class="req">*</span></label>
                        <select name="barangay" id="barangaySelect" class="f-select" required>
                            <option value="">Select Barangay</option>
                            <?php foreach ($barangays as $b): ?>
                                <option value="<?= $b ?>" <?= (($v['barangay'] ?? '') === $b) ? 'selected' : '' ?>><?= $b ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field field-span2">
                        <label>Contact Number <span class="opt">(optional)</span></label>
                        <input class="f-input" name="contact_number" id="contact_number"
                            value="<?= htmlspecialchars($v['contact_number'] ?? '') ?>"
                            maxlength="12" inputmode="numeric" autocomplete="off"
                            placeholder="e.g. 09123456789">
                        <div class="field-err" id="contact_error">⚠️ Maximum 12 digits only.</div>
                        <div class="field-hint">Digits only, max 12 characters.</div>
                    </div>
                    <div class="field">
                        <label>Email <span class="opt">(optional)</span></label>
                        <input type="email" class="f-input" name="email"
                            value="<?= htmlspecialchars($v['email'] ?? '') ?>"
                            placeholder="e.g. farmer@email.com">
                    </div>
                </div>
            </div>

            <!-- FARM INFO -->
            <div class="section-row">
                <div class="section-label">
                    <span class="s-icon">🌾</span>
                    <span class="s-title">Farm Information</span>
                    <span class="s-desc">Total area of farmland</span>
                </div>
                <div class="section-fields">
                    <div class="field">
                        <label>Farm Size <span class="req">*</span> <span class="opt">(hectares)</span></label>
                        <input class="f-input" name="farm_size" required
                            value="<?= htmlspecialchars($v['farm_size'] ?? '') ?>"
                            placeholder="e.g. 2.50" inputmode="decimal">
                        <div class="field-hint">Enter numeric value in hectares.</div>
                    </div>
                </div>
            </div>

            <div class="action-bar">
                <button type="submit" class="btn-act btn-green">✓ Update Farmer</button>
                <a href="farmers.php" class="btn-act btn-ghost">✕ Cancel</a>
            </div>

        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function () {
    $('#barangaySelect').select2({ placeholder: 'Search barangay...', allowClear: true, width: '100%' });
});
const contactInput = document.getElementById('contact_number');
const contactError = document.getElementById('contact_error');
contactInput.addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '');
    if (this.value.length > 12) this.value = this.value.slice(0, 12);
    const over = this.value.length === 12;
    this.classList.toggle('invalid', over);
    contactError.style.display = over ? 'block' : 'none';
});
document.getElementById('editFarmerForm').addEventListener('submit', function (e) {
    if (contactInput.value.length > 12) {
        e.preventDefault();
        contactInput.classList.add('invalid');
        contactError.style.display = 'block';
    }
});
</script>

<?php require "../includes/footer.php"; ?>