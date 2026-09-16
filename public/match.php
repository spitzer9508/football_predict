<?php
require dirname(__DIR__) . '/bootstrap.php';

use App\Support\Database;

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
function statValue(mixed $value, string $suffix = ''): string { return $value === null ? '—' : e($value) . $suffix; }

$providerId = trim((string) ($_GET['id'] ?? ''));
$fixture = null;
$stats = [];

if ($providerId !== '') {
    try {
        $pdo = Database::connection();
        $statement = $pdo->prepare(
            "SELECT f.*, c.name competition_name, c.country_name,
                    h.name home_name, h.short_name home_short, h.logo home_logo,
                    a.name away_name, a.short_name away_short, a.logo away_logo
             FROM fixtures f
             JOIN competitions c ON c.id = f.competition_id
             JOIN teams h ON h.id = f.home_team_id
             JOIN teams a ON a.id = f.away_team_id
             WHERE f.provider_id = ? LIMIT 1"
        );
        $statement->execute([$providerId]);
        $fixture = $statement->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($fixture) {
            $s = $pdo->prepare(
                "SELECT s.*, t.name team_name
                 FROM team_match_stats s
                 JOIN teams t ON t.id = s.team_id
                 WHERE s.fixture_id = ? AND s.period = 'MATCH'"
            );
            $s->execute([$fixture['id']]);
            foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $row) $stats[(int) $row['team_id']] = $row;
        }
    } catch (Throwable $e) {
        $fixture = null;
    }
}

