<?php
// ============================================================
//  index.php — Selvi Resort & Lawn (Full merged website)
//  Merges: index.html design + dynamic DB features + PDF receipt
// ============================================================
require_once __DIR__ . '/includes/config.php';
$pdo = getDB();

// Load active packages from DB
$packages = $pdo->query("SELECT *, COALESCE(is_available, 1) as is_available FROM packages WHERE is_active=1 ORDER BY sort_order ASC")->fetchAll();

// Load settings
$sRows = $pdo->query("SELECT setting_key, setting_val FROM settings")->fetchAll();
$s = []; foreach ($sRows as $r) $s[$r['setting_key']] = $r['setting_val'];
$siteName = $s['site_name']      ?? 'Selvi Resort & Lawn';
$phone1   = $s['site_phone1']    ?? '+91 98765 43210';
$phone2   = $s['site_phone2']    ?? '+91 98765 43211';
$email    = $s['site_email']     ?? 'info@selviresort.com';
$address  = $s['site_address']   ?? 'Main Highway Road, Tamil Nadu';
$whatsapp = $s['site_whatsapp']  ?? '919876543210';
$mapsUrl  = $s['google_maps_url']?? 'https://maps.google.com';

// ── Calendar blocked dates ───────────────────────────────────
// Block every day from event_date up to (not including) checkout_date
// checkout_date itself is FREE — checkout 10AM, next checkin 1PM = no conflict
$bookedRanges = $pdo->query("
    SELECT event_date,
           COALESCE(checkout_date, DATE_ADD(event_date, INTERVAL 1 DAY)) AS checkout_date
    FROM bookings
    WHERE status IN ('new','contacted','confirmed','completed')
      AND event_date IS NOT NULL
      AND event_date >= CURDATE()
")->fetchAll();
$blockedDates = [];
foreach ($bookedRanges as $row) {
    $s = new DateTime($row['event_date']);
    $e = new DateTime($row['checkout_date']);
    for ($d = clone $s; $d < $e; $d->modify('+1 day')) {
        $blockedDates[] = $d->format('Y-m-d');
    }
}
$blockedDates     = array_values(array_unique($blockedDates));
$blockedDatesJSON = json_encode($blockedDates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($siteName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
<!-- jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<!-- Flatpickr -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<style>

/* ── Flatpickr resort theme ──────────────────────────────── */
.flatpickr-calendar{background:#1a1208!important;border:1px solid rgba(201,169,110,.3)!important;border-radius:4px;box-shadow:0 16px 48px rgba(0,0,0,.6)!important;font-family:'Jost',sans-serif!important}
.flatpickr-months,.flatpickr-weekdays{background:#2c1f0e!important}
.flatpickr-month{color:#c9a96e!important}
.flatpickr-current-month,.flatpickr-current-month .flatpickr-monthDropdown-months,.flatpickr-current-month input.cur-year{color:#c9a96e!important;background:transparent!important}
.flatpickr-prev-month,.flatpickr-next-month{fill:#c9a96e!important}
.flatpickr-prev-month:hover svg,.flatpickr-next-month:hover svg{fill:#e8d5a3!important}
span.flatpickr-weekday{color:rgba(201,169,110,.5)!important;background:transparent!important;font-size:.68rem;letter-spacing:1px}
.flatpickr-day{color:rgba(255,255,255,.78)!important;border-radius:3px!important;border:1px solid transparent!important}
.flatpickr-day:hover:not(.flatpickr-disabled):not(.prevMonthDay):not(.nextMonthDay){background:rgba(201,169,110,.15)!important;border-color:rgba(201,169,110,.3)!important;color:#c9a96e!important}
.flatpickr-day.selected,.flatpickr-day.startRange,.flatpickr-day.endRange,.flatpickr-day.selected:hover,.flatpickr-day.startRange:hover,.flatpickr-day.endRange:hover{background:#c9a96e!important;border-color:#c9a96e!important;color:#1a1208!important;font-weight:700!important}
.flatpickr-day.inRange{background:rgba(201,169,110,.2)!important;border-color:rgba(201,169,110,.1)!important;color:#e8d5a3!important}
.flatpickr-day.today:not(.selected):not(.startRange):not(.endRange){border-color:rgba(201,169,110,.5)!important;color:#c9a96e!important;font-weight:600!important}
.flatpickr-day.flatpickr-disabled,.flatpickr-day.flatpickr-disabled:hover{background:rgba(220,38,38,.08)!important;color:rgba(200,60,60,.4)!important;text-decoration:line-through!important;cursor:not-allowed!important;border-color:transparent!important}
.flatpickr-day.prevMonthDay,.flatpickr-day.nextMonthDay{color:rgba(255,255,255,.15)!important}
#b-date-range{cursor:pointer!important}
:root{--gold:#c9a96e;--gold-light:#e8d5a3;--dark:#1a1208;--dark2:#2c1f0e;--cream:#faf6ef;--green:#3d5a3e;--text:#4a3520}
*{margin:0;padding:0;box-sizing:border-box}html{scroll-behavior:smooth}
body{background:var(--cream);color:var(--text);font-family:'Jost',sans-serif;overflow-x:hidden}

/* ── NAV ── */
nav{position:fixed;top:0;width:100%;z-index:200;background:rgba(26,18,8,.97);backdrop-filter:blur(12px);padding:0 40px;display:flex;align-items:center;justify-content:space-between;height:70px;border-bottom:1px solid rgba(201,169,110,.22)}
.logo{text-decoration:none}.logo-name{font-family:'Cormorant Garamond',serif;color:var(--gold);font-size:1.4rem;font-weight:600;letter-spacing:2px;display:block}
.logo-sub{font-size:.58rem;letter-spacing:5px;color:rgba(201,169,110,.55);text-transform:uppercase}
.nav-links{display:flex;gap:22px;list-style:none;align-items:center}
.nav-links a{color:rgba(255,255,255,.72);text-decoration:none;font-size:.72rem;letter-spacing:2px;text-transform:uppercase;font-weight:400;transition:color .3s;cursor:pointer;padding-bottom:2px;border-bottom:1px solid transparent}
.nav-links a:hover,.nav-links a.active{color:var(--gold);border-bottom-color:var(--gold)}
.nav-links a.btn-book{background:var(--gold);color:var(--dark)!important;padding:8px 18px;border:none;font-weight:600;border-bottom:none}
.nav-links a.btn-book:hover{background:var(--gold-light)}
.hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer}.hamburger span{width:24px;height:2px;background:var(--gold);display:block}
.mob-menu{display:none;position:fixed;top:70px;left:0;right:0;background:rgba(26,18,8,.98);padding:20px 30px;z-index:199;flex-direction:column;gap:16px;border-bottom:1px solid rgba(201,169,110,.2)}
.mob-menu.open{display:flex}.mob-menu a{color:rgba(255,255,255,.75);text-decoration:none;font-size:.85rem;letter-spacing:2px;text-transform:uppercase;cursor:pointer;padding:8px 0;border-bottom:1px solid rgba(201,169,110,.1)}

/* ── PAGE SYSTEM ── */
.page{display:none}.page.active{display:block}
section.sec{padding:90px 40px}
.section-header{text-align:center;margin-bottom:60px}
.sec-tag{display:block;color:var(--gold);font-size:.7rem;letter-spacing:6px;text-transform:uppercase;margin-bottom:8px}
.sec-title{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,3rem);color:var(--dark);font-weight:400;line-height:1.2}
.sec-title.light{color:#fff}.sec-line{width:50px;height:2px;background:var(--gold);margin:14px auto 0}
.btn-gold{display:inline-block;background:var(--gold);color:var(--dark);padding:13px 32px;font-size:.78rem;letter-spacing:3px;text-transform:uppercase;font-weight:700;text-decoration:none;border:none;cursor:pointer;font-family:'Jost',sans-serif;transition:all .3s}
.btn-gold:hover{background:var(--gold-light);transform:translateY(-2px)}
.btn-outline{display:inline-block;background:transparent;color:var(--gold);padding:13px 32px;font-size:.78rem;letter-spacing:3px;text-transform:uppercase;font-weight:500;text-decoration:none;border:1px solid var(--gold);cursor:pointer;font-family:'Jost',sans-serif;transition:all .3s}
.btn-outline:hover{background:var(--gold);color:var(--dark)}
@keyframes fadeUp{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:translateY(0)}}
@keyframes bounce{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(6px)}}
@keyframes slideIn{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}

/* ── PAGE BANNER ── */
.page-banner{padding-top:70px;height:360px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--dark) 0%,var(--dark2) 60%,#3d2a10 100%);position:relative;overflow:hidden;text-align:center}
.page-banner::before{content:'';position:absolute;inset:0;opacity:.05;background-image:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23c9a96e' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")}
.pb-inner{position:relative;z-index:2}.pb-tag{color:var(--gold);font-size:.68rem;letter-spacing:7px;text-transform:uppercase;margin-bottom:14px}
.pb-title{font-family:'Cormorant Garamond',serif;font-size:clamp(2.4rem,5vw,4.2rem);color:#fff;font-weight:300;line-height:1.05}
.pb-title em{color:var(--gold);font-style:italic}.pb-line{width:50px;height:2px;background:var(--gold);margin:16px auto 0}

/* ── HOME ── */
.hero{height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1a1208 0%,#2c1f0e 40%,#3d2a10 70%,#1a1208 100%);position:relative;overflow:hidden;text-align:center;padding-top:70px}
.hero::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse at 20% 50%,rgba(61,90,62,.35) 0%,transparent 60%),radial-gradient(ellipse at 80% 50%,rgba(201,169,110,.15) 0%,transparent 60%)}
.hero-pat{position:absolute;inset:0;opacity:.05;background-image:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23c9a96e' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")}
.hero-inner{position:relative;z-index:2;padding:0 20px;animation:fadeUp 1.2s ease}
.hero-tag{color:var(--gold);font-size:.68rem;letter-spacing:8px;text-transform:uppercase;margin-bottom:20px}
.hero h1{font-family:'Cormorant Garamond',serif;font-size:clamp(3.5rem,8vw,7rem);color:#fff;line-height:.95;font-weight:300}
.hero h1 em{color:var(--gold);font-style:italic}
.hero-sub{color:rgba(255,255,255,.55);font-size:.88rem;letter-spacing:3px;margin-top:22px;font-weight:300}
.hero-div{width:60px;height:1px;background:var(--gold);margin:25px auto}
.hero-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap;margin-top:28px}
.scroll-hint{position:absolute;bottom:28px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.28);font-size:.68rem;letter-spacing:3px;text-transform:uppercase;display:flex;flex-direction:column;align-items:center;gap:8px;animation:bounce 2s infinite}
.scroll-hint::after{content:'';width:1px;height:38px;background:rgba(201,169,110,.35)}
.stats-bar{background:var(--gold);padding:24px 40px;display:flex;justify-content:center;gap:55px;flex-wrap:wrap}
.stat{text-align:center}.stat-n{font-family:'Cormorant Garamond',serif;font-size:2.2rem;color:var(--dark);font-weight:600;line-height:1}
.stat-l{font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:rgba(26,18,8,.6);margin-top:3px}
.feat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:22px;max-width:1200px;margin:0 auto}
.feat-card{background:#fff;padding:34px;border:1px solid rgba(201,169,110,.14);transition:all .35s;position:relative;overflow:hidden}
.feat-card::before{content:'';position:absolute;top:0;left:0;width:3px;height:0;background:var(--gold);transition:height .4s}
.feat-card:hover::before{height:100%}.feat-card:hover{transform:translateY(-5px);box-shadow:0 15px 40px rgba(201,169,110,.14)}
.feat-icon{font-size:2.4rem;margin-bottom:16px}.feat-card h3{font-family:'Cormorant Garamond',serif;font-size:1.35rem;color:var(--dark);font-weight:400;margin-bottom:10px}
.feat-card p{font-size:.86rem;color:#776655;line-height:1.85;font-weight:300}
.why-inner{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:70px;align-items:center}
.why-text h2{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,3vw,2.8rem);color:#fff;font-weight:300;line-height:1.2;margin:10px 0 18px}
.why-text h2 em{color:var(--gold);font-style:italic}
.why-text>p{color:rgba(255,255,255,.5);font-size:.88rem;line-height:2;font-weight:300;margin-bottom:28px}
.why-list{list-style:none}.why-list li{padding:10px 0;border-bottom:1px solid rgba(201,169,110,.1);font-size:.86rem;color:rgba(255,255,255,.68);display:flex;gap:12px;align-items:center}
.why-list li::before{content:'✦';color:var(--gold);font-size:.55rem;flex-shrink:0}
.why-boxes{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.wb{padding:28px 18px;text-align:center;border:1px solid rgba(201,169,110,.12)}
.wb:nth-child(2){background:rgba(201,169,110,.06);margin-top:20px}.wb:nth-child(4){background:rgba(61,90,62,.12);margin-top:-20px}
.wb-n{font-family:'Cormorant Garamond',serif;font-size:2.8rem;color:var(--gold);font-weight:600}
.wb-l{font-size:.72rem;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.38);margin-top:5px}
.quote-strip{background:var(--cream);padding:70px 40px;text-align:center}
.quote-strip blockquote{font-family:'Cormorant Garamond',serif;font-size:clamp(1.3rem,2.5vw,1.9rem);color:var(--dark);font-style:italic;max-width:680px;margin:0 auto 18px;line-height:1.65;font-weight:400}
.quote-strip cite{font-size:.78rem;letter-spacing:3px;text-transform:uppercase;color:var(--gold)}
.cta-strip{background:linear-gradient(135deg,var(--green) 0%,#2c4a2c 100%);padding:70px 40px;text-align:center}
.cta-strip h2{font-family:'Cormorant Garamond',serif;font-size:clamp(1.8rem,3.5vw,2.8rem);color:#fff;font-weight:300;margin-bottom:14px}
.cta-strip p{color:rgba(255,255,255,.55);font-size:.88rem;letter-spacing:2px;margin-bottom:32px}
.cta-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}

/* ── ABOUT ── */
.story-wrap{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:65px;align-items:center}
.story-imgs{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.sib{aspect-ratio:1;display:flex;align-items:center;justify-content:center;font-size:3.5rem}
.sib1{background:linear-gradient(135deg,#2c4a2c,#3d5a3e)}.sib2{background:linear-gradient(135deg,#4a3520,#6b4e2f);margin-top:22px}
.sib3{background:linear-gradient(135deg,#1a1208,#2c1f0e);margin-top:-22px}.sib4{background:linear-gradient(135deg,#3d2a10,#5a4030)}
.story-text h2{font-family:'Cormorant Garamond',serif;font-size:clamp(1.8rem,3vw,2.8rem);color:var(--dark);font-weight:300;line-height:1.2;margin:10px 0 18px}
.story-text h2 em{color:var(--gold);font-style:italic}.story-text p{font-size:.88rem;color:#776655;line-height:2;margin-bottom:14px}
.vals-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:2px;max-width:1100px;margin:0 auto}
.val-card{background:rgba(255,255,255,.03);padding:38px 22px;text-align:center;border:1px solid rgba(201,169,110,.08);transition:all .35s}
.val-card:hover{background:rgba(201,169,110,.07);border-color:rgba(201,169,110,.3);transform:translateY(-4px)}
.val-icon{font-size:2.7rem;margin-bottom:16px}.val-card h3{font-family:'Cormorant Garamond',serif;color:var(--gold);font-size:1.25rem;font-weight:400;margin-bottom:10px}
.val-card p{color:rgba(255,255,255,.48);font-size:.82rem;line-height:1.8}
.team-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:28px;max-width:1000px;margin:0 auto}
.team-card{text-align:center}.team-av{width:105px;height:105px;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;font-size:3rem;border:3px solid var(--gold);background:linear-gradient(135deg,var(--dark),var(--dark2))}
.team-card h3{font-family:'Cormorant Garamond',serif;font-size:1.25rem;color:var(--dark);font-weight:400;margin-bottom:4px}
.team-role{font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:10px}
.team-card p{font-size:.82rem;color:#776655;line-height:1.75}
.timeline-wrap{max-width:700px;margin:50px auto 0;position:relative}
.timeline-wrap::before{content:'';position:absolute;left:0;top:0;bottom:0;width:1px;background:rgba(201,169,110,.22)}
.tl-item{padding:0 0 38px 34px;position:relative}
.tl-item::before{content:'';position:absolute;left:-5px;top:5px;width:11px;height:11px;border-radius:50%;background:var(--gold)}
.tl-year{font-family:'Cormorant Garamond',serif;color:var(--gold);font-size:1.45rem;margin-bottom:5px}
.tl-item p{color:rgba(255,255,255,.52);font-size:.86rem;line-height:1.85}

/* ── PACKAGES ── */
.filter-bar{display:flex;gap:11px;justify-content:center;flex-wrap:wrap;margin-bottom:48px}
.fbtn{padding:8px 20px;border:1px solid rgba(201,169,110,.28);background:transparent;color:var(--text);font-family:'Jost',sans-serif;font-size:.75rem;letter-spacing:2px;text-transform:uppercase;cursor:pointer;transition:all .3s}
.fbtn:hover,.fbtn.active{background:var(--gold);border-color:var(--gold);color:var(--dark);font-weight:600}
.pkgs-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:26px;max-width:1250px;margin:0 auto}
.pkg{background:#fff;border:1px solid rgba(201,169,110,.16);overflow:hidden;transition:all .4s;position:relative;display:flex;flex-direction:column}
.pkg:hover{transform:translateY(-7px);box-shadow:0 25px 55px rgba(201,169,110,.16);border-color:var(--gold)}
.pkg.hot{border:2px solid var(--gold)}.pkg.fully-booked{opacity:.75;border-color:rgba(239,68,68,.3)}
.pkg.fully-booked:hover{transform:none;box-shadow:none}
.pkg-badge{position:absolute;top:0;right:0;background:var(--gold);color:var(--dark);font-size:.62rem;letter-spacing:2px;text-transform:uppercase;padding:5px 13px;font-weight:700}
.pkg-badge.full{background:#dc2626;color:#fff}
.pkg-head{background:linear-gradient(135deg,var(--dark),var(--dark2));padding:28px}
.pkg-ico{font-size:2rem;margin-bottom:9px}.pkg-sub{color:rgba(255,255,255,.38);font-size:.68rem;letter-spacing:3px;text-transform:uppercase;margin-bottom:5px}
.pkg-head h3{font-family:'Cormorant Garamond',serif;font-size:1.6rem;color:var(--gold);font-weight:400}
.pkg-body{padding:26px;flex:1}
.pkg-price{font-family:'Cormorant Garamond',serif;font-size:1.9rem;color:var(--dark);font-weight:600;margin-bottom:4px}
.pkg-price span{font-size:.76rem;font-family:'Jost';color:#888;font-weight:400}
.pkg-cap{font-size:.75rem;color:var(--gold);letter-spacing:1px;margin-bottom:18px}
.pkg-feats{list-style:none}.pkg-feats li{padding:8px 0;border-bottom:1px solid rgba(201,169,110,.1);font-size:.84rem;color:var(--text);display:flex;gap:9px;align-items:flex-start}
.pkg-feats li::before{content:'✦';color:var(--gold);font-size:.52rem;margin-top:4px;flex-shrink:0}
.pkg-feats li:last-child{border-bottom:none}
.pkg-foot{padding:18px 26px 26px}
.pkg-btn{display:block;width:100%;padding:12px;background:transparent;border:1px solid var(--gold);color:var(--gold);font-family:'Jost',sans-serif;font-size:.76rem;letter-spacing:3px;text-transform:uppercase;cursor:pointer;transition:all .3s;text-decoration:none;text-align:center;font-weight:500}
.pkg-btn:hover{background:var(--gold);color:var(--dark)}.pkg.hot .pkg-btn{background:var(--gold);color:var(--dark)}
.pkg-btn.disabled{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.3);color:#dc2626;cursor:not-allowed}
.pkg-btn.disabled:hover{background:rgba(239,68,68,.08);color:#dc2626}
.addons-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:18px;max-width:1100px;margin:0 auto}
.addon{background:rgba(255,255,255,.04);border:1px solid rgba(201,169,110,.1);padding:24px 18px;text-align:center;transition:all .3s}
.addon:hover{border-color:var(--gold);background:rgba(201,169,110,.07)}
.addon-ic{font-size:1.9rem;margin-bottom:11px}.addon h4{font-family:'Cormorant Garamond',serif;color:var(--gold);font-size:1.1rem;margin-bottom:7px;font-weight:400}
.addon p{color:rgba(255,255,255,.42);font-size:.78rem;line-height:1.75}.addon-pr{color:var(--gold-light);font-size:.82rem;font-weight:500;margin-top:9px}
.cmp-table{width:100%;max-width:980px;margin:0 auto;border-collapse:collapse}
.cmp-table th{background:var(--dark);color:var(--gold);padding:17px 14px;text-align:center;font-family:'Cormorant Garamond',serif;font-size:.98rem;font-weight:400}
.cmp-table th:first-child{text-align:left}.cmp-table td{padding:13px 14px;text-align:center;border-bottom:1px solid rgba(201,169,110,.11);font-size:.83rem;color:var(--text)}
.cmp-table td:first-child{text-align:left;font-weight:500;color:var(--dark)}.cmp-table tr:hover td{background:rgba(201,169,110,.05)}
.yes{color:#3d5a3e;font-weight:600}.yes::before{content:'✓  '}.no{color:#bbb}

/* ── GALLERY ── */
.gal-masonry{columns:4 240px;gap:11px;max-width:1300px;margin:0 auto}
.gal-item{break-inside:avoid;margin-bottom:11px;position:relative;overflow:hidden;cursor:pointer}
.gal-box{width:100%;position:relative;display:flex;align-items:center;justify-content:center}
.gal-box-inner{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:4rem;transition:transform .5s}
.gal-item:hover .gal-box-inner{transform:scale(1.07)}
.gal-ov{position:absolute;inset:0;background:linear-gradient(to top,rgba(26,18,8,.85) 0%,transparent 55%);opacity:0;transition:.4s;display:flex;align-items:flex-end;padding:18px}
.gal-item:hover .gal-ov{opacity:1}
.gal-ov-cat{color:var(--gold);font-size:.65rem;letter-spacing:2px;text-transform:uppercase;display:block;margin-bottom:3px}
.gal-ov-title{color:#fff;font-family:'Cormorant Garamond',serif;font-size:1.05rem}
.g1{background:linear-gradient(135deg,#2c4a2c,#3d5a3e);padding-top:60%}.g2{background:linear-gradient(135deg,#4a3520,#6b4e2f);padding-top:115%}
.g3{background:linear-gradient(135deg,#1a3a2a,#2c5a3e);padding-top:68%}.g4{background:linear-gradient(135deg,#3a2a1a,#5a4030);padding-top:88%}
.g5{background:linear-gradient(135deg,#2a3a1a,#4a5a2c);padding-top:62%}.g6{background:linear-gradient(135deg,#1a1a3a,#2c2c5a);padding-top:98%}
.g7{background:linear-gradient(135deg,#3a1a1a,#5a2c2c);padding-top:73%}.g8{background:linear-gradient(135deg,#2c3a2c,#4a5a3a);padding-top:83%}
.g9{background:linear-gradient(135deg,#3a2a3a,#5a3a5a);padding-top:63%}.g10{background:linear-gradient(135deg,#1a3a3a,#2c5a5a);padding-top:108%}
.g11{background:linear-gradient(135deg,#3a3a1a,#5a5a2c);padding-top:69%}.g12{background:linear-gradient(135deg,#2c1a3a,#4a2c5a);padding-top:88%}
.lightbox{display:none;position:fixed;inset:0;background:rgba(0,0,0,.93);z-index:999;align-items:center;justify-content:center}
.lightbox.open{display:flex}.lb-inner{position:relative;max-width:680px;width:90%;text-align:center}
.lb-close{position:absolute;top:-38px;right:0;color:var(--gold);font-size:1.4rem;cursor:pointer;background:none;border:none;font-family:'Jost',sans-serif}
.lb-box{width:100%;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;font-size:8rem;border:1px solid rgba(201,169,110,.2);background:rgba(255,255,255,.03)}
.lb-title{color:#fff;font-family:'Cormorant Garamond',serif;font-size:1.35rem;margin-top:18px}.lb-cat{color:var(--gold);font-size:.72rem;letter-spacing:3px;text-transform:uppercase;margin-top:5px}

/* ── REVIEWS ── */
.rating-banner{background:var(--gold);padding:48px 40px}
.rating-inner{max-width:900px;margin:0 auto;display:grid;grid-template-columns:auto 1fr auto;gap:45px;align-items:center}
.rating-big{text-align:center}.rb-num{font-family:'Cormorant Garamond',serif;font-size:5rem;color:var(--dark);font-weight:600;line-height:1}
.rb-stars{font-size:1.4rem;letter-spacing:3px;color:var(--dark);margin:7px 0}.rb-count{font-size:.75rem;color:rgba(26,18,8,.62);letter-spacing:2px;text-transform:uppercase}
.bar-row{display:flex;gap:13px;align-items:center;margin-bottom:9px}.bar-lbl{font-size:.78rem;color:var(--dark);width:38px;font-weight:600;flex-shrink:0}
.bar-track{flex:1;height:7px;background:rgba(26,18,8,.15);overflow:hidden}.bar-fill{height:100%;background:var(--dark)}
.bar-cnt{font-size:.75rem;color:rgba(26,18,8,.58);width:28px;text-align:right;flex-shrink:0}
.rating-g{text-align:center}.g-big-logo{font-size:2.8rem;font-weight:700;color:#4285f4;margin-bottom:7px}
.g-lbl{font-size:.72rem;color:rgba(26,18,8,.58);letter-spacing:2px;text-transform:uppercase;margin-bottom:13px}
.g-link{display:inline-block;background:var(--dark);color:var(--gold);padding:9px 18px;font-size:.73rem;letter-spacing:2px;text-transform:uppercase;text-decoration:none;font-weight:500;transition:.3s}
.g-link:hover{background:#1a3a2a}
.revs-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:22px;max-width:1200px;margin:0 auto}
.rev-card{background:#fff;padding:30px;border:1px solid rgba(201,169,110,.14);transition:all .35s;position:relative}
.rev-card:hover{box-shadow:0 12px 32px rgba(201,169,110,.14);transform:translateY(-4px);border-color:rgba(201,169,110,.38)}
.rev-q{position:absolute;top:16px;right:20px;font-size:3rem;color:rgba(201,169,110,.1);font-family:'Cormorant Garamond',serif;line-height:1}
.rev-stars{color:#f4b400;font-size:.92rem;letter-spacing:2px;margin-bottom:13px}
.rev-evt{font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:12px;font-weight:500}
.rev-text{font-family:'Cormorant Garamond',serif;font-size:1.02rem;color:var(--text);line-height:1.75;font-style:italic;margin-bottom:20px}
.rev-author{display:flex;align-items:center;gap:12px;padding-top:16px;border-top:1px solid rgba(201,169,110,.11)}
.rev-av{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--dark),var(--dark2));display:flex;align-items:center;justify-content:center;font-size:1.2rem;border:2px solid rgba(201,169,110,.28);flex-shrink:0}
.rev-name{font-weight:600;font-size:.86rem;color:var(--dark)}.rev-date{font-size:.73rem;color:#888;margin-top:1px}
.write-rev{background:var(--dark);padding:85px 40px;text-align:center}
.write-rev h2{font-family:'Cormorant Garamond',serif;color:#fff;font-size:clamp(1.8rem,3vw,2.6rem);font-weight:300;margin-bottom:13px}
.write-rev p{color:rgba(255,255,255,.48);margin-bottom:32px;font-size:.88rem}
.g-review-btn{display:inline-flex;align-items:center;gap:11px;background:#fff;color:#333;padding:14px 28px;text-decoration:none;font-family:'Jost',sans-serif;font-size:.83rem;font-weight:600;letter-spacing:1px;transition:.3s;cursor:pointer;border:none}
.g-review-btn:hover{background:var(--gold-light)}

/* ── BOOKING FORM ── */
.book-layout{max-width:1150px;margin:0 auto;display:grid;grid-template-columns:1fr 340px;gap:45px;align-items:start}
.book-form-wrap{background:rgba(255,255,255,.04);border:1px solid rgba(201,169,110,.2);padding:48px}
.form-sec-title{font-family:'Cormorant Garamond',serif;color:var(--gold);font-size:1.25rem;font-weight:400;margin-bottom:22px;padding-bottom:11px;border-bottom:1px solid rgba(201,169,110,.18);display:flex;align-items:center;gap:11px}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
.fgrp{display:flex;flex-direction:column;gap:7px}.fgrp.full{grid-column:span 2}
.fgrp label{font-size:.7rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);font-weight:500}
.fgrp input,.fgrp select,.fgrp textarea{background:rgba(255,255,255,.05);border:1px solid rgba(201,169,110,.2);color:#fff;padding:12px 14px;font-family:'Jost',sans-serif;font-size:.88rem;outline:none;transition:border .3s;-webkit-appearance:none}
.fgrp input::placeholder,.fgrp textarea::placeholder{color:rgba(255,255,255,.26)}
.fgrp input:focus,.fgrp select:focus,.fgrp textarea:focus{border-color:var(--gold);background:rgba(201,169,110,.05)}
.fgrp select option{background:var(--dark);color:#fff}.fgrp textarea{resize:vertical;min-height:100px}
.fdiv{height:1px;background:rgba(201,169,110,.14);margin:26px 0}
.form-submit{width:100%;padding:15px;background:var(--gold);color:var(--dark);font-family:'Jost',sans-serif;font-size:.82rem;letter-spacing:4px;text-transform:uppercase;font-weight:700;border:none;cursor:pointer;transition:all .3s;margin-top:8px}
.form-submit:hover{background:var(--gold-light);transform:translateY(-2px)}.form-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}
.form-note{text-align:center;color:rgba(255,255,255,.32);font-size:.73rem;margin-top:13px}
.book-sidebar{display:flex;flex-direction:column;gap:22px}
.sb-card{background:rgba(255,255,255,.04);border:1px solid rgba(201,169,110,.16);padding:26px}
.sb-card h4{font-family:'Cormorant Garamond',serif;color:var(--gold);font-size:1.15rem;margin-bottom:16px;font-weight:400;border-bottom:1px solid rgba(201,169,110,.14);padding-bottom:11px}
.sb-item{display:flex;gap:11px;align-items:flex-start;margin-bottom:14px}.sb-ico{font-size:1.2rem;flex-shrink:0;margin-top:2px}
.sb-item p,.sb-item a{font-size:.81rem;color:rgba(255,255,255,.52);line-height:1.7;text-decoration:none}.sb-item a:hover{color:var(--gold)}
.sb-checks{display:flex;flex-direction:column;gap:9px}
.sb-checks div{display:flex;gap:9px;align-items:center;font-size:.81rem;color:rgba(255,255,255,.52)}
.sb-checks div::before{content:'✓';color:var(--gold);font-weight:700;flex-shrink:0}
.pkq-item{padding:9px 13px;border:1px solid rgba(201,169,110,.14);display:flex;justify-content:space-between;align-items:center;cursor:pointer;transition:all .2s;font-size:.81rem;margin-bottom:7px}
.pkq-item:hover{border-color:var(--gold);background:rgba(201,169,110,.07)}.pkq-item .pn{color:rgba(255,255,255,.68)}.pkq-item .pp{color:var(--gold);font-weight:600;font-size:.78rem}
.pkq-item.full-pkg{opacity:.5;cursor:not-allowed}.pkq-item.full-pkg:hover{border-color:rgba(201,169,110,.14);background:transparent}

/* ── BOOKING CONFIRMATION CARD ── */
.bconfirm{display:none;background:rgba(255,255,255,.04);border:1px solid rgba(201,169,110,.25);overflow:hidden}
.bconfirm-header{background:linear-gradient(135deg,rgba(34,197,94,.15),rgba(34,197,94,.05));border-bottom:1px solid rgba(34,197,94,.2);padding:32px;text-align:center}
.bconfirm-icon{font-size:3.5rem;margin-bottom:12px}
.bconfirm-title{font-family:'Cormorant Garamond',serif;color:#4ade80;font-size:1.8rem;font-weight:400;margin-bottom:6px}
.bconfirm-sub{color:rgba(255,255,255,.5);font-size:.82rem;letter-spacing:1px}
.bconfirm-id-box{background:rgba(201,169,110,.1);border:2px solid var(--gold);margin:28px 32px 0;padding:18px 24px;text-align:center;cursor:pointer;transition:background .2s;position:relative}
.bconfirm-id-box:hover{background:rgba(201,169,110,.18)}
.bconfirm-id-label{font-size:.62rem;letter-spacing:4px;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:6px}
.bconfirm-id{font-family:'Cormorant Garamond',serif;font-size:2rem;color:var(--gold);font-weight:600;letter-spacing:3px}
.bconfirm-id-sub{font-size:.68rem;color:rgba(255,255,255,.3);margin-top:5px;letter-spacing:1px}
.bconfirm-copy{position:absolute;top:10px;right:12px;font-size:.65rem;color:var(--gold);letter-spacing:1px;text-transform:uppercase;opacity:.7}
.bconfirm-details{padding:24px 32px;display:grid;grid-template-columns:1fr 1fr;gap:0}
.bconfirm-row{padding:11px 0;border-bottom:1px solid rgba(201,169,110,.08)}
.bconfirm-row:last-child{border-bottom:none}
.bconfirm-key{font-size:.65rem;letter-spacing:2px;text-transform:uppercase;color:rgba(201,169,110,.7);margin-bottom:3px}
.bconfirm-val{font-size:.88rem;color:rgba(255,255,255,.85)}
.bconfirm-status{display:inline-block;background:rgba(251,191,36,.12);color:#fbbf24;font-size:.65rem;letter-spacing:2px;text-transform:uppercase;padding:3px 10px;font-weight:600}
.bconfirm-footer{padding:22px 32px;border-top:1px solid rgba(201,169,110,.1);text-align:center;background:rgba(0,0,0,.15)}
.bconfirm-actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:16px}
.btn-pdf{display:inline-flex;align-items:center;gap:8px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;padding:13px 28px;font-size:.78rem;letter-spacing:3px;text-transform:uppercase;font-weight:700;border:none;cursor:pointer;font-family:'Jost',sans-serif;transition:all .3s}
.btn-pdf:hover{background:linear-gradient(135deg,#b91c1c,#991b1b);transform:translateY(-2px)}

/* ── CONTACT ── */
.contact-layout{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:1fr 1fr;gap:55px;align-items:start}
.ci h3{font-family:'Cormorant Garamond',serif;font-size:1.9rem;color:var(--dark);margin-bottom:10px;font-weight:300}
.ci>p{color:#776655;font-size:.88rem;line-height:2;margin-bottom:32px}
.c-item{display:flex;gap:16px;margin-bottom:25px;align-items:flex-start}
.c-ico{width:46px;height:46px;background:var(--dark);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;border:1px solid rgba(201,169,110,.2)}
.c-det strong{display:block;font-size:.7rem;letter-spacing:3px;text-transform:uppercase;color:var(--gold);margin-bottom:5px;font-weight:500}
.c-det p,.c-det a{font-size:.9rem;color:var(--text);text-decoration:none;line-height:1.7}.c-det a:hover{color:var(--gold)}
.social-row{display:flex;gap:11px;margin-top:28px}
.soc-btn{width:42px;height:42px;background:var(--dark);display:flex;align-items:center;justify-content:center;font-size:1.15rem;text-decoration:none;transition:all .3s;border:1px solid rgba(201,169,110,.14);cursor:pointer}
.soc-btn:hover{background:var(--gold);transform:translateY(-3px)}
.hours-bar{background:var(--gold);padding:48px 40px}
.hours-inner{max-width:980px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);gap:28px;text-align:center}
.hi h4{font-family:'Cormorant Garamond',serif;font-size:1.18rem;color:var(--dark);font-weight:600;margin-bottom:5px}
.hi p{font-size:.8rem;color:rgba(26,18,8,.62);line-height:1.7}
.cf-wrap{max-width:680px;margin:0 auto;background:rgba(255,255,255,.04);border:1px solid rgba(201,169,110,.17);padding:48px}

/* ── FOOTER ── */
footer{background:var(--dark);color:rgba(255,255,255,.42);padding:55px 40px 28px;border-top:1px solid rgba(201,169,110,.18)}
.ft-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:38px;margin-bottom:38px}
.ft-logo{font-family:'Cormorant Garamond',serif;font-size:1.7rem;color:var(--gold);font-weight:400;margin-bottom:11px}
.ft-desc{font-size:.82rem;line-height:1.85;color:rgba(255,255,255,.38);max-width:240px}
.ft-col h4{color:var(--gold);font-size:.68rem;letter-spacing:4px;text-transform:uppercase;margin-bottom:16px;font-weight:500}
.ft-col ul{list-style:none}.ft-col ul li{margin-bottom:9px}
.ft-col ul li a{color:rgba(255,255,255,.42);text-decoration:none;font-size:.83rem;transition:color .3s;cursor:pointer}
.ft-col ul li a:hover{color:var(--gold)}
.ft-social{display:flex;gap:9px;margin-top:18px}
.ft-soc{width:34px;height:34px;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center;font-size:.95rem;text-decoration:none;transition:background .3s;border:1px solid rgba(201,169,110,.13)}
.ft-soc:hover{background:var(--gold)}
.ft-bottom{border-top:1px solid rgba(201,169,110,.1);padding-top:22px;display:flex;justify-content:space-between;align-items:center;font-size:.76rem;flex-wrap:wrap;gap:12px}

/* ── NOTIFICATION ── */
.notif{position:fixed;top:80px;right:22px;z-index:999;padding:14px 22px;font-size:.85rem;font-family:'Jost',sans-serif;border:1px solid;max-width:380px;animation:slideIn .4s ease;display:none;line-height:1.6}
.notif.show{display:block}.notif.success{background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.3);color:#166534}
.notif.error{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#991b1b}

/* ── RESPONSIVE ── */
@media(max-width:1000px){.why-inner,.story-wrap,.book-layout,.contact-layout{grid-template-columns:1fr}.why-boxes,.story-imgs{display:none}.book-sidebar{display:none}.vals-grid{grid-template-columns:1fr 1fr}.hours-inner{grid-template-columns:1fr 1fr}.rating-inner{grid-template-columns:1fr;text-align:center;gap:28px}.ft-grid{grid-template-columns:1fr 1fr}}
@media(max-width:768px){nav{padding:0 18px}.nav-links{display:none}.hamburger{display:flex}section.sec{padding:60px 20px}.book-form-wrap,.cf-wrap{padding:28px 18px}.frow{grid-template-columns:1fr}.fgrp.full{grid-column:span 1}.gal-masonry{columns:2 180px}.bconfirm-details{grid-template-columns:1fr}.bconfirm-id-box{margin:18px 16px 0}.bconfirm-details{padding:16px}.bconfirm-footer{padding:16px}}
@media(max-width:500px){.vals-grid,.hours-inner{grid-template-columns:1fr}.stats-bar{gap:25px}.ft-grid{grid-template-columns:1fr}.ft-bottom{flex-direction:column}.bconfirm-id{font-size:1.5rem}}
</style>
</head>
<body>

<div class="notif" id="notif"></div>

<!-- NAV -->
<nav>
  <a class="logo" onclick="showPage('home')">
    <span class="logo-name"><?= htmlspecialchars($siteName) ?></span>
    <span class="logo-sub">Luxury · Events · Celebrations</span>
  </a>
  <ul class="nav-links">
    <li><a onclick="showPage('home')"     id="nl-home" class="active">Home</a></li>
    <li><a onclick="showPage('about')"    id="nl-about">About</a></li>
    <li><a onclick="showPage('packages')" id="nl-packages">Packages</a></li>
    <li><a onclick="showPage('gallery')"  id="nl-gallery">Gallery</a></li>
    <li><a onclick="showPage('reviews')"  id="nl-reviews">Reviews</a></li>
    <li><a onclick="showPage('contact')"  id="nl-contact">Contact</a></li>
    <li><a onclick="showPage('booking')"  id="nl-booking" class="btn-book">Book Now</a></li>
  </ul>
  <div class="hamburger" onclick="toggleMob()"><span></span><span></span><span></span></div>
</nav>
<div class="mob-menu" id="mob-menu">
  <a onclick="showPage('home');toggleMob()">Home</a>
  <a onclick="showPage('about');toggleMob()">About</a>
  <a onclick="showPage('packages');toggleMob()">Packages</a>
  <a onclick="showPage('gallery');toggleMob()">Gallery</a>
  <a onclick="showPage('reviews');toggleMob()">Reviews</a>
  <a onclick="showPage('contact');toggleMob()">Contact</a>
  <a onclick="showPage('booking');toggleMob()" style="color:var(--gold);font-weight:600">Book Now</a>
</div>

<!-- ═══ HOME ═══ -->
<div id="page-home" class="page active">
  <section class="hero">
    <div class="hero-pat"></div>
    <div class="hero-inner">
      <div class="hero-tag">✦ Welcome to Paradise ✦</div>
      <h1><?= htmlspecialchars($siteName) ?><br><em>Resort & Lawn</em></h1>
      <div class="hero-sub">Where Every Moment Becomes a Timeless Memory</div>
      <div class="hero-div"></div>
      <div class="hero-btns">
        <button class="btn-gold" onclick="showPage('booking')">Book Your Event</button>
        <button class="btn-outline" onclick="showPage('packages')">Explore Packages</button>
      </div>
    </div>
    <div class="scroll-hint">Scroll</div>
  </section>

  <div class="stats-bar">
    <div class="stat"><div class="stat-n">1200+</div><div class="stat-l">Events Hosted</div></div>
    <div class="stat"><div class="stat-n">4.9★</div><div class="stat-l">Google Rating</div></div>
    <div class="stat"><div class="stat-n">15+</div><div class="stat-l">Years Experience</div></div>
    <div class="stat"><div class="stat-n">600+</div><div class="stat-l">Max Capacity</div></div>
  </div>

  <section class="sec" style="background:var(--cream)">
    <div class="section-header">
      <span class="sec-tag">What We Offer</span>
      <h2 class="sec-title">Everything for a Perfect Celebration</h2>
      <div class="sec-line"></div>
    </div>
    <div class="feat-grid">
      <div class="feat-card"><div class="feat-icon">🌿</div><h3>Lush Green Lawns</h3><p>Sprawling open-air grounds ideal for outdoor ceremonies under the sky, surrounded by natural beauty and manicured gardens.</p></div>
      <div class="feat-card"><div class="feat-icon">🏛️</div><h3>Elegant Banquet Halls</h3><p>Fully air-conditioned, beautifully designed halls with modern lighting, LED stages, and custom layout options.</p></div>
      <div class="feat-card"><div class="feat-icon">🍽️</div><h3>Exquisite Catering</h3><p>In-house culinary team crafting multi-cuisine menus from traditional South Indian feasts to continental spreads.</p></div>
      <div class="feat-card"><div class="feat-icon">🎶</div><h3>Sound & Lighting</h3><p>Professional DJ setups, LED walls, ambient lighting rigs, and PA systems tailored for every occasion.</p></div>
      <div class="feat-card"><div class="feat-icon">📸</div><h3>Scenic Backdrops</h3><p>Curated photo-worthy spots throughout the venue — perfect for pre-wedding shoots and event photography.</p></div>
      <div class="feat-card"><div class="feat-icon">🛎️</div><h3>Dedicated Coordinators</h3><p>Our expert team manages every detail so you enjoy your special day stress-free, from setup to final bow.</p></div>
    </div>
  </section>

  <section class="sec" style="background:var(--dark)">
    <div class="why-inner">
      <div class="why-text">
        <span class="sec-tag">Why Choose Us</span>
        <h2>A Venue That Truly <em>Cares</em></h2>
        <p>At Selvi Resort & Lawn, we don't just host events — we craft experiences. Every detail is curated with love, professionalism, and a commitment to making your celebration absolutely unforgettable.</p>
        <ul class="why-list">
          <li>Over 15 years of event excellence in Tamil Nadu</li>
          <li>Fully customizable packages for all budgets</li>
          <li>In-house catering with 100+ menu options</li>
          <li>Ample secured parking for all guests</li>
          <li>Bridal & groom preparation suites on-site</li>
          <li>Transparent pricing — no hidden charges ever</li>
          <li>Emergency backup power throughout the venue</li>
        </ul>
        <br><button class="btn-outline" onclick="showPage('about')" style="margin-top:18px">Learn More About Us</button>
      </div>
      <div class="why-boxes">
        <div class="wb"><div class="wb-n">1200+</div><div class="wb-l">Events</div></div>
        <div class="wb"><div class="wb-n">4.9</div><div class="wb-l">Star Rating</div></div>
        <div class="wb"><div class="wb-n">600</div><div class="wb-l">Max Guests</div></div>
        <div class="wb"><div class="wb-n">15+</div><div class="wb-l">Years</div></div>
      </div>
    </div>
  </section>

  <section class="quote-strip">
    <blockquote>"Selvi Resort made our wedding absolutely magical. The lawn was breathtaking, the food was outstanding, and the team went above and beyond every expectation."</blockquote>
    <cite>— Priya & Ramesh, Wedding Couple</cite><br><br>
    <button class="btn-outline" onclick="showPage('reviews')" style="margin-top:22px;color:var(--dark);border-color:var(--dark)">Read All Reviews</button>
  </section>

  <section class="cta-strip">
    <h2>Your Dream Event Starts Here</h2>
    <p>Limited dates available — secure yours today</p>
    <div class="cta-btns">
      <button class="btn-gold" onclick="showPage('booking')">Book Now</button>
      <button class="btn-outline" onclick="showPage('contact')" style="border-color:rgba(255,255,255,.45);color:#fff">Contact Us</button>
    </div>
  </section>
</div>

<!-- ═══ ABOUT ═══ -->
<div id="page-about" class="page">
  <div class="page-banner"><div class="pb-inner"><div class="pb-tag">Our Story</div><div class="pb-title">About <em>Selvi Resort</em></div><div class="pb-line"></div></div></div>
  <section class="sec">
    <div class="story-wrap">
      <div class="story-imgs">
        <div class="sib sib1">🌿</div><div class="sib sib2">🏛️</div>
        <div class="sib sib3">👑</div><div class="sib sib4">🌸</div>
      </div>
      <div class="story-text">
        <span class="sec-tag">Who We Are</span>
        <h2>Born from a <em>Passion</em> for Celebrations</h2>
        <p>Selvi Resort & Lawn was founded in 2009 with a single vision: to create a place where families come together, love is celebrated, and memories are made that last a lifetime.</p>
        <p>What started as a modest lawn venue has grown into one of Tamil Nadu's most prestigious event destinations — spanning over 3 acres of lush grounds with elegant indoor halls, premium catering, and a team that treats every event as their own.</p>
        <p>Today, over 1,200 weddings, receptions, corporate gatherings, and celebrations have been hosted here. Each one a unique story. Each one crafted with the same devotion that has defined us since day one.</p>
        <button class="btn-gold" onclick="showPage('booking')" style="margin-top:18px">Plan Your Event</button>
      </div>
    </div>
  </section>
  <section class="sec" style="background:var(--dark)">
    <div class="section-header"><span class="sec-tag">What We Stand For</span><h2 class="sec-title light">Our Core Values</h2><div class="sec-line"></div></div>
    <div class="vals-grid">
      <div class="val-card"><div class="val-icon">💛</div><h3>Warmth</h3><p>Every guest is treated like family. From enquiry to the final farewell, our hospitality is heartfelt and genuine.</p></div>
      <div class="val-card"><div class="val-icon">🎯</div><h3>Precision</h3><p>We leave nothing to chance. Every detail — from décor placement to dish timing — is carefully planned and executed.</p></div>
      <div class="val-card"><div class="val-icon">💎</div><h3>Quality</h3><p>Premium ingredients, quality materials, and skilled professionals — we never compromise on excellence for any budget.</p></div>
      <div class="val-card"><div class="val-icon">🌱</div><h3>Integrity</h3><p>Transparent pricing, honest communication, and commitments we always keep. Your trust is our greatest honour.</p></div>
    </div>
  </section>
  <section class="sec">
    <div class="section-header"><span class="sec-tag">The People Behind the Magic</span><h2 class="sec-title">Meet Our Team</h2><div class="sec-line"></div></div>
    <div class="team-grid">
      <div class="team-card"><div class="team-av">👨‍💼</div><h3>Selvaraj K.</h3><div class="team-role">Founder & Director</div><p>With 20+ years in hospitality, Selvaraj's vision built this resort from the ground up with a dedication to excellence.</p></div>
      <div class="team-card"><div class="team-av">👩‍🍳</div><h3>Rani Devi</h3><div class="team-role">Head Chef</div><p>Award-winning culinary artist specializing in South Indian cuisine and multi-cuisine banquet menus for large gatherings.</p></div>
      <div class="team-card"><div class="team-av">👩‍💼</div><h3>Kavya S.</h3><div class="team-role">Event Coordinator</div><p>500+ events planned and executed flawlessly. Kavya ensures no detail is missed on your special day.</p></div>
      <div class="team-card"><div class="team-av">👨‍🎨</div><h3>Arjun M.</h3><div class="team-role">Décor Specialist</div><p>Creative decorator with expertise in floral designs, LED backdrops, themed setups, and luxury installations.</p></div>
    </div>
  </section>
  <section class="sec" style="background:var(--dark2)">
    <div class="section-header"><span class="sec-tag">Our Journey</span><h2 class="sec-title light">15 Years of Excellence</h2><div class="sec-line"></div></div>
    <div class="timeline-wrap">
      <div class="tl-item"><div class="tl-year">2009</div><p>Selvi Resort & Lawn founded with a modest outdoor lawn venue, hosting our first 50-guest wedding event.</p></div>
      <div class="tl-item"><div class="tl-year">2012</div><p>Expanded with our first climate-controlled banquet hall and launched in-house catering services.</p></div>
      <div class="tl-item"><div class="tl-year">2015</div><p>Introduced professional DJ, sound, and lighting packages. Hosted our 100th wedding milestone celebration.</p></div>
      <div class="tl-item"><div class="tl-year">2018</div><p>Major renovation — added bridal suites, premium décor team, and full corporate event facilities.</p></div>
      <div class="tl-item"><div class="tl-year">2021</div><p>Awarded "Best Event Venue in Tamil Nadu" by Regional Hospitality Awards. Crossed 800 events milestone.</p></div>
      <div class="tl-item"><div class="tl-year">2024</div><p>Now hosting 1200+ events, rated 4.9★ on Google, and expanding to add a poolside ceremony deck.</p></div>
    </div>
  </section>
</div>

<!-- ═══ PACKAGES ═══ -->
<div id="page-packages" class="page">
  <div class="page-banner"><div class="pb-inner"><div class="pb-tag">Plans & Pricing</div><div class="pb-title">Our <em>Packages</em></div><div class="pb-line"></div></div></div>
  <section class="sec">
    <div class="section-header"><span class="sec-tag">Choose Your Plan</span><h2 class="sec-title">Event Packages for Every Occasion</h2><div class="sec-line"></div></div>
    <div class="filter-bar">
      <button class="fbtn active" onclick="filterPkgs('all',this)">All</button>
      <button class="fbtn" onclick="filterPkgs('wedding',this)">Weddings</button>
      <button class="fbtn" onclick="filterPkgs('corporate',this)">Corporate</button>
      <button class="fbtn" onclick="filterPkgs('birthday',this)">Birthday</button>
    </div>
    <div class="pkgs-grid" id="pkgs-grid">
      <?php foreach($packages as $p):
        $feats  = json_decode($p['features'], true) ?? [];
        $isFull = !(int)($p['is_available'] ?? 1);
        // Determine category tag for filter
        $slug   = strtolower($p['slug'] ?? $p['name']);
        $cat    = str_contains($slug,'wedding')||str_contains($slug,'platinum') ? 'wedding' :
                 (str_contains($slug,'corporate') ? 'corporate' :
                 (str_contains($slug,'birthday') ? 'birthday' : 'all'));
      ?>
      <div class="pkg <?= $p['is_featured']?'hot':'' ?> <?= $isFull?'fully-booked':'' ?>" data-cat="<?= $cat ?>">
        <?php if($isFull): ?><div class="pkg-badge full">🚫 Fully Booked</div>
        <?php elseif($p['is_featured']): ?><div class="pkg-badge">Most Popular</div><?php endif; ?>
        <div class="pkg-head">
          <div class="pkg-ico"><?= htmlspecialchars($p['icon']) ?></div>
          <div class="pkg-sub"><?= htmlspecialchars($p['subtitle']) ?></div>
          <h3><?= htmlspecialchars($p['name']) ?></h3>
        </div>
        <div class="pkg-body">
          <div class="pkg-price"><?= $p['price']>0?'₹'.number_format($p['price']):'Custom' ?><span> / <?= htmlspecialchars($p['price_label']) ?></span></div>
          <div class="pkg-cap">👥 Up to <?= $p['max_guests']<9999?$p['max_guests']:'Unlimited' ?> guests · <?= htmlspecialchars($p['duration']) ?></div>
          <ul class="pkg-feats"><?php foreach($feats as $f): ?><li><?= htmlspecialchars($f) ?></li><?php endforeach; ?></ul>
        </div>
        <div class="pkg-foot">
          <?php if($isFull): ?>
            <span class="pkg-btn disabled">🚫 Fully Booked — Contact Us</span>
          <?php else: ?>
            <button class="pkg-btn" onclick="selectPackage('<?= htmlspecialchars($p['name'],ENT_QUOTES) ?>')">
              <?= $p['price']>0?'Book This Package':'Get Custom Quote' ?>
            </button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sec" style="background:var(--dark)">
    <div class="section-header"><span class="sec-tag">Enhance Your Event</span><h2 class="sec-title light">Optional Add-Ons</h2><div class="sec-line"></div></div>
    <div class="addons-grid">
      <div class="addon"><div class="addon-ic">📸</div><h4>Photography</h4><p>Professional DSLR coverage for 4–8 hours with same-day preview.</p><div class="addon-pr">From ₹15,000</div></div>
      <div class="addon"><div class="addon-ic">🎥</div><h4>Videography</h4><p>Full HD cinematic shoot with highlights reel and raw footage.</p><div class="addon-pr">From ₹20,000</div></div>
      <div class="addon"><div class="addon-ic">🚁</div><h4>Drone Coverage</h4><p>Aerial footage of your venue and event with edited clip.</p><div class="addon-pr">₹8,000</div></div>
      <div class="addon"><div class="addon-ic">🌺</div><h4>Floral Upgrade</h4><p>Premium imported flowers with custom arrangements.</p><div class="addon-pr">From ₹12,000</div></div>
      <div class="addon"><div class="addon-ic">🎤</div><h4>Live Music</h4><p>Live classical, film, or jazz performance for 2 hours.</p><div class="addon-pr">From ₹18,000</div></div>
      <div class="addon"><div class="addon-ic">🎠</div><h4>Kids Zone</h4><p>Supervised play area with entertainment for children.</p><div class="addon-pr">₹6,000</div></div>
    </div>
  </section>

  <section class="sec">
    <div class="section-header"><span class="sec-tag">Side by Side</span><h2 class="sec-title">Package Comparison</h2><div class="sec-line"></div></div>
    <div style="overflow-x:auto">
    <table class="cmp-table">
      <thead><tr><th>Feature</th><th>Silver</th><th>Gold</th><th>Platinum</th><th>Wedding</th></tr></thead>
      <tbody>
        <tr><td>Guest Capacity</td><td>100</td><td>300</td><td>600</td><td>Unlimited</td></tr>
        <tr><td>Duration</td><td>6 hrs</td><td>10 hrs</td><td>Full Day</td><td>2–3 Days</td></tr>
        <tr><td>Catering</td><td class="yes">Veg Only</td><td class="yes">Veg & Non-Veg</td><td class="yes">Multi-Cuisine</td><td class="yes">All Ceremonies</td></tr>
        <tr><td>DJ & Sound</td><td class="no">Basic System</td><td class="yes">Full DJ Rig</td><td class="yes">Premium Setup</td><td class="yes">Custom</td></tr>
        <tr><td>Décor</td><td class="yes">Basic</td><td class="yes">Premium LED</td><td class="yes">Luxury Theme</td><td class="yes">Custom Design</td></tr>
        <tr><td>Bridal Suite</td><td class="no">No</td><td class="yes">2 Hours</td><td class="yes">Full Day</td><td class="yes">All Days</td></tr>
        <tr><td>Photography</td><td class="no">Add-On</td><td class="no">Add-On</td><td class="yes">Included</td><td class="yes">Full Coverage</td></tr>
        <tr><td>Coordinator</td><td class="yes">1 Person</td><td class="yes">Team of 5</td><td class="yes">24hr Concierge</td><td class="yes">Dedicated Team</td></tr>
      </tbody>
    </table>
    </div>
    <div style="text-align:center;margin-top:48px"><button class="btn-gold" onclick="showPage('booking')">Book Your Package Now</button></div>
  </section>
</div>

<!-- ═══ GALLERY ═══ -->
<div id="page-gallery" class="page">
  <div class="page-banner"><div class="pb-inner"><div class="pb-tag">Visual Journey</div><div class="pb-title">Our <em>Gallery</em></div><div class="pb-line"></div></div></div>
  <section class="sec">
    <div class="section-header"><span class="sec-tag">Moments Captured</span><h2 class="sec-title">A Glimpse of Magic</h2><div class="sec-line"></div></div>
    <div class="filter-bar">
      <button class="fbtn active">All</button><button class="fbtn">Weddings</button><button class="fbtn">Décor</button><button class="fbtn">Catering</button><button class="fbtn">Corporate</button>
    </div>
    <div class="gal-masonry">
      <div class="gal-item" onclick="openLb('🌿','The Grand Lawn','Venue')"><div class="gal-box g1"><div class="gal-box-inner">🌿</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Venue</span><div class="gal-ov-title">The Grand Lawn</div></div></div></div>
      <div class="gal-item" onclick="openLb('🕌','Sacred Ceremony','Wedding')"><div class="gal-box g2"><div class="gal-box-inner">🕌</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Wedding</span><div class="gal-ov-title">Sacred Ceremony</div></div></div></div>
      <div class="gal-item" onclick="openLb('🌸','Floral Stage','Décor')"><div class="gal-box g3"><div class="gal-box-inner">🌸</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Décor</span><div class="gal-ov-title">Floral Stage</div></div></div></div>
      <div class="gal-item" onclick="openLb('🍽️','Grand Buffet','Catering')"><div class="gal-box g4"><div class="gal-box-inner">🍽️</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Catering</span><div class="gal-ov-title">Grand Buffet</div></div></div></div>
      <div class="gal-item" onclick="openLb('✨','Night Gala','Reception')"><div class="gal-box g5"><div class="gal-box-inner">✨</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Reception</span><div class="gal-ov-title">Night Gala</div></div></div></div>
      <div class="gal-item" onclick="openLb('💍','Couple Portrait','Wedding')"><div class="gal-box g6"><div class="gal-box-inner">💍</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Wedding</span><div class="gal-ov-title">Couple Portrait</div></div></div></div>
      <div class="gal-item" onclick="openLb('🎶','Sangeet Night','Events')"><div class="gal-box g7"><div class="gal-box-inner">🎶</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Events</span><div class="gal-ov-title">Sangeet Night</div></div></div></div>
      <div class="gal-item" onclick="openLb('🏛️','Banquet Hall','Venue')"><div class="gal-box g8"><div class="gal-box-inner">🏛️</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Venue</span><div class="gal-ov-title">Banquet Hall</div></div></div></div>
      <div class="gal-item" onclick="openLb('🎂','Birthday Setup','Birthday')"><div class="gal-box g9"><div class="gal-box-inner">🎂</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Birthday</span><div class="gal-ov-title">Birthday Setup</div></div></div></div>
      <div class="gal-item" onclick="openLb('💐','Bridal Entry','Wedding')"><div class="gal-box g10"><div class="gal-box-inner">💐</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Wedding</span><div class="gal-ov-title">Bridal Entry</div></div></div></div>
      <div class="gal-item" onclick="openLb('💼','Corporate Conference','Corporate')"><div class="gal-box g11"><div class="gal-box-inner">💼</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Corporate</span><div class="gal-ov-title">Corporate Conference</div></div></div></div>
      <div class="gal-item" onclick="openLb('🌙','Evening Lights','Décor')"><div class="gal-box g12"><div class="gal-box-inner">🌙</div></div><div class="gal-ov"><div><span class="gal-ov-cat">Décor</span><div class="gal-ov-title">Evening Lights</div></div></div></div>
    </div>
  </section>
  <section style="background:var(--dark);padding:65px 40px;text-align:center">
    <span class="sec-tag" style="display:block;margin-bottom:10px">Book Your Date</span>
    <h2 style="font-family:'Cormorant Garamond',serif;color:#fff;font-size:2.4rem;font-weight:300;margin-bottom:22px">Make Your Event Look This Beautiful</h2>
    <button class="btn-gold" onclick="showPage('booking')">Book Now</button>
  </section>
</div>
<div class="lightbox" id="lightbox" onclick="closeLb(event)">
  <div class="lb-inner">
    <button class="lb-close" onclick="document.getElementById('lightbox').classList.remove('open')">✕ Close</button>
    <div class="lb-box" id="lb-box"></div>
    <div class="lb-cat" id="lb-cat"></div>
    <div class="lb-title" id="lb-title"></div>
  </div>
</div>

<!-- ═══ REVIEWS ═══ -->
<div id="page-reviews" class="page">
  <div class="page-banner"><div class="pb-inner"><div class="pb-tag">What Our Guests Say</div><div class="pb-title">Reviews & <em>Testimonials</em></div><div class="pb-line"></div></div></div>
  <div class="rating-banner">
    <div class="rating-inner">
      <div class="rating-big"><div class="rb-num">4.9</div><div class="rb-stars">★★★★★</div><div class="rb-count">240+ Reviews</div></div>
      <div>
        <div class="bar-row"><span class="bar-lbl">5 ★</span><div class="bar-track"><div class="bar-fill" style="width:88%"></div></div><span class="bar-cnt">212</span></div>
        <div class="bar-row"><span class="bar-lbl">4 ★</span><div class="bar-track"><div class="bar-fill" style="width:9%"></div></div><span class="bar-cnt">22</span></div>
        <div class="bar-row"><span class="bar-lbl">3 ★</span><div class="bar-track"><div class="bar-fill" style="width:2%"></div></div><span class="bar-cnt">5</span></div>
        <div class="bar-row"><span class="bar-lbl">2 ★</span><div class="bar-track"><div class="bar-fill" style="width:.5%"></div></div><span class="bar-cnt">1</span></div>
        <div class="bar-row"><span class="bar-lbl">1 ★</span><div class="bar-track"><div class="bar-fill" style="width:0%"></div></div><span class="bar-cnt">0</span></div>
      </div>
      <div class="rating-g"><div class="g-big-logo">Google</div><div class="g-lbl">Verified Reviews</div><a class="g-link" href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank">See All Reviews →</a></div>
    </div>
  </div>
  <section class="sec">
    <div class="section-header"><span class="sec-tag">Real Stories</span><h2 class="sec-title">Voices of Our Guests</h2><div class="sec-line"></div></div>
    <div class="revs-grid">
      <div class="rev-card"><div class="rev-q">"</div><div class="rev-stars">★★★★★</div><div class="rev-evt">💍 Wedding Ceremony</div><p class="rev-text">Selvi Resort made our wedding absolutely magical. The lawn was breathtaking at sunset, the food was outstanding, and the team went above and beyond in every way possible.</p><div class="rev-author"><div class="rev-av">👩</div><div><div class="rev-name">Priya Ramesh</div><div class="rev-date">Wedding — January 2024</div></div></div></div>
      <div class="rev-card"><div class="rev-q">"</div><div class="rev-stars">★★★★★</div><div class="rev-evt">💼 Corporate Annual Day</div><p class="rev-text">Hosted our company's annual day here. The corporate package was excellent — great AV setup, impeccable service, and catering was a huge hit with 180 employees. Will definitely rebook.</p><div class="rev-author"><div class="rev-av">👨</div><div><div class="rev-name">Suresh Kumar</div><div class="rev-date">Corporate Event — March 2024</div></div></div></div>
      <div class="rev-card"><div class="rev-q">"</div><div class="rev-stars">★★★★★</div><div class="rev-evt">🎂 Birthday Party</div><p class="rev-text">Booked the birthday package for my daughter's sweet 16. The staff decorated beautifully, the kids had a blast, and we got memories that will last a lifetime. Worth every rupee!</p><div class="rev-author"><div class="rev-av">👩</div><div><div class="rev-name">Kavitha Menon</div><div class="rev-date">Birthday Party — May 2024</div></div></div></div>
      <div class="rev-card"><div class="rev-q">"</div><div class="rev-stars">★★★★☆</div><div class="rev-evt">🎉 Reception</div><p class="rev-text">Amazing venue with a stunning lawn. The Gold Package covered everything. Food variety was incredible and the DJ kept everyone dancing all night long. Highly recommended!</p><div class="rev-author"><div class="rev-av">👨</div><div><div class="rev-name">Arjun Nair</div><div class="rev-date">Reception — August 2024</div></div></div></div>
      <div class="rev-card"><div class="rev-q">"</div><div class="rev-stars">★★★★★</div><div class="rev-evt">🎶 Sangeet Ceremony</div><p class="rev-text">The venue is gorgeous, but what truly sets Selvi Resort apart is their team. They handled every detail of our sangeet with such care. The lighting setup was especially breathtaking!</p><div class="rev-author"><div class="rev-av">👩</div><div><div class="rev-name">Deepa Krishnan</div><div class="rev-date">Sangeet — October 2024</div></div></div></div>
      <div class="rev-card"><div class="rev-q">"</div><div class="rev-stars">★★★★★</div><div class="rev-evt">💎 Platinum Package</div><p class="rev-text">From enquiry to event day, everything was seamless. The coordinator was incredibly responsive and accommodating. Platinum package delivered beyond all expectations. 10/10!</p><div class="rev-author"><div class="rev-av">👨</div><div><div class="rev-name">Rajan Pillai</div><div class="rev-date">Family Celebration — December 2024</div></div></div></div>
      <div class="rev-card"><div class="rev-q">"</div><div class="rev-stars">★★★★★</div><div class="rev-evt">👶 Baby Shower</div><p class="rev-text">Organized a surprise baby shower for my sister. The team helped with every surprise element perfectly. The floral arrangements were stunning and the food was absolutely delicious!</p><div class="rev-author"><div class="rev-av">👩</div><div><div class="rev-name">Meena Subramanian</div><div class="rev-date">Baby Shower — November 2024</div></div></div></div>
      <div class="rev-card"><div class="rev-q">"</div><div class="rev-stars">★★★★★</div><div class="rev-evt">💍 3-Day Wedding</div><p class="rev-text">We booked Selvi Resort for our entire 3-day wedding — mehendi, sangeet, and reception. Each ceremony was flawlessly executed. The bridal suite was luxurious and the mandap décor jaw-dropping.</p><div class="rev-author"><div class="rev-av">👨</div><div><div class="rev-name">Vikram & Ananya</div><div class="rev-date">Wedding Package — February 2024</div></div></div></div>
      <div class="rev-card"><div class="rev-q">"</div><div class="rev-stars">★★★★★</div><div class="rev-evt">🎓 Graduation Celebration</div><p class="rev-text">Hosted a family gathering for my son's graduation. The Silver Package was perfectly sized for our 80 guests. Warm staff, delicious food, and a beautiful setting. Highly recommend!</p><div class="rev-author"><div class="rev-av">👩</div><div><div class="rev-name">Saranya Balu</div><div class="rev-date">Family Gathering — June 2024</div></div></div></div>
    </div>
  </section>
  <section class="write-rev">
    <span class="sec-tag" style="display:block;margin-bottom:10px">Share Your Experience</span>
    <h2>Had a Great Event at Selvi Resort?</h2>
    <p>We'd love to hear from you! Your review helps other families make the right choice.</p>
    <a class="g-review-btn" href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank">G &nbsp; Write a Review on Google</a>
  </section>
</div>

<!-- ═══ BOOKING ═══ -->
<div id="page-booking" class="page">
  <div class="page-banner"><div class="pb-inner"><div class="pb-tag">Reserve Your Date</div><div class="pb-title">Book Your <em>Event</em></div><div class="pb-line"></div></div></div>
  <section class="sec" style="background:var(--dark)">
    <div class="book-layout">

      <!-- FORM -->
      <div class="book-form-wrap">
        <div id="bform-inner">
          <div class="form-sec-title"><span>👤</span> Personal Details</div>
          <div class="frow">
            <div class="fgrp"><label>Full Name *</label><input type="text" id="b-name" placeholder="Your full name"></div>
            <div class="fgrp"><label>Phone Number *</label><input type="tel" id="b-phone" placeholder="+91 XXXXX XXXXX"></div>
          </div>
          <div class="frow">
            <div class="fgrp"><label>Email Address</label><input type="email" id="b-email" placeholder="your@email.com"></div>
            <div class="fgrp"><label>WhatsApp Number</label><input type="tel" id="b-whatsapp" placeholder="+91 XXXXX XXXXX"></div>
          </div>
          <div class="fdiv"></div>
          <div class="form-sec-title"><span>🎉</span> Event Details</div>
          <div class="frow">
            <div class="fgrp"><label>Event Type *</label>
              <select id="b-event"><option value="">Select event type</option><option>Wedding Ceremony</option><option>Reception</option><option>Sangeet / Mehendi</option><option>Engagement</option><option>Birthday Party</option><option>Anniversary</option><option>Corporate Event</option><option>Baby Shower</option><option>Other</option></select>
            </div>
            <div class="fgrp"><label>Package</label>
              <select id="b-pkg"><option value="">Select package (optional)</option>
                <?php foreach($packages as $p):
                  $isFull = !(int)($p['is_available'] ?? 1);
                ?>
                  <option value="<?= htmlspecialchars($p['name']) ?>" <?= $isFull?'disabled style="color:#dc2626"':'' ?>>
                    <?= htmlspecialchars($p['name']) ?><?= $p['price']>0?' (₹'.number_format($p['price']).')':' (Custom)' ?><?= $isFull?' — FULLY BOOKED':'' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="frow">
            <div class="fgrp" style="grid-column:span 2">
              <label>Event Date Range</label>
              <input type="text" id="b-date-range" placeholder="📅 Click to select check-in → check-out dates" readonly style="cursor:pointer;width:100%">
              <input type="hidden" id="b-date">
              <input type="hidden" id="b-altdate">
              <input type="hidden" id="b-checkout-date">
              <div id="b-date-summary" style="display:none;margin-top:8px;gap:10px;flex-wrap:wrap">
                <div style="flex:1;min-width:130px;border:1px solid rgba(201,169,110,.28);padding:8px 13px;background:rgba(201,169,110,.06)">
                  <div style="font-size:.6rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:2px">Check-In</div>
                  <div id="b-checkin-display" style="font-size:.85rem;color:#fff;font-weight:500">—</div>
                </div>
                <div style="flex:1;min-width:130px;border:1px solid rgba(201,169,110,.28);padding:8px 13px;background:rgba(201,169,110,.06)">
                  <div style="font-size:.6rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:2px">Check-Out</div>
                  <div id="b-checkout-display" style="font-size:.85rem;color:#fff;font-weight:500">—</div>
                </div>
                <div style="flex:1;min-width:100px;border:1px solid rgba(201,169,110,.28);padding:8px 13px;background:rgba(201,169,110,.06)">
                  <div style="font-size:.6rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:2px">Duration</div>
                  <div id="b-duration-display" style="font-size:.85rem;color:#fff;font-weight:500">—</div>
                </div>
              </div>
            </div>
          </div>
          <div class="frow">
            <div class="fgrp"><label>Expected Guests</label>
              <select id="b-guests"><option>Below 50</option><option>50–100</option><option>100–200</option><option>200–300</option><option>300–500</option><option>500–600</option><option>600+</option></select>
            </div>
            <div class="fgrp">
              <label>Check-In / Check-Out Time</label>
              <div style="display:flex;gap:10px">
                <div style="flex:1;border:1px solid rgba(201,169,110,.28);padding:10px 13px;background:rgba(201,169,110,.06)">
                  <div style="font-size:.6rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:3px">Check-In</div>
                  <div style="font-size:.9rem;color:#fff;font-weight:500">🕐 1:00 PM</div>
                </div>
                <div style="flex:1;border:1px solid rgba(201,169,110,.28);padding:10px 13px;background:rgba(201,169,110,.06)">
                  <div style="font-size:.6rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:3px">Check-Out</div>
                  <div style="font-size:.9rem;color:#fff;font-weight:500">🕙 10:00 AM <span style="font-size:.7rem;color:rgba(255,255,255,.4)">(last day)</span></div>
                </div>
              </div>
              <input type="hidden" id="b-slot" value="Full Day (1 PM - 10 AM)">
            </div>
          </div>
          <div class="fdiv"></div>
          <div class="form-sec-title"><span>✨</span> Add-Ons & Requests</div>
          <div class="frow">
            <div class="fgrp"><label>Add-On Service</label>
              <select id="b-addon"><option value="">Optional add-on</option><option>Photography (₹15,000+)</option><option>Videography (₹20,000+)</option><option>Drone Coverage (₹8,000)</option><option>Floral Upgrade (₹12,000+)</option><option>Live Music (₹18,000+)</option><option>Kids Zone (₹6,000)</option></select>
            </div>
            <div class="fgrp"><label>How did you hear about us?</label>
              <select id="b-heard"><option>Google Search</option><option>Google Maps</option><option>Word of Mouth</option><option>Instagram / Facebook</option><option>Previous Guest</option><option>Other</option></select>
            </div>
          </div>
          <div class="frow">
            <div class="fgrp full"><label>Special Requirements / Message</label><textarea id="b-special" placeholder="Describe your event, special needs, cultural preferences, dietary requirements..."></textarea></div>
          </div>
          <button class="form-submit" id="b-submit-btn" onclick="submitBooking()">Send Booking Enquiry</button>
          <p class="form-note">Our team will contact you within 24 hours to confirm availability.</p>
        </div>

        <!-- CONFIRMATION CARD -->
        <div class="bconfirm" id="bsuccess">
          <div class="bconfirm-header">
            <div class="bconfirm-icon">🎉</div>
            <div class="bconfirm-title">Booking Enquiry Received!</div>
            <div class="bconfirm-sub">Save your Booking ID — share it when you contact us</div>
          </div>
          <div class="bconfirm-id-box" onclick="copyBookingId()" title="Click to copy">
            <div class="bconfirm-id-label">Your Booking Reference</div>
            <div class="bconfirm-id" id="bc-ref">—</div>
            <div class="bconfirm-id-sub">Database ID: <span id="bc-num-id">—</span></div>
            <div class="bconfirm-copy">📋 Tap to copy</div>
          </div>
          <div class="bconfirm-details">
            <div class="bconfirm-row"><div class="bconfirm-key">Name</div><div class="bconfirm-val" id="bc-name">—</div></div>
            <div class="bconfirm-row"><div class="bconfirm-key">Phone</div><div class="bconfirm-val" id="bc-phone">—</div></div>
            <div class="bconfirm-row"><div class="bconfirm-key">Event Type</div><div class="bconfirm-val" id="bc-event">—</div></div>
            <div class="bconfirm-row"><div class="bconfirm-key">Package</div><div class="bconfirm-val" id="bc-pkg-confirm">—</div></div>
            <div class="bconfirm-row"><div class="bconfirm-key">Event Date</div><div class="bconfirm-val" id="bc-date">—</div></div>
            <div class="bconfirm-row"><div class="bconfirm-key">Expected Guests</div><div class="bconfirm-val" id="bc-guests">—</div></div>
            <div class="bconfirm-row"><div class="bconfirm-key">Status</div><div class="bconfirm-val"><span class="bconfirm-status" id="bc-status">New</span></div></div>
            <div class="bconfirm-row"><div class="bconfirm-key">Submitted At</div><div class="bconfirm-val" id="bc-time">—</div></div>
          </div>
          <div class="bconfirm-footer">
            <p style="color:rgba(255,255,255,.5);font-size:.82rem;line-height:1.8">Our team will call you within <strong style="color:var(--gold)">24 hours</strong> to confirm your booking.</p>
            <p style="color:var(--gold);margin-top:10px;font-size:.9rem">📞 <strong><?= htmlspecialchars($phone1) ?></strong></p>
            <div class="bconfirm-actions">
              <button class="btn-pdf" onclick="downloadPDFReceipt()">📄 Download PDF Receipt</button>
              <button class="btn-outline" onclick="copyBookingId()">📋 Copy Booking ID</button>
              <button class="btn-outline" onclick="resetBookingForm()">New Booking</button>
              <button class="btn-gold" onclick="showPage('home')">Back to Home</button>
            </div>
          </div>
        </div>
      </div>

      <!-- SIDEBAR -->
      <div class="book-sidebar">
        <div class="sb-card">
          <h4>📦 Quick Package Select</h4>
          <?php foreach($packages as $p):
            $isFull = !(int)($p['is_available'] ?? 1);
          ?>
          <div class="pkq-item <?= $isFull?'full-pkg':'' ?>" <?= !$isFull?"onclick=\"selectPackage('".htmlspecialchars($p['name'],ENT_QUOTES)."')\"":'' ?>>
            <span class="pn"><?= htmlspecialchars($p['icon']) ?> <?= htmlspecialchars($p['name']) ?><?= $isFull?' 🚫':'' ?></span>
            <span class="pp"><?= $p['price']>0?'₹'.number_format($p['price']):'Custom' ?></span>
          </div>
          <?php endforeach; ?>
          <br><a onclick="showPage('packages')" style="color:var(--gold);font-size:.76rem;cursor:pointer">Compare all packages →</a>
        </div>
        <div class="sb-card">
          <h4>✅ What to Expect</h4>
          <div class="sb-checks">
            <div>Confirmation call within 24 hours</div>
            <div>Site visit & venue walk-through</div>
            <div>Custom quote & contract</div>
            <div>Advance booking secures your date</div>
            <div>Dedicated coordinator assigned</div>
            <div>No hidden charges — ever</div>
          </div>
        </div>
        <div class="sb-card">
          <h4>📞 Contact Directly</h4>
          <div class="sb-item"><div class="sb-ico">📱</div><div><a href="tel:<?= htmlspecialchars($phone1) ?>"><?= htmlspecialchars($phone1) ?></a><br><a href="tel:<?= htmlspecialchars($phone2) ?>"><?= htmlspecialchars($phone2) ?></a></div></div>
          <div class="sb-item"><div class="sb-ico">✉️</div><div><a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a></div></div>
          <div class="sb-item"><div class="sb-ico">💬</div><div><a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" target="_blank">Chat on WhatsApp →</a></div></div>
          <div class="sb-item"><div class="sb-ico">🕐</div><p>Mon–Sat: 9AM–8PM · Sun: 10AM–6PM</p></div>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- ═══ CONTACT ═══ -->
<div id="page-contact" class="page">
  <div class="page-banner"><div class="pb-inner"><div class="pb-tag">We're Here For You</div><div class="pb-title">Contact <em>Us</em></div><div class="pb-line"></div></div></div>
  <section class="sec">
    <div class="contact-layout">
      <div class="ci">
        <h3>Let's Plan Your Perfect Event</h3>
        <p>Whether you're planning a grand wedding, an intimate celebration, or a corporate gathering — our team is ready to help. Reach out and let's make your vision a reality.</p>
        <div class="c-item"><div class="c-ico">📍</div><div class="c-det"><strong>Our Address</strong><p><?= nl2br(htmlspecialchars($address)) ?></p></div></div>
        <div class="c-item"><div class="c-ico">📞</div><div class="c-det"><strong>Phone Numbers</strong><a href="tel:<?= htmlspecialchars($phone1) ?>"><?= htmlspecialchars($phone1) ?></a><br><a href="tel:<?= htmlspecialchars($phone2) ?>"><?= htmlspecialchars($phone2) ?></a></div></div>
        <div class="c-item"><div class="c-ico">✉️</div><div class="c-det"><strong>Email</strong><a href="mailto:<?= htmlspecialchars($email) ?>"><?= htmlspecialchars($email) ?></a></div></div>
        <div class="c-item"><div class="c-ico">💬</div><div class="c-det"><strong>WhatsApp</strong><a href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" target="_blank">Chat with us on WhatsApp →</a></div></div>
        <div class="c-item"><div class="c-ico">🕐</div><div class="c-det"><strong>Office Hours</strong><p>Mon – Sat: 9:00 AM – 8:00 PM<br>Sunday: 10:00 AM – 6:00 PM</p></div></div>
        <div class="social-row">
          <a class="soc-btn" href="#">📘</a><a class="soc-btn" href="#">📸</a>
          <a class="soc-btn" href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" target="_blank">💬</a>
          <a class="soc-btn" href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank">🗺️</a>
          <a class="soc-btn" href="#">▶️</a>
        </div>
      </div>
      <div>
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3890.0!2d80.2707!3d13.0827!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTPCsDA0JzU3LjciTiA4MMKwMTYnMTQuNiJF!5e0!3m2!1sen!2sin!4v1234567890" style="width:100%;height:345px;border:none;border:2px solid rgba(201,169,110,.2);filter:sepia(15%) contrast(1.06)" allowfullscreen loading="lazy"></iframe>
        <a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank" style="display:block;text-align:center;margin-top:12px;color:var(--gold);text-decoration:none;font-size:.76rem;letter-spacing:2px;text-transform:uppercase">📍 Get Directions on Google Maps →</a>
      </div>
    </div>
  </section>
  <div class="hours-bar"><div class="hours-inner">
    <div class="hi"><h4>Office Hours</h4><p>Mon – Sat<br>9:00 AM – 8:00 PM</p></div>
    <div class="hi"><h4>Sundays</h4><p>10:00 AM – 6:00 PM</p></div>
    <div class="hi"><h4>Response Time</h4><p>Enquiries answered<br>within 24 hours</p></div>
    <div class="hi"><h4>Site Visits</h4><p>By appointment<br>Any day of the week</p></div>
  </div></div>
  <section class="sec" style="background:var(--dark)">
    <div class="section-header"><span class="sec-tag">Drop Us a Message</span><h2 class="sec-title light">Send an Enquiry</h2><div class="sec-line"></div></div>
    <div class="cf-wrap">
      <div class="frow"><div class="fgrp"><label>Your Name</label><input type="text" id="c-name" placeholder="Full name"></div><div class="fgrp"><label>Phone Number</label><input type="tel" id="c-phone" placeholder="+91 XXXXX XXXXX"></div></div>
      <div class="frow"><div class="fgrp"><label>Email Address</label><input type="email" id="c-email" placeholder="your@email.com"></div>
        <div class="fgrp"><label>Subject</label><select id="c-subject"><option>Event Booking Enquiry</option><option>Package Information</option><option>Pricing & Availability</option><option>Site Visit Request</option><option>Feedback</option><option>Other</option></select></div>
      </div>
      <div class="frow"><div class="fgrp full"><label>Your Message</label><textarea id="c-message" placeholder="Tell us how we can help you..."></textarea></div></div>
      <button class="form-submit" onclick="submitContact()">Send Message</button>
    </div>
  </section>
</div>

<!-- FOOTER -->
<footer>
  <div class="ft-grid">
    <div>
      <div class="ft-logo"><?= htmlspecialchars($siteName) ?></div>
      <p class="ft-desc">A premier event venue offering lush green lawns, elegant halls, and world-class hospitality for every celebration.</p>
      <div class="ft-social">
        <a class="ft-soc" href="#">📘</a><a class="ft-soc" href="#">📸</a>
        <a class="ft-soc" href="https://wa.me/<?= htmlspecialchars($whatsapp) ?>" target="_blank">💬</a>
        <a class="ft-soc" href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank">🗺️</a>
      </div>
    </div>
    <div class="ft-col"><h4>Quick Links</h4><ul>
      <li><a onclick="showPage('home')">Home</a></li><li><a onclick="showPage('about')">About Us</a></li>
      <li><a onclick="showPage('packages')">Packages</a></li><li><a onclick="showPage('gallery')">Gallery</a></li>
      <li><a onclick="showPage('reviews')">Reviews</a></li><li><a onclick="showPage('contact')">Contact</a></li>
    </ul></div>
    <div class="ft-col"><h4>Packages</h4><ul>
      <?php foreach($packages as $p): ?>
        <li><a onclick="showPage('packages')"><?= htmlspecialchars($p['name']) ?></a></li>
      <?php endforeach; ?>
    </ul></div>
    <div class="ft-col"><h4>Contact</h4><ul>
      <li><a href="tel:<?= htmlspecialchars($phone1) ?>">📞 <?= htmlspecialchars($phone1) ?></a></li>
      <li><a href="mailto:<?= htmlspecialchars($email) ?>">✉️ <?= htmlspecialchars($email) ?></a></li>
      <li><a href="<?= htmlspecialchars($mapsUrl) ?>" target="_blank">📍 View Location</a></li>
      <li><a onclick="showPage('booking')">📅 Book Now</a></li>
    </ul></div>
  </div>
  <div class="ft-bottom">
    <span>© <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. All Rights Reserved.</span>
    <span>Designed with ❤️ for unforgettable events</span>
  </div>
</footer>

<script>
// ── Booking data store (for PDF receipt) ──────────────────────
let _bookingData = {};

// ── Navigation ───────────────────────────────────────────────
function showPage(name) {
  document.querySelectorAll('.page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
  const pg = document.getElementById('page-' + name);
  if (pg) pg.classList.add('active');
  const nl = document.getElementById('nl-' + name);
  if (nl) nl.classList.add('active');
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function toggleMob() { document.getElementById('mob-menu').classList.toggle('open'); }

// ── Package filter ────────────────────────────────────────────
function filterPkgs(cat, btn) {
  document.querySelectorAll('.filter-bar .fbtn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#pkgs-grid .pkg').forEach(p => {
    p.style.display = (cat === 'all' || p.dataset.cat === cat) ? '' : 'none';
  });
}

function selectPackage(name) {
  showPage('booking');
  setTimeout(() => {
    const s = document.getElementById('b-pkg');
    for (let o of s.options) if (o.value === name && !o.disabled) { s.value = name; break; }
  }, 100);
}

// ── Gallery lightbox ─────────────────────────────────────────
function openLb(emoji, title, cat) {
  document.getElementById('lb-box').textContent = emoji;
  document.getElementById('lb-title').textContent = title;
  document.getElementById('lb-cat').textContent = cat;
  document.getElementById('lightbox').classList.add('open');
}
function closeLb(e) {
  if (e.target === document.getElementById('lightbox'))
    document.getElementById('lightbox').classList.remove('open');
}
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') document.getElementById('lightbox').classList.remove('open');
});

// ── Notification toast ───────────────────────────────────────
function showNotif(msg, type) {
  const n = document.getElementById('notif');
  n.innerHTML = msg; n.className = 'notif ' + type + ' show';
  setTimeout(() => n.classList.remove('show'), 6000);
}

// ── Copy booking ID ───────────────────────────────────────────
function copyBookingId() {
  const ref = document.getElementById('bc-ref').textContent;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(ref).then(() =>
      showNotif('✅ Booking ID <strong>' + ref + '</strong> copied!', 'success'));
  } else {
    const ta = document.createElement('textarea');
    ta.value = ref; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
    showNotif('✅ Booking ID copied!', 'success');
  }
}

// ── PDF Receipt Download ──────────────────────────────────────
function downloadPDFReceipt() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ unit: 'mm', format: 'a4' });
  const d = _bookingData;
  const pw = 210; // page width mm
  const gold = [201, 169, 110];
  const dark = [26, 18, 8];
  const cream = [250, 246, 239];

  // Background
  doc.setFillColor(...cream);
  doc.rect(0, 0, pw, 297, 'F');

  // Header band
  doc.setFillColor(...dark);
  doc.rect(0, 0, pw, 45, 'F');

  // Gold accent line
  doc.setFillColor(...gold);
  doc.rect(0, 45, pw, 2, 'F');

  // Logo / resort name
  doc.setTextColor(...gold);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(22);
  doc.text('<?= addslashes(htmlspecialchars($siteName)) ?>', pw/2, 18, { align: 'center' });
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8);
  doc.setTextColor(201, 169, 110, 0.6);
  doc.text('LUXURY  ·  EVENTS  ·  CELEBRATIONS', pw/2, 25, { align: 'center' });

  // Receipt title
  doc.setTextColor(255, 255, 255);
  doc.setFontSize(13);
  doc.setFont('helvetica', 'bold');
  doc.text('BOOKING ENQUIRY RECEIPT', pw/2, 35, { align: 'center' });

  // Booking ID box
  doc.setFillColor(...gold);
  doc.roundedRect(15, 53, pw - 30, 22, 2, 2, 'F');
  doc.setTextColor(...dark);
  doc.setFontSize(9);
  doc.setFont('helvetica', 'normal');
  doc.text('BOOKING REFERENCE', pw/2, 61, { align: 'center' });
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(18);
  doc.text(d.booking_ref || '—', pw/2, 70, { align: 'center' });

  // Sub-info row
  doc.setFillColor(240, 235, 225);
  doc.rect(15, 77, pw - 30, 10, 'F');
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8);
  doc.setTextColor(100, 80, 50);
  doc.text('Database ID: #' + (d.booking_id || '—'), pw/2 - 30, 83);
  doc.text('Submitted: ' + (d.submitted_at || '—'), pw/2 + 10, 83);

  // Section: Customer Details
  let y = 95;
  const drawSection = (title, rows) => {
    doc.setFillColor(...dark);
    doc.rect(15, y, pw - 30, 8, 'F');
    doc.setTextColor(...gold);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.text(title, 20, y + 5.5);
    y += 10;
    rows.forEach(([label, value], i) => {
      if (i % 2 === 0) {
        doc.setFillColor(248, 244, 237);
        doc.rect(15, y, pw - 30, 9, 'F');
      }
      doc.setTextColor(100, 80, 50);
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(8);
      doc.text(label, 20, y + 6);
      doc.setFont('helvetica', 'normal');
      doc.setTextColor(30, 20, 10);
      doc.text(String(value || '—'), 75, y + 6);
      y += 9;
    });
    y += 4;
  };

  drawSection('CUSTOMER DETAILS', [
    ['Full Name', d.full_name],
    ['Phone Number', d.phone],
    ['Email Address', d.email || 'Not provided'],
  ]);

  drawSection('EVENT DETAILS', [
    ['Event Type',       d.event_type    || '—'],
    ['Package Selected', d.package_name  || 'Not selected'],
    ['Check-In Date',    (d.event_date   || 'To be confirmed') + (d.event_date ? ' — 1:00 PM' : '')],
    ['Check-Out Date',   (d.checkout_date|| 'To be confirmed') + (d.checkout_date ? ' — 10:00 AM' : '')],
    ['Duration',         d.nights ? d.nights + ' night' + (d.nights !== 1 ? 's' : '') : '1 night'],
    ['Expected Guests',  d.guest_count   || '—'],
  ]);

  drawSection('BOOKING STATUS', [
    ['Current Status', 'New — Awaiting Confirmation'],
    ['Next Step', 'Our team will call within 24 hours'],
  ]);

  // Footer note
  y += 4;
  doc.setFillColor(...gold);
  doc.rect(15, y, pw - 30, 0.5, 'F');
  y += 6;
  doc.setTextColor(100, 80, 50);
  doc.setFont('helvetica', 'italic');
  doc.setFontSize(8);
  doc.text('This is an enquiry receipt. Your booking will be confirmed after our team contacts you.', pw/2, y, { align: 'center' });
  y += 6;
  doc.setFont('helvetica', 'normal');
  doc.text('<?= addslashes(htmlspecialchars($phone1)) ?>  ·  <?= addslashes(htmlspecialchars($email)) ?>', pw/2, y, { align: 'center' });
  y += 5;
  doc.text('<?= addslashes(htmlspecialchars($address)) ?>', pw/2, y, { align: 'center' });

  // Watermark
  doc.setTextColor(201, 169, 110);
  doc.setFontSize(60);
  doc.setFont('helvetica', 'bold');
  doc.setGState(doc.GState({ opacity: 0.04 }));
  doc.text('SELVI', pw/2, 180, { align: 'center', angle: 45 });
  doc.setGState(doc.GState({ opacity: 1 }));

  const filename = 'Selvi-Booking-' + (d.booking_ref || 'Receipt') + '.pdf';
  doc.save(filename);
  showNotif('📄 PDF receipt downloaded: ' + filename, 'success');
}

// ── Reset booking form ────────────────────────────────────────
function resetBookingForm() {
  document.getElementById('bform-inner').style.display = 'block';
  document.getElementById('bsuccess').style.display = 'none';
  ['b-name','b-phone','b-email','b-whatsapp','b-date','b-altdate','b-special'].forEach(id => {
    const el = document.getElementById(id); if (el) el.value = '';
  });
  ['b-event','b-pkg','b-guests','b-addon','b-heard'].forEach(id => {
    const el = document.getElementById(id); if (el) el.selectedIndex = 0;
  });
  if (window._rangePicker) window._rangePicker.clear();
  ['b-date','b-altdate','b-checkout-date'].forEach(id => { var el=document.getElementById(id); if(el) el.value=''; });
  var s2=document.getElementById('b-date-summary'); if(s2) s2.style.display='none';
  const btn = document.getElementById('b-submit-btn');
  btn.textContent = 'Send Booking Enquiry'; btn.disabled = false;
}

// ── Submit booking ────────────────────────────────────────────
async function submitBooking() {
  const name  = document.getElementById('b-name').value.trim();
  const phone = document.getElementById('b-phone').value.trim();
  const event = document.getElementById('b-event').value;
  if (!name)  { showNotif('⚠️ Please enter your full name.', 'error'); return; }
  if (!phone) { showNotif('⚠️ Please enter your phone number.', 'error'); return; }
  if (!event) { showNotif('⚠️ Please select an event type.', 'error'); return; }

  const fd = new FormData();
  fd.append('full_name',       name);
  fd.append('phone',           phone);
  fd.append('email',           document.getElementById('b-email').value);
  fd.append('whatsapp',        document.getElementById('b-whatsapp').value);
  fd.append('event_type',      event);
  fd.append('package_name',    document.getElementById('b-pkg').value);
  fd.append('event_date',      document.getElementById('b-date').value);
  fd.append('checkout_date',   document.getElementById('b-checkout-date').value);
  fd.append('alt_date',        document.getElementById('b-altdate').value);
  fd.append('time_slot',       document.getElementById('b-slot').value);
  fd.append('guest_count',     document.getElementById('b-guests').value);
  fd.append('addon_service',   document.getElementById('b-addon').value);
  fd.append('heard_from',      document.getElementById('b-heard').value);
  fd.append('special_request', document.getElementById('b-special').value);

  const btn = document.getElementById('b-submit-btn');
  btn.textContent = 'Submitting…'; btn.disabled = true;

  try {
    const res  = await fetch('api/booking.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      // Store for PDF
      _bookingData = data;

      // Populate confirmation card
      document.getElementById('bc-ref').textContent         = data.booking_ref  || '—';
      document.getElementById('bc-num-id').textContent      = '#' + (data.booking_id || '—');
      document.getElementById('bc-name').textContent        = data.full_name    || '—';
      document.getElementById('bc-phone').textContent       = data.phone        || '—';
      document.getElementById('bc-event').textContent       = data.event_type   || '—';
      document.getElementById('bc-pkg-confirm').textContent = data.package_name || 'Not selected';
      document.getElementById('bc-date').textContent        = data.event_date   || 'To be confirmed';
      document.getElementById('bc-guests').textContent      = data.guest_count  || '—';
      document.getElementById('bc-status').textContent      = data.status       || 'New';
      document.getElementById('bc-time').textContent        = data.submitted_at || '—';

      // Show confirmation
      document.getElementById('bform-inner').style.display = 'none';
      const s = document.getElementById('bsuccess');
      s.style.display = 'block';
      s.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else {
      showNotif('❌ ' + (data.message || 'Something went wrong. Please try again.'), 'error');
      btn.textContent = 'Send Booking Enquiry'; btn.disabled = false;
    }
  } catch(e) {
    showNotif('🌐 Network error. Please call us: <?= htmlspecialchars($phone1) ?>', 'error');
    btn.textContent = 'Send Booking Enquiry'; btn.disabled = false;
  }
}

// ── Submit contact ────────────────────────────────────────────
async function submitContact() {
  const name = document.getElementById('c-name').value.trim();
  const msg  = document.getElementById('c-message').value.trim();
  if (!name || !msg) { showNotif('⚠️ Please fill in your name and message.', 'error'); return; }

  const fd = new FormData();
  fd.append('full_name', name);
  fd.append('phone',     document.getElementById('c-phone').value);
  fd.append('email',     document.getElementById('c-email').value);
  fd.append('subject',   document.getElementById('c-subject').value);
  fd.append('message',   msg);

  const btn = document.querySelector('#page-contact .form-submit');
  btn.textContent = 'Sending…'; btn.disabled = true;

  try {
    const res  = await fetch('api/contact.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showNotif('✅ Message sent! We will get back to you within 24 hours.', 'success');
      ['c-name','c-phone','c-email','c-message'].forEach(id => document.getElementById(id).value = '');
    } else {
      showNotif('❌ ' + (data.message || 'Something went wrong.'), 'error');
    }
  } catch(e) {
    showNotif('🌐 Network error. Please email us directly.', 'error');
  }
  btn.textContent = 'Send Message'; btn.disabled = false;
}

// ── Flatpickr range picker — booked dates greyed out ─────────
(function() {
  var blocked = <?= $blockedDatesJSON ?? '[]' ?>;

  function toYMD(d) {
    return d.getFullYear() + '-' +
           String(d.getMonth()+1).padStart(2,'0') + '-' +
           String(d.getDate()).padStart(2,'0');
  }
  function fmtDisp(d) {
    return d.toLocaleDateString('en-IN', {day:'2-digit', month:'short', year:'numeric'});
  }

  window._rangePicker = flatpickr('#b-date-range', {
    mode             : 'range',
    minDate          : 'today',
    dateFormat       : 'Y-m-d',
    monthSelectorType: 'static',
    disable          : blocked,
    onDayCreate: function(dObj, dStr, fp, dayElem) {
      if (!dayElem.dateObj) return;
      var iso = toYMD(dayElem.dateObj);
      if (blocked.indexOf(iso) !== -1)
        dayElem.title = 'Already booked — please choose another date';
    },
    onChange: function(sel, dateStr) {
      if (sel.length === 2) {
        var startYMD = toYMD(sel[0]);
        var endYMD   = toYMD(sel[1]);
        var nights   = Math.round((sel[1] - sel[0]) / 86400000);
        document.getElementById('b-date').value          = startYMD;
        document.getElementById('b-checkout-date').value = endYMD;
        document.getElementById('b-altdate').value       = '';
        var sum = document.getElementById('b-date-summary');
        sum.style.display = 'flex';
        document.getElementById('b-checkin-display').textContent  = '🕐 ' + fmtDisp(sel[0]) + ' — 1:00 PM';
        document.getElementById('b-checkout-display').textContent = '🕙 ' + fmtDisp(sel[1]) + ' — 10:00 AM';
        document.getElementById('b-duration-display').textContent = '📆 ' + nights + ' night' + (nights!==1?'s':'');
      } else if (sel.length === 0) {
        ['b-date','b-checkout-date'].forEach(function(id){
          document.getElementById(id).value = '';
        });
        document.getElementById('b-date-summary').style.display = 'none';
      }
    }
  });
})();

</script>
</body>
</html>
