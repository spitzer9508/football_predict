<?php
require dirname(__DIR__) . '/bootstrap.php';

use App\Support\Database;

$fixtures = [];
try {
    $pdo = Database::connection();
    $sql = "SELECT f.provider_id, f.kickoff, f.status, f.home_score, f.away_score,
                   c.name competition_name, c.country_name,
                   h.name home_name, h.logo home_logo,
                   a.name away_name, a.logo away_logo
            FROM fixtures f
            JOIN competitions c ON c.id = f.competition_id
            JOIN teams h ON h.id = f.home_team_id
            JOIN teams a ON a.id = f.away_team_id
            WHERE c.prediction_enabled = 1
              AND f.kickoff >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 12 HOUR)
            ORDER BY f.kickoff ASC LIMIT 18";
    $fixtures = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $fixtures = [];
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function fixtureTime(string $utc): string {
    try { $d = new DateTime($utc, new DateTimeZone('UTC')); $d->setTimezone(new DateTimeZone('Europe/Berlin')); return $d->format('H:i'); }
    catch (Throwable) { return '--:--'; }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TukiScore — Football Intelligence</title>
<meta name="description" content="Data-driven football probabilities, match intelligence and model confidence.">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
[x-cloak]{display:none!important}.grid-bg{background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);background-size:38px 38px}.glow{box-shadow:0 0 70px rgba(52,211,153,.08)}
</style>
</head>
<body class="bg-[#07110e] text-slate-100 antialiased min-h-screen">
<div class="fixed inset-0 grid-bg pointer-events-none"></div>
<header class="relative border-b border-white/10 bg-[#07110e]/85 backdrop-blur-xl sticky top-0 z-30">
<div class="max-w-7xl mx-auto h-16 px-5 flex items-center justify-between">
<a href="/" class="flex items-center gap-3"><span class="w-9 h-9 rounded-xl bg-emerald-400 text-[#07110e] grid place-items-center font-black">T</span><span class="font-bold tracking-tight text-lg">TukiScore</span><span class="hidden sm:inline text-[10px] uppercase tracking-[.22em] text-emerald-300 border border-emerald-400/20 bg-emerald-400/5 rounded-full px-2 py-1">Intelligence</span></a>
<nav class="hidden md:flex items-center gap-7 text-sm text-slate-400"><a class="text-white" href="/">Matches</a><a class="hover:text-white" href="#models">Models</a><a class="hover:text-white" href="#performance">Performance</a><a class="hover:text-white" href="#about">Methodology</a></nav>
<div class="flex items-center gap-2"><span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span><span class="text-xs text-slate-400">Data engine online</span></div>
</div></header>
<main class="relative">
<section class="max-w-7xl mx-auto px-5 pt-14 pb-10">
<div class="max-w-3xl"><div class="text-emerald-300 text-xs uppercase tracking-[.25em] font-semibold mb-4">Football, measured</div><h1 class="text-4xl sm:text-6xl font-black tracking-[-.04em] leading-[.98]">Probabilities before opinions.</h1><p class="mt-6 text-lg text-slate-400 max-w-2xl leading-8">Match intelligence built from historical performance, xG, shots, team strength and context. Every forecast shows probability and confidence separately.</p></div>
<div class="grid sm:grid-cols-3 gap-3 mt-10 max-w-3xl"><div class="rounded-2xl border border-white/10 bg-white/[.035] p-4"><div class="text-xs text-slate-500">Coverage</div><div class="mt-1 font-semibold">Top 5 leagues + UCL</div></div><div class="rounded-2xl border border-white/10 bg-white/[.035] p-4"><div class="text-xs text-slate-500">Markets</div><div class="mt-1 font-semibold">Result · Goals · Shots</div></div><div class="rounded-2xl border border-white/10 bg-white/[.035] p-4"><div class="text-xs text-slate-500">Approach</div><div class="mt-1 font-semibold">Calibrated probabilities</div></div></div>
</section>
<section class="max-w-7xl mx-auto px-5 pb-20" x-data="{filter:'all'}">
<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-5 mb-6"><div><p class="text-xs uppercase tracking-[.2em] text-slate-500">Match centre</p><h2 class="text-2xl font-bold mt-1">Upcoming fixtures</h2></div><div class="flex gap-2 overflow-x-auto pb-1"><button @click="filter='all'" :class="filter==='all'?'bg-white text-black':'bg-white/5 text-slate-400'" class="px-4 py-2 rounded-xl text-xs font-semibold">All</button><button @click="filter='today'" :class="filter==='today'?'bg-white text-black':'bg-white/5 text-slate-400'" class="px-4 py-2 rounded-xl text-xs font-semibold">Today</button><button @click="filter='models'" :class="filter==='models'?'bg-white text-black':'bg-white/5 text-slate-400'" class="px-4 py-2 rounded-xl text-xs font-semibold">Model ready</button></div></div>
<?php if (!$fixtures): ?>
<div class="rounded-3xl border border-white/10 bg-white/[.025] p-10 sm:p-16 text-center glow"><div class="mx-auto w-12 h-12 rounded-2xl bg-emerald-400/10 border border-emerald-400/20 grid place-items-center text-emerald-300 text-xl">↗</div><h3 class="font-bold text-xl mt-5">Match intelligence is being prepared</h3><p class="text-slate-500 mt-2 max-w-md mx-auto">Fixtures will appear here as the data collector populates supported competitions.</p></div>
<?php else: ?>
<div class="grid lg:grid-cols-2 gap-3">
<?php foreach ($fixtures as $f): ?>
<article class="group rounded-2xl border border-white/10 bg-white/[.03] hover:bg-white/[.055] transition p-5">
<div class="flex justify-between items-center text-xs text-slate-500 mb-5"><span><?= e(($f['country_name'] ? $f['country_name'].' · ' : '').$f['competition_name']) ?></span><span><?= e(fixtureTime($f['kickoff'])) ?></span></div>
<div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4"><div class="min-w-0"><div class="font-semibold truncate"><?= e($f['home_name']) ?></div></div><div class="text-center"><div class="text-[10px] uppercase tracking-widest text-slate-600 mb-1"><?= e($f['status']) ?></div><div class="font-mono text-lg font-bold"><?= $f['home_score'] !== null ? e($f['home_score'].' – '.$f['away_score']) : 'vs' ?></div></div><div class="min-w-0 text-right"><div class="font-semibold truncate"><?= e($f['away_name']) ?></div></div></div>
<div class="mt-5 pt-4 border-t border-white/5 flex items-center justify-between"><span class="text-xs text-slate-500">Prediction model pending</span><span class="text-emerald-300 text-xs font-semibold group-hover:translate-x-1 transition">Analysis →</span></div>
</article>
<?php endforeach; ?>
</div><?php endif; ?>
</section>
<section id="models" class="border-y border-white/10 bg-black/10"><div class="max-w-7xl mx-auto px-5 py-20"><div class="grid lg:grid-cols-[.8fr_1.2fr] gap-12"><div><p class="text-xs uppercase tracking-[.2em] text-emerald-300">Model stack</p><h2 class="text-3xl font-bold mt-3">One match. Multiple independent signals.</h2><p class="text-slate-500 mt-4 leading-7">The platform separates market models instead of forcing one prediction to explain everything.</p></div><div class="grid sm:grid-cols-2 gap-3"><?php foreach ([['01','Result','Home · Draw · Away'],['02','Goals','xG · totals · BTTS'],['03','Shot volume','Shots · shots on target'],['04','Match texture','Corners · cards · fouls']] as $m): ?><div class="p-5 rounded-2xl border border-white/10 bg-white/[.025]"><span class="font-mono text-xs text-emerald-300"><?= $m[0] ?></span><h3 class="font-bold mt-5"><?= $m[1] ?></h3><p class="text-sm text-slate-500 mt-1"><?= $m[2] ?></p></div><?php endforeach; ?></div></div></div></section>
<section id="performance" class="max-w-7xl mx-auto px-5 py-20"><div class="rounded-3xl border border-white/10 bg-gradient-to-br from-white/[.05] to-transparent p-7 sm:p-10"><div class="flex flex-col lg:flex-row lg:items-center justify-between gap-8"><div class="max-w-xl"><p class="text-xs uppercase tracking-[.2em] text-emerald-300">Transparent by design</p><h2 class="text-3xl font-bold mt-3">A prediction is only useful if its history is measurable.</h2><p class="text-slate-400 mt-4 leading-7">Performance reporting will expose calibration, Brier score, log loss and market-level accuracy rather than hiding misses.</p></div><div class="grid grid-cols-2 gap-3 min-w-[300px]"><div class="bg-black/20 rounded-2xl p-4"><div class="text-xs text-slate-500">Calibration</div><div class="font-bold mt-1">Tracked</div></div><div class="bg-black/20 rounded-2xl p-4"><div class="text-xs text-slate-500">Versions</div><div class="font-bold mt-1">Auditable</div></div></div></div></div></section>
</main>
<footer id="about" class="relative border-t border-white/10"><div class="max-w-7xl mx-auto px-5 py-8 flex flex-col sm:flex-row gap-4 justify-between text-xs text-slate-600"><span>© <?= date('Y') ?> TukiScore Football Intelligence</span><span>Probabilities are estimates, not guarantees.</span></div></footer>
</body></html>