if (!$fixture) { http_response_code(404); }
$homeStats = $fixture ? ($stats[(int) $fixture['home_team_id']] ?? []) : [];
$awayStats = $fixture ? ($stats[(int) $fixture['away_team_id']] ?? []) : [];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $fixture ? e($fixture['home_name'].' vs '.$fixture['away_name']) : 'Match not found' ?> — TukiScore</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>.grid-bg{background-image:linear-gradient(rgba(255,255,255,.03) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.03) 1px,transparent 1px);background-size:38px 38px}</style></head>
<body class="bg-[#07110e] text-slate-100 min-h-screen antialiased"><div class="fixed inset-0 grid-bg pointer-events-none"></div>
<header class="relative border-b border-white/10"><div class="max-w-6xl mx-auto h-16 px-5 flex items-center justify-between"><a href="/" class="flex items-center gap-3 font-bold"><span class="w-9 h-9 rounded-xl bg-emerald-400 text-[#07110e] grid place-items-center font-black">T</span>TukiScore</a><a href="/" class="text-sm text-slate-400 hover:text-white">← Match centre</a></div></header>
<main class="relative max-w-6xl mx-auto px-5 py-10">
<?php if (!$fixture): ?><div class="py-24 text-center"><div class="text-5xl mb-5">404</div><h1 class="text-2xl font-bold">Match not found</h1><p class="text-slate-500 mt-2">The requested fixture is not available in the local dataset.</p></div>
<?php else: ?>
<div class="text-xs text-slate-500 mb-5"><?= e(($fixture['country_name'] ? $fixture['country_name'].' · ' : '').$fixture['competition_name']) ?><?php if ($fixture['round_name']): ?> · <?= e($fixture['round_name']) ?><?php endif; ?></div>
<section class="rounded-3xl border border-white/10 bg-white/[.035] p-6 sm:p-10">
<div class="grid grid-cols-[1fr_auto_1fr] items-center gap-4 sm:gap-10"><div class="text-center"><div class="mx-auto w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/5 border border-white/10 grid place-items-center overflow-hidden"><?php if ($fixture['home_logo']): ?><img src="<?= e($fixture['home_logo']) ?>" class="w-12 h-12 object-contain" alt=""><?php else: ?><span class="font-bold text-xl"><?= e(substr($fixture['home_name'],0,2)) ?></span><?php endif; ?></div><h1 class="font-bold text-base sm:text-xl mt-4"><?= e($fixture['home_name']) ?></h1></div>
<div class="text-center"><div class="text-[10px] uppercase tracking-[.2em] text-emerald-300 mb-3"><?= e($fixture['status']) ?></div><div class="text-3xl sm:text-5xl font-black tracking-tight"><?= $fixture['home_score'] !== null ? e($fixture['home_score'].' – '.$fixture['away_score']) : 'VS' ?></div><div class="text-xs text-slate-500 mt-3"><?= e($fixture['kickoff']) ?> UTC</div></div>
<div class="text-center"><div class="mx-auto w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/5 border border-white/10 grid place-items-center overflow-hidden"><?php if ($fixture['away_logo']): ?><img src="<?= e($fixture['away_logo']) ?>" class="w-12 h-12 object-contain" alt=""><?php else: ?><span class="font-bold text-xl"><?= e(substr($fixture['away_name'],0,2)) ?></span><?php endif; ?></div><h1 class="font-bold text-base sm:text-xl mt-4"><?= e($fixture['away_name']) ?></h1></div></div>
<?php if ($fixture['venue'] || $fixture['referee_name']): ?><div class="mt-8 pt-5 border-t border-white/10 flex flex-wrap justify-center gap-x-7 gap-y-2 text-xs text-slate-500"><?php if ($fixture['venue']): ?><span>Venue · <?= e($fixture['venue']) ?></span><?php endif; ?><?php if ($fixture['referee_name']): ?><span>Referee · <?= e($fixture['referee_name']) ?></span><?php endif; ?></div><?php endif; ?>
</section>
<div class="grid lg:grid-cols-[1.25fr_.75fr] gap-4 mt-4">
<section class="rounded-3xl border border-white/10 bg-white/[.025] p-6"><div class="flex justify-between items-end mb-6"><div><div class="text-xs uppercase tracking-[.18em] text-slate-500">Match data</div><h2 class="font-bold text-xl mt-1">Team statistics</h2></div><span class="text-xs text-slate-600">Full match</span></div>
<?php if (!$homeStats && !$awayStats): ?><div class="py-14 text-center text-sm text-slate-500">Statistics have not been imported for this match yet.</div><?php else: ?>
<div class="space-y-1"><?php foreach ([['xg','Expected goals'],['possession','Possession','%'],['shots','Shots'],['shots_on_target','Shots on target'],['big_chances','Big chances'],['corners','Corners'],['fouls','Fouls'],['yellow_cards','Yellow cards'],['red_cards','Red cards']] as $metric): ?><div class="grid grid-cols-[70px_1fr_70px] items-center py-3 border-b border-white/5 last:border-0"><div class="font-mono font-semibold"><?= statValue($homeStats[$metric[0]] ?? null,$metric[2] ?? '') ?></div><div class="text-center text-xs text-slate-500"><?= e($metric[1]) ?></div><div class="font-mono font-semibold text-right"><?= statValue($awayStats[$metric[0]] ?? null,$metric[2] ?? '') ?></div></div><?php endforeach; ?></div><?php endif; ?></section>
<aside class="space-y-4"><section class="rounded-3xl border border-emerald-400/15 bg-emerald-400/[.035] p-6"><div class="text-xs uppercase tracking-[.18em] text-emerald-300">TukiScore forecast</div><h2 class="font-bold text-xl mt-2">Model not published yet</h2><p class="text-sm text-slate-400 mt-3 leading-6">Historical data is currently being collected. Probabilities will appear here after the prediction engine has enough validated features.</p><div class="mt-5 grid grid-cols-3 gap-2"><?php foreach (['Home','Draw','Away'] as $label): ?><div class="rounded-xl bg-black/20 p-3 text-center"><div class="text-[10px] text-slate-600"><?= $label ?></div><div class="font-mono mt-1">—</div></div><?php endforeach; ?></div></section>
<section class="rounded-3xl border border-white/10 bg-white/[.025] p-6"><div class="text-xs uppercase tracking-[.18em] text-slate-500">Confidence</div><div class="flex items-end gap-2 mt-3"><span class="text-3xl font-black">—</span><span class="text-sm text-slate-600 mb-1">/ 100</span></div><p class="text-xs text-slate-500 mt-3">Confidence will measure data completeness, model agreement, calibration, sample size and freshness.</p></section></aside></div>
<?php endif; ?></main></body></html>
