<?php
session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['username'])) {
    header('Location: ../login/index.php');
    exit;
}

include_once '../database.php';

$events = [];
$loadError = '';

try {
	$sql = "SELECT id, name, category, time_start, time_end, restriction, description, place, city, curr_letszam, max_letszam
			FROM esemenyek
			ORDER BY time_start DESC, id DESC";
	$result = mysqli_query($conn, $sql);
	if ($result) {
		$events = mysqli_fetch_all($result, MYSQLI_ASSOC);
		mysqli_free_result($result);
	} else {
		$loadError = 'Nem sikerült betölteni az eseményeket.';
	}
} catch (Exception $e) {
	$loadError = 'Hiba történt az események betöltése során.';
}

function formatEventDate($dateValue)
{
	if (!$dateValue) {
		return 'Nincs megadva';
	}
	$timestamp = strtotime($dateValue);
	return $timestamp ? date('Y.m.d H:i', $timestamp) : 'Nincs megadva';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<link rel="stylesheet" href="../navbar/navbar.css">
	<link rel="stylesheet" href="styles.css">
	<title>EventSearch</title>
</head>

<body>
	<?php
	$activePage = 'eventsearch';
	include_once '../navbar/navbar.php';
	?>

	<main class="page-shell">
		<h1>Események</h1>

		<?php if ($loadError !== ''): ?>
			<p class="state error"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></p>
		<?php elseif (count($events) === 0): ?>
			<p class="state">Még nincs létrehozott esemény.</p>
		<?php else: ?>
			<section class="events-grid">
				<?php foreach ($events as $event): ?>
					<article class="event-card">
						<h2><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></h2>
						<p class="event-category"><?= htmlspecialchars($event['category'], ENT_QUOTES, 'UTF-8') ?></p>
						<ul class="event-meta">
							<li><strong>Kezdés:</strong> <?= htmlspecialchars(formatEventDate($event['time_start']), ENT_QUOTES, 'UTF-8') ?></li>
							<li><strong>Vége:</strong> <?= htmlspecialchars(formatEventDate($event['time_end']), ENT_QUOTES, 'UTF-8') ?></li>
							<li><strong>Helyszín:</strong> <?= htmlspecialchars(trim(($event['city'] ?? '') . ', ' . ($event['place'] ?? '')), ENT_QUOTES, 'UTF-8') ?></li>
							<li><strong>Korhatár:</strong> <?= htmlspecialchars((string) $event['restriction'], ENT_QUOTES, 'UTF-8') ?>+</li>
							<li><strong>Létszám:</strong> <?= htmlspecialchars((string) ($event['curr_letszam']), ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) ($event['max_letszam']), ENT_QUOTES, 'UTF-8') ?></li>

						</ul>
					</article>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>
	</main>
</body>

</html>
