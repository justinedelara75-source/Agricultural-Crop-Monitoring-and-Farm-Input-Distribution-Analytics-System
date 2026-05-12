<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: auth/login.php");
    exit();
}
require "config/database.php";

/* ================================================================
   EXPORT: CSV — must run before any HTML
   ================================================================ */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="agri_report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Barangay','Top Crops','Total Yield (tons)','Avg Yield','Status']);
    $rows = $pdo->query("
        SELECT fc.barangay,
               GROUP_CONCAT(DISTINCT cr.crop_name SEPARATOR ', ') crops,
               SUM(cm.actual_yield) total_yield,
               AVG(cm.actual_yield) avg_yield
        FROM farmer_crops fc
        JOIN crops cr ON fc.crop_id = cr.crop_id
        LEFT JOIN crop_monitoring cm ON cm.farmer_crop_id = fc.id
        GROUP BY fc.barangay ORDER BY total_yield DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $avg = $r['avg_yield'] ?? 0;
        $status = $avg >= 150 ? 'High' : ($avg >= 80 ? 'Moderate' : 'Low');
        fputcsv($out, [$r['barangay'], $r['crops'], number_format($r['total_yield'],2), number_format($avg,2), $status]);
    }
    fclose($out);
    exit();
}

/* ================================================================
   FILTERS
   ================================================================ */
$fYear     = isset($_GET['year'])      && $_GET['year']      !== '' ? (int)$_GET['year']     : null;
$fSeason   = isset($_GET['season'])    && $_GET['season']    !== '' ? $_GET['season']         : null;
$fMonth    = isset($_GET['month'])     && $_GET['month']     !== '' ? (int)$_GET['month']     : null;
$fBarangay = isset($_GET['barangay'])  && $_GET['barangay']  !== '' ? $_GET['barangay']       : null;
$fCropId   = isset($_GET['crop_id'])   && $_GET['crop_id']   !== '' ? (int)$_GET['crop_id']  : null;
$fFarmerId = isset($_GET['farmer_id']) && $_GET['farmer_id'] !== '' ? (int)$_GET['farmer_id']: null;

$conds  = [];
$params = [];

if ($fYear)                { $conds[] = "YEAR(fc.planting_date) = ?";  $params[] = $fYear; }
if ($fMonth)               { $conds[] = "MONTH(fc.planting_date) = ?"; $params[] = $fMonth; }
if ($fSeason === 'wet')    { $conds[] = "MONTH(fc.planting_date) BETWEEN 6 AND 11"; }
if ($fSeason === 'dry')    { $conds[] = "(MONTH(fc.planting_date) BETWEEN 1 AND 5 OR MONTH(fc.planting_date) = 12)"; }
if ($fBarangay)            { $conds[] = "fc.barangay = ?";             $params[] = $fBarangay; }
if ($fCropId)              { $conds[] = "fc.crop_id = ?";              $params[] = $fCropId; }
if ($fFarmerId)            { $conds[] = "fc.farmer_id = ?";            $params[] = $fFarmerId; }

$WHERE = $conds ? "WHERE " . implode(" AND ", $conds) : "";

function qf(PDO $pdo, string $sql, array $p): PDOStatement {
    $s = $pdo->prepare($sql);
    $s->execute($p);
    return $s;
}

/* ================================================================
   DROPDOWN OPTIONS
   ================================================================ */
$optYears     = $pdo->query("SELECT DISTINCT YEAR(planting_date) yr FROM farmer_crops ORDER BY yr DESC")->fetchAll(PDO::FETCH_COLUMN);
$optBarangays = $pdo->query("SELECT DISTINCT barangay FROM farmer_crops WHERE barangay IS NOT NULL ORDER BY barangay")->fetchAll(PDO::FETCH_COLUMN);
$optCrops     = $pdo->query("SELECT crop_id, crop_name FROM crops ORDER BY crop_name")->fetchAll(PDO::FETCH_ASSOC);
$optFarmers   = $pdo->query("SELECT id, CONCAT(first_name,' ',last_name) nm FROM farmers ORDER BY last_name")->fetchAll(PDO::FETCH_ASSOC);

/* ================================================================
   KPI QUERIES (all respect filters)
   ================================================================ */
$totalFarmers = $pdo->query("SELECT COUNT(*) FROM farmers")->fetchColumn();
$totalLand    = $pdo->query("SELECT COALESCE(SUM(farm_size),0) FROM farmers")->fetchColumn();

$r = qf($pdo,"SELECT cr.crop_name, COUNT(*) total FROM farmer_crops fc JOIN crops cr ON fc.crop_id=cr.crop_id $WHERE GROUP BY cr.crop_name ORDER BY total DESC LIMIT 1",$params)->fetch(PDO::FETCH_ASSOC);
$mostPlanted = $r['crop_name'] ?? 'N/A';

$r = qf($pdo,"SELECT cr.crop_name, SUM(cm.actual_yield) total_yield FROM crop_monitoring cm JOIN farmer_crops fc ON cm.farmer_crop_id=fc.id JOIN crops cr ON fc.crop_id=cr.crop_id $WHERE GROUP BY cr.crop_name ORDER BY total_yield DESC LIMIT 1",$params)->fetch(PDO::FETCH_ASSOC);
$mostProfit = $r['crop_name'] ?? 'N/A';

