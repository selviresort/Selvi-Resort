<?php // admin/admin_style.css.php — outputs CSS ?>
:root{--gold:#c9a96e;--gold-light:#e8d5a3;--dark:#1a1208;--dark2:#2c1f0e;--sidebar-w:240px;--cream:#faf6ef}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Jost',sans-serif;background:#f4f1eb;color:#333;display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{width:var(--sidebar-w);background:var(--dark);min-height:100vh;position:fixed;left:0;top:0;bottom:0;display:flex;flex-direction:column;z-index:100;border-right:1px solid rgba(201,169,110,.18)}
.sidebar-logo{padding:22px 20px;border-bottom:1px solid rgba(201,169,110,.15)}
.sidebar-logo h2{font-family:'Cormorant Garamond',serif;color:var(--gold);font-size:1.15rem;font-weight:600}
.sidebar-logo p{color:rgba(255,255,255,.3);font-size:.62rem;letter-spacing:3px;text-transform:uppercase;margin-top:3px}
.sidebar-nav{flex:1;padding:16px 0}
.nav-section{padding:8px 20px 4px;font-size:.6rem;letter-spacing:4px;text-transform:uppercase;color:rgba(255,255,255,.22);font-weight:500}
.sidebar-nav a{display:flex;align-items:center;gap:11px;padding:11px 20px;color:rgba(255,255,255,.58);text-decoration:none;font-size:.82rem;transition:all .25s;border-left:3px solid transparent}
.sidebar-nav a:hover,.sidebar-nav a.active{background:rgba(201,169,110,.08);color:var(--gold);border-left-color:var(--gold)}
.sidebar-nav a .nav-icon{font-size:1rem;width:20px;text-align:center}
.sidebar-nav a .badge-count{margin-left:auto;background:rgba(239,68,68,.85);color:#fff;font-size:.6rem;padding:2px 7px;border-radius:10px}
.sidebar-footer{padding:16px 20px;border-top:1px solid rgba(201,169,110,.12)}
.sidebar-footer a{display:flex;align-items:center;gap:9px;color:rgba(255,255,255,.4);text-decoration:none;font-size:.78rem;transition:color .25s}
.sidebar-footer a:hover{color:var(--gold)}

/* MAIN */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:#fff;padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(201,169,110,.2);position:sticky;top:0;z-index:50}
.topbar h3{font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--dark);font-weight:400}
.topbar-right{display:flex;align-items:center;gap:18px}
.topbar-user{font-size:.78rem;color:#666}
.topbar-user strong{color:var(--dark)}
.btn-logout{background:transparent;border:1px solid rgba(201,169,110,.35);color:var(--gold);padding:6px 14px;font-size:.72rem;letter-spacing:1px;cursor:pointer;font-family:'Jost',sans-serif;transition:all .25s;text-decoration:none}
.btn-logout:hover{background:var(--gold);color:var(--dark)}
.content{padding:28px;flex:1}

/* PAGE TITLE */
.page-title{margin-bottom:26px}
.page-title h2{font-family:'Cormorant Garamond',serif;font-size:1.7rem;color:var(--dark);font-weight:400}
.page-title p{color:#888;font-size:.82rem;margin-top:3px}

/* STAT CARDS */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:16px;margin-bottom:26px}
.stat-card{background:#fff;padding:20px;border:1px solid rgba(201,169,110,.15);display:flex;align-items:center;gap:15px;border-radius:2px}
.sc-icon{width:46px;height:46px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
.sc-num{font-family:'Cormorant Garamond',serif;font-size:2rem;color:var(--dark);font-weight:600;line-height:1}
.sc-label{font-size:.7rem;color:#888;text-transform:uppercase;letter-spacing:1px;margin-top:3px}

/* CARDS */
.card{background:#fff;border:1px solid rgba(201,169,110,.14);margin-bottom:22px}
.card-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid rgba(201,169,110,.12)}
.card-header h3{font-family:'Cormorant Garamond',serif;font-size:1.1rem;color:var(--dark);font-weight:400}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:22px}

/* TABLE */
.data-table{width:100%;border-collapse:collapse;font-size:.82rem}
.data-table th{background:#faf6ef;padding:11px 14px;text-align:left;font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);font-weight:500;border-bottom:1px solid rgba(201,169,110,.18)}
.data-table td{padding:11px 14px;border-bottom:1px solid rgba(201,169,110,.08);color:#444;vertical-align:middle}
.data-table tr:hover td{background:#fdfaf5}
.data-table tr:last-child td{border-bottom:none}
code{background:#f4f1eb;padding:2px 7px;font-size:.78rem;font-family:monospace;color:var(--dark)}

/* BADGES */
.badge{padding:3px 10px;font-size:.65rem;letter-spacing:1px;text-transform:uppercase;font-weight:600;display:inline-block}
.badge-new{background:rgba(239,68,68,.1);color:#dc2626}
.badge-contacted{background:rgba(251,191,36,.12);color:#d97706}
.badge-confirmed{background:rgba(34,197,94,.1);color:#16a34a}
.badge-completed{background:rgba(59,130,246,.1);color:#2563eb}
.badge-cancelled{background:rgba(107,114,128,.1);color:#6b7280}
.badge-unread{background:rgba(168,85,247,.1);color:#9333ea}
.badge-read{background:rgba(107,114,128,.1);color:#6b7280}
.badge-replied{background:rgba(34,197,94,.1);color:#16a34a}

/* BUTTONS */
.btn-sm{padding:6px 14px;background:var(--gold);color:var(--dark);font-size:.7rem;letter-spacing:2px;text-transform:uppercase;font-weight:600;text-decoration:none;border:none;cursor:pointer;font-family:'Jost',sans-serif;transition:all .25s}
.btn-sm:hover{background:var(--gold-light)}
.btn-xs{padding:4px 10px;background:transparent;border:1px solid rgba(201,169,110,.35);color:var(--gold);font-size:.68rem;letter-spacing:1px;text-transform:uppercase;text-decoration:none;transition:all .2s;font-family:'Jost',sans-serif}
.btn-xs:hover{background:var(--gold);color:var(--dark)}
.btn-danger{padding:6px 14px;background:rgba(239,68,68,.1);color:#dc2626;border:1px solid rgba(239,68,68,.25);font-size:.7rem;letter-spacing:1px;text-transform:uppercase;cursor:pointer;font-family:'Jost',sans-serif;transition:all .25s;text-decoration:none}
.btn-danger:hover{background:#dc2626;color:#fff}
.btn-primary{display:inline-block;padding:10px 22px;background:var(--gold);color:var(--dark);font-size:.76rem;letter-spacing:3px;text-transform:uppercase;font-weight:700;border:none;cursor:pointer;font-family:'Jost',sans-serif;transition:all .3s;text-decoration:none}
.btn-primary:hover{background:var(--gold-light)}

/* MESSAGES */
.msg-item{padding:14px 20px;border-bottom:1px solid rgba(201,169,110,.1);transition:background .2s}
.msg-item:hover{background:#fdfaf5}
.msg-item.unread{background:#fffbf3}
.msg-name{font-weight:600;font-size:.86rem;color:var(--dark);display:flex;align-items:center;gap:7px;margin-bottom:3px}
.dot{width:7px;height:7px;border-radius:50%;background:#ef4444;flex-shrink:0}
.msg-sub{font-size:.78rem;color:var(--gold);font-weight:500}
.msg-preview{font-size:.8rem;color:#888;margin-top:4px;line-height:1.5}
.msg-date{font-size:.7rem;color:#bbb;margin-top:5px}

/* FORMS */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.form-group{display:flex;flex-direction:column;gap:6px}
.form-group.full{grid-column:span 2}
.form-group label{font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);font-weight:500}
.form-group input,.form-group select,.form-group textarea{border:1px solid rgba(201,169,110,.28);background:#fff;padding:10px 13px;font-family:'Jost',sans-serif;font-size:.86rem;outline:none;color:#333;transition:border .25s}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--gold);background:#fffbf5}
.form-group textarea{resize:vertical;min-height:90px}
.form-actions{display:flex;gap:12px;margin-top:20px;align-items:center;padding:16px 20px;border-top:1px solid rgba(201,169,110,.12);background:#fdfaf5}

/* DETAIL VIEW */
.detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;padding:20px}
.detail-item{padding:12px 0;border-bottom:1px solid rgba(201,169,110,.08)}
.detail-label{font-size:.68rem;letter-spacing:2px;text-transform:uppercase;color:var(--gold);margin-bottom:4px;font-weight:500}
.detail-value{font-size:.9rem;color:#333;font-weight:400}
.status-form{padding:20px;background:#fdfaf5;border-top:1px solid rgba(201,169,110,.12)}
.status-form h4{font-family:'Cormorant Garamond',serif;font-size:1rem;color:var(--dark);margin-bottom:14px}

/* ALERT */
.alert{padding:12px 16px;font-size:.82rem;margin-bottom:20px;border:1px solid}
.alert-success{background:rgba(34,197,94,.08);border-color:rgba(34,197,94,.25);color:#166534}
.alert-error{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25);color:#991b1b}

@media(max-width:900px){.two-col,.form-grid{grid-template-columns:1fr}.form-group.full{grid-column:span 1}}
@media(max-width:700px){.sidebar{width:0;overflow:hidden}.main{margin-left:0}}
