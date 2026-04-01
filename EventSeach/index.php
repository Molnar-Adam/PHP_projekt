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

$currentUsername = (string) ($_SESSION['username'] ?? '');
$events = [];
$eventsForView = [];
$loadError = '';
$actionNotice = '';
$actionNoticeType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'toggle_attendance')) {
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);

        try {
            $stmtSelect = mysqli_prepare($conn, 'SELECT attending_users, curr_letszam, max_letszam FROM esemenyek WHERE id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmtSelect, 'i', $eventId);
            mysqli_stmt_execute($stmtSelect);
            $result = mysqli_stmt_get_result($stmtSelect);
            $eventRow = $result ? mysqli_fetch_assoc($result) : null;
            if ($result) {
                mysqli_free_result($result);
            }
            mysqli_stmt_close($stmtSelect);

                $attendingUsers = json_decode((string) ($eventRow['attending_users'] ?? '[]'), true);
                if (!is_array($attendingUsers)) {
                    $attendingUsers = [];
                }

                $normalizedUsers = [];
                foreach ($attendingUsers as $username) {
                    if (is_string($username) && $username !== '') {
                        $normalizedUsers[] = $username;
                    }
                }
                $attendingUsers = array_values(array_unique($normalizedUsers));

                $currLetszam = (int) ($eventRow['curr_letszam'] ?? 0);
                $hasLimit = $eventRow['max_letszam'] !== null;
                $maxLetszam = $hasLimit ? (int) $eventRow['max_letszam'] : 0;
                $alreadyAttending = in_array($currentUsername, $attendingUsers, true);

                if ($alreadyAttending) {
                    $attendingUsers = array_values(array_filter(
                        $attendingUsers,
                        static fn($username) => $username !== $currentUsername
                    ));
                    $currLetszam = max(0, $currLetszam - 1);
                    $actionNotice = 'Sikeres lejelentkezes az esemenyrol.';
                    $actionNoticeType = 'success';
                } else {
                    if ($hasLimit && $currLetszam >= $maxLetszam) {
                        $actionNotice = 'Az esemeny megtelt, nem lehet jelentkezni.';
                        $actionNoticeType = 'error';
                    } else {
                        $attendingUsers[] = $currentUsername;
                        $currLetszam++;
                        $actionNotice = 'Sikeres jelentkezes az esemenyre.';
                        $actionNoticeType = 'success';
                    }
                }

                if ($actionNoticeType === 'success') {
                    $usersJson = json_encode(array_values($attendingUsers), JSON_UNESCAPED_UNICODE);
                    $stmtUpdate = mysqli_prepare($conn, 'UPDATE esemenyek SET attending_users = ?, curr_letszam = ? WHERE id = ?');
                    mysqli_stmt_bind_param($stmtUpdate, 'sii', $usersJson, $currLetszam, $eventId);
                    $updateOk = mysqli_stmt_execute($stmtUpdate);
                    mysqli_stmt_close($stmtUpdate);

                    if (!$updateOk) {
                        $actionNotice = 'Nem sikerult frissiteni a jelentkezest.';
                        $actionNoticeType = 'error';
                    }
                }
            
        } catch (Exception $e) {
            $actionNotice = 'Hiba tortent a jelentkezes kezelese soran.';
            $actionNoticeType = 'error';
        }
    }


try {
    $sql = "SELECT id, name, category, time_start, time_end, restriction, description, place, city, curr_letszam, max_letszam, attending_users
            FROM esemenyek
            ORDER BY time_start DESC, id DESC";
    $result = mysqli_query($conn, $sql);
    if ($result) {
        $events = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);
    } else {
        $loadError = 'Nem sikerult betolteni az esemenyeket.';
    }
} catch (Exception $e) {
    $loadError = 'Hiba tortent az esemenyek betoltese soran.';
}

function formatEventDate($dateValue)
{
    if (!$dateValue) {
        return 'Nincs megadva';
    }
    $timestamp = strtotime($dateValue);
    return $timestamp ? date('Y.m.d H:i', $timestamp) : 'Nincs megadva';
}