$totalProd = qf($pdo,"SELECT COALESCE(SUM(cm.actual_yield),0) FROM crop_monitoring cm JOIN farmer_crops fc ON cm.farmer_crop_id=fc.id $WHERE",$params)->fetchColumn();
$avgYield  = qf($pdo,"SELECT COALESCE(AVG(cm.actual_yield),0) FROM crop_monitoring cm JOIN farmer_crops fc ON cm.farmer_crop_id=fc.id $WHERE",$params)->fetchColumn();

/* ================================================================
   MONTHLY CHART
   ================================================================ */
$months      = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
$chartRice   = $chartCorn = $chartTomato = array_fill(0,12,0);

$chartRows = qf($pdo,"
    SELECT MONTH(fc.planting_date) mo, cr.crop_name, SUM(cm.actual_yield) total
    FROM crop_monitoring cm
    JOIN farmer_crops fc ON cm.farmer_crop_id=fc.id
    JOIN crops cr ON fc.crop_id=cr.crop_id $WHERE
    GROUP BY mo, cr.crop_name",$params)->fetchAll(PDO::FETCH_ASSOC);

foreach ($chartRows as $row) {
    $i = $row['mo'] - 1;
    switch (strtolower($row['crop_name'])) {
        case 'rice':   $chartRice[$i]   = (float)$row['total']; break;
        case 'corn':   $chartCorn[$i]   = (float)$row['total']; break;
        case 'tomato': $chartTomato[$i] = (float)$row['total']; break;
    }
}

/* ================================================================
   TOP BARANGAYS
   ================================================================ */
$barangayRows = qf($pdo,"
    SELECT fc.barangay,
           GROUP_CONCAT(DISTINCT cr.crop_name SEPARATOR ', ') crops,
           SUM(cm.actual_yield) total_yield, AVG(cm.actual_yield) avg_yield
    FROM farmer_crops fc
    JOIN crops cr ON fc.crop_id=cr.crop_id
    LEFT JOIN crop_monitoring cm ON cm.farmer_crop_id=fc.id $WHERE
    GROUP BY fc.barangay ORDER BY total_yield DESC LIMIT 5",$params)->fetchAll(PDO::FETCH_ASSOC);

/* ================================================================
   PROFITABILITY PIE
   ================================================================ */
$condStr    = $conds ? implode(" AND ", $conds) : null;
$profitable    = qf($pdo,"SELECT COUNT(*) FROM crop_monitoring cm JOIN farmer_crops fc ON cm.farmer_crop_id=fc.id " . ($condStr ? "WHERE $condStr AND" : "WHERE") . " cm.actual_yield >= 100",$params)->fetchColumn();
$notProfitable = qf($pdo,"SELECT COUNT(*) FROM crop_monitoring cm JOIN farmer_crops fc ON cm.farmer_crop_id=fc.id " . ($condStr ? "WHERE $condStr AND" : "WHERE") . " cm.actual_yield < 100",$params)->fetchColumn();
$totalPie   = $profitable + $notProfitable;
$profPct    = $totalPie > 0 ? round(($profitable / $totalPie) * 100) : 0;

/* ================================================================
   TREND ANALYSIS (all-time, year-over-year)
   ================================================================ */
$trendYears  = [];
$trendRice   = $trendCorn = $trendTomato = [];
$trendMap    = [];
$trendRows   = $pdo->query("
    SELECT YEAR(fc.planting_date) yr, cr.crop_name, SUM(cm.actual_yield) total
    FROM crop_monitoring cm
    JOIN farmer_crops fc ON cm.farmer_crop_id=fc.id
    JOIN crops cr ON fc.crop_id=cr.crop_id
    GROUP BY yr, cr.crop_name ORDER BY yr")->fetchAll(PDO::FETCH_ASSOC);

foreach ($trendRows as $row) {
    $yr = $row['yr'];
    if (!in_array($yr,$trendYears)) $trendYears[] = $yr;
    $trendMap[$yr][strtolower($row['crop_name'])] = (float)$row['total'];
}
foreach ($trendYears as $yr) {
    $trendRice[]   = $trendMap[$yr]['rice']   ?? 0;
    $trendCorn[]   = $trendMap[$yr]['corn']   ?? 0;
    $trendTomato[] = $trendMap[$yr]['tomato'] ?? 0;
}

/* ================================================================
   COMPARATIVE CROP ANALYTICS
   ================================================================ */
$compareRows = qf($pdo,"
    SELECT cr.crop_name,
           COUNT(DISTINCT fc.farmer_id) farmers,
           SUM(cm.actual_yield) total_yield,
           AVG(cm.actual_yield) avg_yield,
           SUM(fc.area_planted) total_area
    FROM farmer_crops fc
    JOIN crops cr ON fc.crop_id=cr.crop_id
    LEFT JOIN crop_monitoring cm ON cm.farmer_crop_id=fc.id $WHERE
    GROUP BY cr.crop_name ORDER BY total_yield DESC",$params)->fetchAll(PDO::FETCH_ASSOC);

/* ================================================================
   INPUTS INVENTORY
   ================================================================ */
$stmtI = $pdo->prepare("SELECT COALESCE(SUM(inv.quantity),0) FROM inputs_inventory inv JOIN input_items ii ON inv.input_item_id=ii.id JOIN input_types it ON ii.input_type_id=it.id WHERE it.name=?");
$stmtI->execute(['Fertilizer']); $fertilizer = $stmtI->fetchColumn() ?: 0;
$stmtI->execute(['Seeds']);      $seeds      = $stmtI->fetchColumn() ?: 0;
$stmtI->execute(['Equipment']);  $equipment  = $stmtI->fetchColumn() ?: 0;

/* ================================================================
   SMART INSIGHTS
   ================================================================ */
$insights = [];
if (!empty($barangayRows) && ($barangayRows[0]['total_yield'] ?? 0) > 0)
    $insights[] = ['icon'=>'🌾','text'=> htmlspecialchars($barangayRows[0]['barangay']).' leads in total yield this period.'];
if ($fertilizer < 50)
    $insights[] = ['icon'=>'⚠️','text'=>'Fertilizer stock is low ('.number_format($fertilizer).' kg). Consider restocking.'];
if ($mostProfit !== 'N/A')
    $insights[] = ['icon'=>'💰','text'=> htmlspecialchars($mostProfit).' is the most profitable crop this period.'];
if ($totalProd > 0)
    $insights[] = ['icon'=>'📈','text'=>'Total production recorded: '.number_format($totalProd,2).' tons.'];
if (empty($insights))
    $insights[] = ['icon'=>'ℹ️','text'=>'Add crop monitoring records to generate insights.'];

/* ================================================================
   INCLUDE LAYOUT
   ================================================================ */
ob_start();
include "includes/layout_top.php";
echo str_replace('../assets/', 'assets/', ob_get_clean());
?>

<!-- ============================================================
     GOOGLE FONTS + DASHBOARD STYLES
     ============================================================ -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
/* ── BASE ── */
*{box-sizing:border-box;}
body{
    font-family:'Poppins','Segoe UI',sans-serif!important;
    background:#eef2ee!important;
    background-image:linear-gradient(160deg,#e8f5e9 0%,#f1f8e9 50%,#f9fbe7 100%)!important;
    background-attachment:fixed!important;
}

/* ── CANVAS OVERRIDES ── */
#productionChart{height:170px!important;width:100%!important;margin-top:8px;}
#trendChart     {height:155px!important;width:100%!important;margin-top:8px;}
#profitChart    {height:130px!important;width:130px!important;margin-top:0;}

/* ── PANEL BASE OVERRIDE (fight layout_top.php) ── */
.panel{
    background:#fff!important;
    border-radius:14px!important;
    padding:0!important;
    box-shadow:0 2px 14px rgba(0,0,0,.07)!important;
    border:1px solid rgba(200,230,201,.5)!important;
    height:auto!important;
    overflow:hidden!important;
    transition:box-shadow .2s!important;
}
.panel:hover{box-shadow:0 6px 22px rgba(26,92,42,.11)!important;}
.panel h5{font-weight:700;color:#fff;margin-bottom:0;font-size:12px;}
.panel p{background:none!important;padding:0!important;border-radius:0!important;margin-bottom:0!important;display:block!important;font-size:inherit!important;}
.panel-body{padding:12px 16px!important;}

/* ── TOPBAR ── */
.topbar{
    display:flex!important;
    align-items:center!important;
    justify-content:space-between!important;
    padding:13px 28px!important;
    background:#fff!important;
    border-bottom:1px solid #e3ede3!important;
    margin-bottom:0!important;
    border-radius:0!important;
    box-shadow:0 2px 14px rgba(0,0,0,.07)!important;
    position:sticky!important;
    top:0!important;
    z-index:50!important;
}
.sys-title{
    font-family:'Poppins',sans-serif!important;
    font-size:14px!important;
    font-weight:700!important;
    color:#1a5c2a!important;
    display:flex!important;
    align-items:center!important;
    gap:9px!important;
}
.sys-title .title-icon{
    font-size:20px;
    background:linear-gradient(135deg,#dcfce7,#bbf7d0);
    padding:6px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 2px 8px rgba(22,163,74,.2);
}
.tb-right{display:flex!important;align-items:center!important;gap:10px!important;}
.btn-x{
    display:inline-flex!important;align-items:center!important;gap:6px!important;
    padding:8px 16px!important;border-radius:10px!important;
    font-size:12px!important;font-weight:600!important;
    font-family:'Poppins',sans-serif!important;
    cursor:pointer!important;text-decoration:none!important;
    transition:all .2s!important;letter-spacing:.2px!important;
}
.btn-outline{
    background:#fff!important;border:1.5px solid #1a5c2a!important;
    color:#1a5c2a!important;box-shadow:0 2px 8px rgba(26,92,42,.1)!important;
}
.btn-outline:hover{background:#f1f8e9!important;transform:translateY(-1px)!important;box-shadow:0 4px 14px rgba(26,92,42,.18)!important;}
.btn-solid{
    background:linear-gradient(135deg,#1a5c2a,#2e7d32)!important;
    color:#fff!important;border:none!important;
    box-shadow:0 4px 12px rgba(26,92,42,.35)!important;
}
.btn-solid:hover{transform:translateY(-1px)!important;box-shadow:0 6px 18px rgba(26,92,42,.4)!important;}
.admin-pill{
    display:flex;align-items:center;gap:8px;
    background:#f9fafb;border:1px solid #e5e7eb;
    border-radius:24px;padding:5px 14px 5px 5px;
    box-shadow:0 2px 8px rgba(0,0,0,.06);
}
.admin-pill img{width:30px;height:30px;border-radius:50%;border:2px solid #c8e6c9;}
.admin-pill span{font-size:12px;font-weight:600;color:#374151;font-family:'Poppins',sans-serif;}

/* ── DASH CONTENT WRAPPER ── */
.dash-content{padding:14px 20px;}

/* ── FILTER BAR ── */
.filter-bar{
    background:#fff!important;border-radius:12px!important;
    padding:10px 16px!important;display:flex!important;
    flex-wrap:wrap!important;gap:8px!important;align-items:flex-end!important;
    margin-bottom:12px!important;
    box-shadow:0 2px 12px rgba(0,0,0,.07)!important;
    border:1px solid rgba(200,230,201,.5)!important;
}
.fb-group label{
    font-family:'Poppins',sans-serif!important;font-size:10px!important;
    font-weight:700!important;color:#9ca3af!important;
    text-transform:uppercase!important;letter-spacing:.8px!important;
    display:block!important;margin-bottom:5px!important;
}
.fb-group select{
    font-family:'Poppins',sans-serif!important;
    border:1.5px solid #e5e7eb!important;border-radius:10px!important;
    padding:8px 12px!important;font-size:12px!important;color:#374151!important;
    background:#f9fafb!important;min-width:118px!important;
    transition:all .2s!important;cursor:pointer!important;
}
.fb-group select:focus{
    outline:none!important;border-color:#1a5c2a!important;
    box-shadow:0 0 0 3px rgba(26,92,42,.12)!important;background:#fff!important;
}
.btn-filter{
    font-family:'Poppins',sans-serif!important;
    padding:9px 22px!important;
    background:linear-gradient(135deg,#1a5c2a,#2e7d32)!important;
    color:#fff!important;border:none!important;border-radius:10px!important;
    font-size:12px!important;font-weight:700!important;cursor:pointer!important;
    box-shadow:0 4px 12px rgba(26,92,42,.35)!important;
    transition:all .2s!important;display:inline-flex!important;
    align-items:center!important;gap:6px!important;
}
.btn-filter:hover{transform:translateY(-1px)!important;box-shadow:0 6px 18px rgba(26,92,42,.4)!important;}
.btn-reset{
    font-family:'Poppins',sans-serif!important;
    padding:9px 16px!important;background:#f3f4f6!important;
    color:#6b7280!important;border:1.5px solid #e5e7eb!important;
    border-radius:10px!important;font-size:12px!important;font-weight:600!important;
    cursor:pointer!important;display:inline-flex!important;
    align-items:center!important;gap:5px!important;
    transition:all .2s!important;text-decoration:none!important;
}
.btn-reset:hover{background:#e5e7eb!important;color:#374151!important;}

/* ── KPI GRID ── */
.kpi-grid{
    display:grid!important;
    grid-template-columns:repeat(6,1fr)!important;
    gap:10px!important;margin-bottom:12px!important;
}
@media(max-width:1300px){.kpi-grid{grid-template-columns:repeat(3,1fr)!important;}}
@media(max-width:900px) {.kpi-grid{grid-template-columns:repeat(2,1fr)!important;}}

.kpi-card{
    background:#fff!important;border-radius:12px!important;
    padding:12px 14px!important;
    box-shadow:0 2px 12px rgba(0,0,0,.07)!important;
    display:flex!important;align-items:center!important;gap:10px!important;
    transition:all .25s ease!important;
    border:1px solid rgba(200,230,201,.4)!important;
    position:relative!important;overflow:hidden!important;
}
.kpi-card::before{
    content:''!important;position:absolute!important;
    top:0!important;left:0!important;right:0!important;height:3px!important;
    background:linear-gradient(90deg,#1a5c2a,#4caf50)!important;
    border-radius:12px 12px 0 0!important;
}
.kpi-card:hover{
    transform:translateY(-3px)!important;
    box-shadow:0 8px 24px rgba(26,92,42,.14)!important;
}
.kpi-ico{
    width:42px!important;height:42px!important;border-radius:12px!important;
    display:flex!important;align-items:center!important;
    justify-content:center!important;font-size:20px!important;flex-shrink:0!important;
}
.ico-g{background:linear-gradient(135deg,#dcfce7,#bbf7d0)!important;}
.ico-t{background:linear-gradient(135deg,#ccfbf1,#99f6e4)!important;}
.ico-y{background:linear-gradient(135deg,#fef9c3,#fef08a)!important;}
.ico-r{background:linear-gradient(135deg,#fee2e2,#fecaca)!important;}
.ico-b{background:linear-gradient(135deg,#dbeafe,#bfdbfe)!important;}
.ico-p{background:linear-gradient(135deg,#ede9fe,#ddd6fe)!important;}
.kpi-lbl{
    font-family:'Poppins',sans-serif!important;font-size:9px!important;
    font-weight:700!important;color:#9ca3af!important;
    text-transform:uppercase!important;letter-spacing:.7px!important;margin-bottom:2px!important;
}
.kpi-val{
    font-family:'Poppins',sans-serif!important;font-size:18px!important;
    font-weight:800!important;color:#111827!important;line-height:1!important;
}
.kpi-sub{font-family:'Poppins',sans-serif!important;font-size:9px!important;color:#9ca3af!important;margin-top:2px!important;}

/* ── GRIDS ── */
.g84{display:grid!important;grid-template-columns:2fr 1fr!important;gap:12px!important;margin-bottom:12px!important;}
.g66{display:grid!important;grid-template-columns:1fr 1fr!important;gap:12px!important;margin-bottom:12px!important;}
@media(max-width:1050px){.g84,.g66{grid-template-columns:1fr!important;}}

/* ── P-TITLE — green header bar like Image 3 ── */
.p-title{
    font-family:'Poppins',sans-serif!important;font-size:12px!important;
    font-weight:700!important;color:#fff!important;
    margin:0 0 0 0!important;
    padding:10px 16px!important;
    background:linear-gradient(90deg,#1a5c2a,#2e7d32)!important;
    border-radius:0!important;
    display:flex!important;
    align-items:center!important;justify-content:space-between!important;
    letter-spacing:.2px!important;
}
.p-title small{font-size:10px!important;color:rgba(255,255,255,.7)!important;font-weight:500!important;}
#toggleBtn{
    cursor:pointer!important;padding:4px 12px!important;
    background:rgba(255,255,255,.2)!important;border:1.5px solid rgba(255,255,255,.5)!important;
    border-radius:8px!important;font-size:10px!important;font-weight:600!important;
    color:#fff!important;transition:all .2s!important;
    font-family:'Poppins',sans-serif!important;
}
#toggleBtn:hover{background:rgba(255,255,255,.35)!important;}

/* ── AVAILABLE INPUT ── */
.inp-row{
    display:flex!important;align-items:center!important;
    justify-content:space-between!important;
    padding:8px 0!important;border-bottom:1px solid #f3f4f6!important;
}
.inp-row:last-of-type{border-bottom:none!important;}
.inp-lft{
    display:flex!important;align-items:center!important;gap:8px!important;
    font-size:11.5px!important;font-weight:600!important;color:#374151!important;
    font-family:'Poppins',sans-serif!important;
}
.inp-val{
    font-size:12px!important;font-weight:800!important;color:#1a5c2a!important;
    font-family:'Poppins',sans-serif!important;
    background:#f1f8e9!important;padding:3px 10px!important;
    border-radius:20px!important;border:1px solid #c8e6c9!important;
}

/* ── SMART INSIGHTS ── */
.ins-item{
    display:flex!important;align-items:flex-start!important;gap:8px!important;
    padding:7px 10px!important;border-radius:8px!important;
    font-size:11px!important;color:#374151!important;margin-bottom:5px!important;
    background:#f9fafb!important;border:1px solid #f3f4f6!important;
    font-family:'Poppins',sans-serif!important;line-height:1.4!important;
}
.ins-ico{font-size:14px!important;flex-shrink:0!important;margin-top:1px!important;}

/* ── DONUT / PROFITABILITY ── */
.donut-wrap{display:flex!important;align-items:center!important;gap:24px!important;padding:10px 0!important;}
.d-leg{display:flex!important;flex-direction:column!important;gap:10px!important;}
.d-leg-item{
    display:flex!important;align-items:center!important;gap:8px!important;
    font-size:12px!important;font-family:'Poppins',sans-serif!important;color:#374151!important;
}
.dot{width:10px!important;height:10px!important;border-radius:50%!important;flex-shrink:0!important;}

/* ── TOP BARANGAYS TABLE ── */
.dtbl{width:100%!important;border-collapse:collapse!important;font-size:12px!important;font-family:'Poppins',sans-serif!important;}
.dtbl thead th{
    background:linear-gradient(135deg,#f1f8e9,#e8f5e9)!important;
    padding:11px 12px!important;font-size:10px!important;font-weight:700!important;
    color:#2e7d32!important;text-transform:uppercase!important;
    letter-spacing:.7px!important;text-align:left!important;
    border-bottom:2px solid #c8e6c9!important;
}
.dtbl tbody td{
    padding:12px!important;border-bottom:1px solid #f3f4f6!important;
    color:#374151!important;vertical-align:middle!important;font-size:12px!important;
}
.dtbl tbody tr:last-child td{border-bottom:none!important;}
.dtbl tbody tr:hover td{background:#f9fbe7!important;}

/* ── RANK BADGES ── */
.rnk{
    width:24px!important;height:24px!important;border-radius:50%!important;
    font-size:10px!important;font-weight:800!important;
    display:flex!important;align-items:center!important;justify-content:center!important;
    font-family:'Poppins',sans-serif!important;
}
.rk1{background:linear-gradient(135deg,#fde68a,#f59e0b)!important;color:#78350f!important;box-shadow:0 2px 8px rgba(245,158,11,.4)!important;}
.rk2{background:linear-gradient(135deg,#e5e7eb,#d1d5db)!important;color:#374151!important;}
.rk3{background:linear-gradient(135deg,#fde68a,#d97706)!important;color:#78350f!important;}
.rkn{background:#f3f4f6!important;color:#9ca3af!important;}

/* ── STATUS BADGES ── */
.bdg{
    padding:4px 12px!important;border-radius:20px!important;font-size:10px!important;
    font-weight:700!important;display:inline-block!important;
    font-family:'Poppins',sans-serif!important;letter-spacing:.3px!important;
}
.bdg-h{background:linear-gradient(135deg,#dcfce7,#bbf7d0)!important;color:#15803d!important;border:1px solid #86efac!important;}
.bdg-m{background:linear-gradient(135deg,#fef9c3,#fef08a)!important;color:#a16207!important;border:1px solid #fde047!important;}
.bdg-l{background:linear-gradient(135deg,#fee2e2,#fecaca)!important;color:#b91c1c!important;border:1px solid #fca5a5!important;}

/* ── PROGRESS BARS ── */
.bar-wrap{width:100%!important;background:#f3f4f6!important;border-radius:6px!important;height:9px!important;overflow:hidden!important;}
.bar-fill{height:100%!important;border-radius:6px!important;transition:width .6s ease!important;}

/* ── LOAD ANIMATIONS ── */
@keyframes fadeUp{from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:translateY(0);}}
.kpi-card{animation:fadeUp .4s ease both;}
.kpi-card:nth-child(1){animation-delay:.05s;}
.kpi-card:nth-child(2){animation-delay:.10s;}
.kpi-card:nth-child(3){animation-delay:.15s;}
.kpi-card:nth-child(4){animation-delay:.20s;}
.kpi-card:nth-child(5){animation-delay:.25s;}
.kpi-card:nth-child(6){animation-delay:.30s;}
.panel{animation:fadeUp .45s ease .15s both;}

/* ── PRINT ── */
@media print{
    .filter-bar,.btn-x,.topbar .tb-right,.btn-filter,.btn-reset{display:none!important;}
    .sidebar{display:none!important;}
    .main{margin-left:0!important;}
}
</style>

<!-- ============================================================
     TOPBAR
     ============================================================ -->
<div class="topbar">
    <div class="sys-title">
        <span class="title-icon">🌿</span>
        Agricultural Crop Monitoring and Farm Input Distribution Analytics System
    </div>
    <div class="tb-right">
        <a href="?<?= http_build_query(array_merge($_GET,['export'=>'csv'])) ?>" class="btn-x btn-outline">
            ⬇ Export CSV
        </a>
        <button class="btn-x btn-solid" onclick="window.print()">
            🖨 Print
        </button>
        <div class="admin-pill">
            <img src="assets/img/user-setting.png" alt="admin">
            <span><?= htmlspecialchars($_SESSION['role']) ?></span>
        </div>
    </div>
</div>

<!-- ============================================================
     DASHBOARD CONTENT WRAPPER
     ============================================================ -->
<div class="dash-content">

<!-- ============================================================
     FILTER BAR
     ============================================================ -->
<form method="GET" class="filter-bar">
    <div class="fb-group">
        <label>Year</label>
        <select name="year">
            <option value="">All Years</option>
            <?php foreach($optYears as $y): ?>
                <option value="<?=$y?>" <?=$fYear==$y?'selected':''?>><?=$y?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fb-group">
        <label>Season</label>
        <select name="season">
            <option value="">All Seasons</option>
            <option value="wet" <?=$fSeason==='wet'?'selected':''?>>🌧 Wet (Jun–Nov)</option>
            <option value="dry" <?=$fSeason==='dry'?'selected':''?>>☀️ Dry (Dec–May)</option>
        </select>
    </div>
    <div class="fb-group">
        <label>Month</label>
        <select name="month">
            <option value="">All Months</option>
            <?php $mn=["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
            foreach($mn as $mi=>$ml): ?>
                <option value="<?=$mi+1?>" <?=$fMonth==$mi+1?'selected':''?>><?=$ml?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fb-group">
        <label>Barangay</label>
        <select name="barangay">
            <option value="">All Barangays</option>
            <?php foreach($optBarangays as $b): ?>
                <option value="<?=htmlspecialchars($b)?>" <?=$fBarangay===$b?'selected':''?>><?=htmlspecialchars($b)?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fb-group">
        <label>Crop</label>
        <select name="crop_id">
            <option value="">All Crops</option>
            <?php foreach($optCrops as $c): ?>
                <option value="<?=$c['crop_id']?>" <?=$fCropId==$c['crop_id']?'selected':''?>><?=htmlspecialchars($c['crop_name'])?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="fb-group">
        <label>Farmer</label>
        <select name="farmer_id">
            <option value="">All Farmers</option>
            <?php foreach($optFarmers as $f): ?>
                <option value="<?=$f['id']?>" <?=$fFarmerId==$f['id']?'selected':''?>><?=htmlspecialchars($f['nm'])?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn-filter">🔍 Filter</button>
    <a href="dashboard.php" class="btn-reset">✕ Reset</a>
</form>

<!-- ============================================================
     KPI CARDS
     ============================================================ -->
<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-ico ico-g">👨‍🌾</div>
        <div>
            <div class="kpi-lbl">Total Farmers</div>
            <div class="kpi-val"><?= number_format($totalFarmers) ?></div>
            <div class="kpi-sub">Registered</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-ico ico-t">🌿</div>
        <div>
            <div class="kpi-lbl">Cultivated Land</div>
            <div class="kpi-val"><?= number_format($totalLand) ?></div>
            <div class="kpi-sub">Total Area (ha)</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-ico ico-y">🌱</div>
        <div>
            <div class="kpi-lbl">Most Planted Crop</div>
            <div class="kpi-val" style="font-size:16px;"><?= htmlspecialchars($mostPlanted) ?></div>
            <div class="kpi-sub">This Season</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-ico ico-r">💰</div>
        <div>
            <div class="kpi-lbl">Most Profitable Crop</div>
            <div class="kpi-val" style="font-size:16px;"><?= htmlspecialchars($mostProfit) ?></div>
            <div class="kpi-sub">This Season</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-ico ico-b">📦</div>
        <div>
            <div class="kpi-lbl">Total Production</div>
            <div class="kpi-val"><?= number_format($totalProd,1) ?></div>
            <div class="kpi-sub">tons recorded</div>
        </div>
    </div>
    <div class="kpi-card">
        <div class="kpi-ico ico-p">📊</div>
        <div>
            <div class="kpi-lbl">Avg Yield/Record</div>
            <div class="kpi-val"><?= number_format($avgYield,1) ?></div>
            <div class="kpi-sub">tons</div>
        </div>
    </div>
</div>

<!-- ============================================================
     ROW 1: Monthly Chart + Input & Insights
     ============================================================ -->
<div class="g84">
    <div class="panel">
        <div class="p-title">
            📊 Crop Production Overview
            <span id="toggleBtn" onclick="toggleChart()">Switch to Line</span>
        </div>
        <div class="panel-body">
        <canvas id="productionChart"></canvas>
        </div>
    </div>

    <div class="panel" style="display:flex;flex-direction:column;">
        <div class="p-title">📦 Available Input</div>
        <div class="panel-body" style="flex:1;display:flex;flex-direction:column;gap:0;">
        <div class="inp-row">
            <div class="inp-lft">🌱 Fertilizers</div>
            <div class="inp-val"><?= number_format($fertilizer) ?> kg</div>
        </div>
        <div class="inp-row">
            <div class="inp-lft">🌾 Seeds</div>
            <div class="inp-val"><?= number_format($seeds) ?> kg</div>
        </div>
        <div class="inp-row">
            <div class="inp-lft">🚜 Equipment</div>
            <div class="inp-val"><?= number_format($equipment) ?> units</div>
        </div>
        </div>

        <div class="p-title" style="margin-top:0;">💡 Smart Insights</div>
        <div class="panel-body" style="padding-top:8px!important;">
        <?php foreach($insights as $ins): ?>
        <div class="ins-item">
            <span class="ins-ico"><?= $ins['icon'] ?></span>
            <span><?= $ins['text'] ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ============================================================
     ROW 2: Profitability Donut + Top Barangays
     ============================================================ -->
<div class="g66">
    <div class="panel">
        <div class="p-title">📈 Profitability Analysis</div>
        <div class="panel-body">
        <div class="donut-wrap">
            <div style="position:relative;width:130px;flex-shrink:0;">
                <canvas id="profitChart" width="130" height="130"></canvas>
                <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;pointer-events:none;">
                    <div style="font-size:18px;font-weight:800;color:#111;font-family:'Poppins',sans-serif;"><?= $profPct ?>%</div>
                    <div style="font-size:9px;color:#6b7280;font-family:'Poppins',sans-serif;">Profitable</div>
                </div>
            </div>
            <div class="d-leg">
                <div class="d-leg-item">
                    <span class="dot" style="background:#16a34a;"></span>
                    Profitable — <b style="margin-left:4px;"><?= number_format($profitable) ?></b>
                </div>
                <div class="d-leg-item">
                    <span class="dot" style="background:#eab308;"></span>
                    Non Profitable — <b style="margin-left:4px;"><?= number_format($notProfitable) ?></b>
                </div>
                <div style="font-size:10px;color:#9ca3af;margin-top:6px;font-family:'Poppins',sans-serif;">
                    Threshold: 100 tons/record
                </div>
            </div>
        </div>
        </div>
    </div>

    <div class="panel">
        <div class="p-title">🏆 Top Barangays by Yield</div>
        <div class="panel-body" style="padding:0!important;">
        <table class="dtbl">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Barangay</th>
                    <th>Crops</th>
                    <th>Yield (tons)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($barangayRows as $i=>$row):
                $avg = $row['avg_yield'] ?? 0;
                if($avg>=150){$s='High';$bc='bdg-h';}
                elseif($avg>=80){$s='Moderate';$bc='bdg-m';}
                else{$s='Low';$bc='bdg-l';}
                $rnks=['rk1','rk2','rk3','rkn','rkn'];
            ?>
            <tr>
                <td><div class="rnk <?=$rnks[$i]?>"><?=$i+1?></div></td>
                <td><b><?= htmlspecialchars($row['barangay']) ?></b></td>
                <td style="color:#6b7280;font-size:11px;"><?= htmlspecialchars($row['crops']?:'N/A') ?></td>
                <td><b><?= number_format($row['total_yield'],1) ?></b></td>
                <td><span class="bdg <?=$bc?>"><?=$s?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($barangayRows)): ?>
            <tr>
                <td colspan="5" style="text-align:center;color:#9ca3af;padding:20px;font-family:'Poppins',sans-serif;">
                    No data for selected filters.
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- ============================================================
     ROW 3: Trend Analysis + Comparative Analytics
     ============================================================ -->
<div class="g66">
    <div class="panel">
        <div class="p-title">
            📊 Trend Analysis
            <small>Year-over-Year</small>
        </div>
        <canvas id="trendChart"></canvas>
    </div>

    <div class="panel">
        <div class="p-title">🌾 Comparative Crop Analytics</div>
        <?php
        $maxY = max(array_column($compareRows,'total_yield') ?: [1]);
        $cropColors = ['rice'=>'#16a34a','corn'=>'#eab308','tomato'=>'#dc2626'];
        foreach($compareRows as $cr):
            $pct = $maxY > 0 ? ($cr['total_yield'] / $maxY * 100) : 0;
            $col = $cropColors[strtolower($cr['crop_name'])] ?? '#6b7280';
        ?>
        <div style="margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:6px;font-family:'Poppins',sans-serif;">
                <span style="font-weight:700;color:#1a3d1f;"><?= htmlspecialchars($cr['crop_name']) ?></span>
                <span style="color:#6b7280;"><?= number_format($cr['total_yield'],1) ?> tons &middot; <?= number_format($cr['total_area'],1) ?> ha &middot; <?= $cr['farmers'] ?> farmer(s)</span>
            </div>
            <div class="bar-wrap">
                <div class="bar-fill" style="width:<?= $pct ?>%;background:<?= $col ?>;"></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($compareRows)): ?>
        <div style="color:#9ca3af;text-align:center;padding:24px;font-size:12px;font-family:'Poppins',sans-serif;">
            No data for selected filters.
        </div>
        <?php endif; ?>
    </div>
</div>

</div><!-- end .dash-content -->

<!-- ============================================================
     CHARTS JS
     ============================================================ -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
/* ── Chart defaults — Poppins font ── */
Chart.defaults.font.family = "'Poppins', 'Segoe UI', sans-serif";

/* ── Production chart — bar/line toggle ── */
let chartType = 'bar';
const prodDatasets = [
    {
        label: 'Rice',
        data: <?= json_encode($chartRice) ?>,
        backgroundColor: 'rgba(22,163,74,.75)',
        borderColor: '#16a34a',
        borderRadius: 6,
        tension: .4, fill: false, pointRadius: 4
    },
    {
        label: 'Corn',
        data: <?= json_encode($chartCorn) ?>,
        backgroundColor: 'rgba(234,179,8,.75)',
        borderColor: '#eab308',
        borderRadius: 6,
        tension: .4, fill: false, pointRadius: 4
    },
    {
        label: 'Tomato',
        data: <?= json_encode($chartTomato) ?>,
        backgroundColor: 'rgba(220,38,38,.75)',
        borderColor: '#dc2626',
        borderRadius: 6,
        tension: .4, fill: false, pointRadius: 4
    }
];
const prodOpts = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { position: 'top', labels: { boxWidth: 11, font: { size: 11, family: 'Poppins' }, padding: 14 } },
        tooltip: { mode: 'index', intersect: false }
    },
    scales: {
        y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 } } },
        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
    }
};
let prodChart = new Chart(
    document.getElementById('productionChart'),
    { type: chartType, data: { labels: <?= json_encode($months) ?>, datasets: prodDatasets }, options: prodOpts }
);

function toggleChart() {
    chartType = chartType === 'bar' ? 'line' : 'bar';
    document.getElementById('toggleBtn').textContent = chartType === 'bar' ? 'Switch to Line' : 'Switch to Bar';
    prodChart.destroy();
    prodChart = new Chart(
        document.getElementById('productionChart'),
        { type: chartType, data: { labels: <?= json_encode($months) ?>, datasets: prodDatasets }, options: prodOpts }
    );
}

/* ── Profitability donut ── */
new Chart(document.getElementById('profitChart'), {
    type: 'doughnut',
    data: {
        labels: ['Profitable', 'Non Profitable'],
        datasets: [{
            data: [<?= $profitable ?>, <?= $notProfitable ?>],
            backgroundColor: ['#16a34a', '#eab308'],
            borderWidth: 0,
            hoverOffset: 8
        }]
    },
    options: {
        cutout: '74%',
        responsive: false,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.raw } }
        }
    }
});

/* ── Trend chart ── */
new Chart(document.getElementById('trendChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($trendYears) ?>,
        datasets: [
            { label: 'Rice',   data: <?= json_encode($trendRice) ?>,   backgroundColor: 'rgba(22,163,74,.85)',  borderRadius: 6 },
            { label: 'Corn',   data: <?= json_encode($trendCorn) ?>,   backgroundColor: 'rgba(234,179,8,.85)',  borderRadius: 6 },
            { label: 'Tomato', data: <?= json_encode($trendTomato) ?>, backgroundColor: 'rgba(220,38,38,.85)', borderRadius: 6 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 11, font: { size: 11, family: 'Poppins' }, padding: 14 } },
            tooltip: { mode: 'index', intersect: false }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: { beginAtZero: true, grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 } } }
        }
    }
});
</script>

<?php include "includes/layout_bottom.php"; ?>