foreach ($events as $event) {
    $formattedStart = formatEventDate($event['time_start'] ?? null);
    $formattedEnd = formatEventDate($event['time_end'] ?? null);

    $attendingUsers = json_decode((string) ($event['attending_users'] ?? '[]'), true);
    if (!is_array($attendingUsers)) {
        $attendingUsers = [];
    }

    $normalizedUsers = [];
    foreach ($attendingUsers as $username) {
        if (is_string($username) && $username !== '') {
            $normalizedUsers[] = $username;
        }
    }
    $attendingUsers = array_values(array_unique($normalizedUsers));

    $currLetszam = (int) ($event['curr_letszam'] ?? 0);
    $maxLetszam = $event['max_letszam'] !== null ? (int) $event['max_letszam'] : null;
    $isAttending = in_array($currentUsername, $attendingUsers, true);
    $isFull = $maxLetszam !== null && $currLetszam >= $maxLetszam;

    $payload = json_encode([
        'id' => (int) ($event['id'] ?? 0),
        'name' => (string) ($event['name'] ?? ''),
        'category' => (string) ($event['category'] ?? ''),
        'description' => (string) ($event['description'] ?? ''),
        'time_start' => $formattedStart,
        'time_end' => $formattedEnd,
        'city' => (string) ($event['city'] ?? ''),
        'place' => (string) ($event['place'] ?? ''),
        'restriction' => (string) ($event['restriction'] ?? ''),
        'curr_letszam' => (string) ($event['curr_letszam'] ?? ''),
        'max_letszam' => (string) ($event['max_letszam'] ?? ''),
        'is_attending' => $isAttending,
        'is_full' => $isFull,
    ], JSON_UNESCAPED_UNICODE);

    $eventsForView[] = [
        'name' => (string) ($event['name'] ?? ''),
        'category' => (string) ($event['category'] ?? ''),
        'formatted_time_start' => $formattedStart,
        'formatted_time_end' => $formattedEnd,
        'location' => trim((string) (($event['city'] ?? '') . ', ' . ($event['place'] ?? '')), ' ,'),
        'restriction' => (string) ($event['restriction'] ?? ''),
        'curr_letszam' => (string) ($event['curr_letszam'] ?? ''),
        'max_letszam' => (string) ($event['max_letszam'] ?? ''),
        'payload' => htmlspecialchars($payload ?: '{}', ENT_QUOTES, 'UTF-8'),
    ];
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

        <?php if ($actionNotice !== ''): ?>
            <p class="state <?= $actionNoticeType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($actionNotice, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($loadError !== ''): ?>
            <p class="state error"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif (count($eventsForView) === 0): ?>
            <p class="state">Még nincs létrehozott esemény.</p>
        <?php else: ?>
            <section class="grid">
                <?php foreach ($eventsForView as $event): ?>
                    <article class="card" tabindex="0" role="button" data-event="<?= $event['payload'] ?>" aria-label="Részletek megnyitása: <?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?>">
                        <h2><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="category"><?= htmlspecialchars($event['category'], ENT_QUOTES, 'UTF-8') ?></p>
                        <ul class="meta">
                            <li><strong>Kezdés:</strong> <?= htmlspecialchars($event['formatted_time_start'], ENT_QUOTES, 'UTF-8') ?></li>
                            <li><strong>Vége:</strong> <?= htmlspecialchars($event['formatted_time_end'], ENT_QUOTES, 'UTF-8') ?></li>
                            <li><strong>Helyszín:</strong> <?= htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') ?></li>
                            <li><strong>Korhatár:</strong> <?= htmlspecialchars((string) $event['restriction'], ENT_QUOTES, 'UTF-8') ?>+</li>
                            <li><strong>Létszám:</strong> <?= htmlspecialchars((string) ($event['curr_letszam']), ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) ($event['max_letszam']), ENT_QUOTES, 'UTF-8') ?></li>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <div class="modal-overlay" id="eventModal" aria-hidden="true">
        <section class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
            <button type="button" class="modal-close" id="modalClose" aria-label="Bezárás">&times;</button>
            <h2 id="modalTitle">Esemény részletei</h2>
            <ul class="modal-meta" id="modalMeta"></ul>
            <form method="POST" class="modal-actions">
                <input type="hidden" name="action" value="toggle_attendance">
                <input type="hidden" name="event_id" id="modalEventId" value="">
                <button type="submit" class="attendance-btn" id="attendanceBtn">Jelentkezés</button>
            </form>
        </section>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('eventModal');
            const modalClose = document.getElementById('modalClose');
            const modalMeta = document.getElementById('modalMeta');
            const modalEventId = document.getElementById('modalEventId');
            const attendanceBtn = document.getElementById('attendanceBtn');
            const cards = document.querySelectorAll('.card[data-event]');

            if (!modal || !modalClose || !modalMeta || !modalEventId || !attendanceBtn || cards.length === 0) {
                return;
            }

            const escapeHtml = (value) => {
                const temp = document.createElement('span');
                temp.textContent = value ?? '';
                return temp.innerHTML;
            };

            const openModal = (payload) => {
                const data = payload || {};
                const eventId = Number.parseInt(data.id, 10);
                const isAttending = Boolean(data.is_attending);
                const isFull = Boolean(data.is_full);
                const rows = [
                    ['Név', data.name],
                    ['Kategória', data.category],
                    ['Leírás', data.description],
                    ['Kezdés', data.time_start],
                    ['Vége', data.time_end],
                    ['Város', (data.city || '').trim()],
                    ['Helyszín', (data.place || '').trim()],
                    ['Korhatár', data.restriction ? data.restriction + '+' : 'Nincs megadva'],
                    ['Létszám', `${data.curr_letszam || '0'}/${data.max_letszam || '0'}`]
                ];

                modalMeta.innerHTML = rows.map(([label, value]) => {
                    return `<li><strong>${escapeHtml(label)}:</strong> ${escapeHtml(value)}</li>`;
                }).join('');

                if (Number.isInteger(eventId) && eventId > 0) {
                    modalEventId.value = String(eventId);
                    attendanceBtn.disabled = false;
                } else {
                    modalEventId.value = '';
                    attendanceBtn.disabled = true;
                }

                if (isAttending) {
                    attendanceBtn.textContent = 'Jelentkezés lemondása';
                    attendanceBtn.classList.add('danger');
                } else {
                    attendanceBtn.textContent = 'Jelentkezés';
                    attendanceBtn.classList.remove('danger');
                }

                if (!isAttending && isFull) {
                    attendanceBtn.textContent = 'Esemény megtelt';
                    attendanceBtn.disabled = true;
                }

                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
            };

            const closeModal = () => {
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('modal-open');
            };

            cards.forEach((card) => {
                card.addEventListener('click', () => {
                    try {
                        openModal(JSON.parse(card.dataset.event || '{}'));
                    } catch (err) {
                        openModal({});
                    }
                });

                card.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        card.click();
                    }
                });
            });

            modalClose.addEventListener('click', closeModal);

            modal.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('open')) {
                    closeModal();
                }
            });
        })();
    </script>
</body>

</html>
