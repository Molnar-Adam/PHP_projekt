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
$eventImagesByEventId = [];
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
                    $actionNotice = 'Sikeres lejelentkezés az eseményről!';
                    $actionNoticeType = 'success';
                } else {
                    if ($hasLimit && $currLetszam >= $maxLetszam) {
                        $actionNotice = 'Az esemeny megtelt, nem lehet jelentkezni.';
                        $actionNoticeType = 'error';
                    } else {
                        $attendingUsers[] = $currentUsername;
                        $currLetszam++;
                        $actionNotice = 'Sikeres jelentkezés az eseményre!';
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
            ORDER BY time_start ASC, id ASC";
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

try {
    $imagesSql = 'SELECT event_id, image FROM event_images ORDER BY id ASC';
    $imagesResult = mysqli_query($conn, $imagesSql);
    if ($imagesResult) {
        while ($row = mysqli_fetch_assoc($imagesResult)) {
            $eventId = (int) ($row['event_id'] ?? 0);
            $imagePath = trim((string) ($row['image'] ?? ''));
            if ($eventId <= 0 || $imagePath === '') {
                continue;
            }

            if (!isset($eventImagesByEventId[$eventId])) {
                $eventImagesByEventId[$eventId] = [];
            }

            if (count($eventImagesByEventId[$eventId]) < 10) {
                $eventImagesByEventId[$eventId][] = $imagePath;
            }
        }
        mysqli_free_result($imagesResult);
    }
} catch (Exception $e) {
}

function formatEventDate($dateValue)
{
    if (!$dateValue) {
        return 'Nincs megadva';
    }
    $timestamp = strtotime($dateValue);
    return $timestamp ? date('Y.m.d H:i', $timestamp) : 'Nincs megadva';
}

function toPublicImagePath($imagePath)
{
    $cleanedPath = trim((string) $imagePath);
    if ($cleanedPath === '') {
        return '';
    }

    if (preg_match('/^(https?:)?\/\//i', $cleanedPath)) {
        return $cleanedPath;
    }

    if (str_starts_with($cleanedPath, '/')) {
        return $cleanedPath;
    }

    if (str_starts_with($cleanedPath, 'uploads/')) {
        return '../' . $cleanedPath;
    }

    if (str_starts_with($cleanedPath, 'uploads')) {
        return '../uploads/' . ltrim(substr($cleanedPath, 7), '/\\');
    }

    return '../uploads/' . ltrim($cleanedPath, '/\\');
}

foreach ($events as $event) {
    $eventId = (int) ($event['id'] ?? 0);
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
    $eventImagesRaw = $eventImagesByEventId[$eventId] ?? [];
    $eventImages = [];
    foreach ($eventImagesRaw as $imagePath) {
        $publicPath = toPublicImagePath($imagePath);
        if ($publicPath !== '') {
            $eventImages[] = $publicPath;
        }
    }

    $payload = json_encode([
        'id' => $eventId,
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
        'images' => $eventImages,
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
    <link rel="stylesheet" href="../navbar/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
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
            <div class="modal-content-wrapper">
                <h2 id="modalTitle">Esemény részletei</h2>
                <ul class="modal-meta" id="modalMeta"></ul>
                <div class="modal-gallery" id="modalGallery" aria-label="Esemény képek"></div>
                <form method="POST" class="modal-actions">
                    <input type="hidden" name="action" value="toggle_attendance">
                    <input type="hidden" name="event_id" id="modalEventId" value="">
                    <button type="submit" class="attendance-btn" id="attendanceBtn">Jelentkezés</button>
                </form>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('eventModal');
            const modalClose = document.getElementById('modalClose');
            const modalMeta = document.getElementById('modalMeta');
            const modalGallery = document.getElementById('modalGallery');
            const modalEventId = document.getElementById('modalEventId');
            const attendanceBtn = document.getElementById('attendanceBtn');
            const cards = document.querySelectorAll('.card[data-event]');

            if (!modal || !modalClose || !modalMeta || !modalGallery || !modalEventId || !attendanceBtn || cards.length === 0) {
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
                    { label: 'Név', value: data.name },
                    { label: 'Kategória', value: data.category },
                    { label: 'Leírás', value: data.description, multiline: true },
                    { label: 'Kezdés', value: data.time_start },
                    { label: 'Vége', value: data.time_end },
                    { label: 'Város', value: (data.city || '').trim() },
                    { label: 'Helyszín', value: (data.place || '').trim() },
                    { label: 'Korhatár', value: data.restriction ? data.restriction + '+' : 'Nincs megadva' },
                    { label: 'Létszám', value: `${data.curr_letszam || '0'}/${data.max_letszam || '0'}` }
                ];

                modalMeta.innerHTML = rows.map((row) => {
                    const value = row.value === undefined || row.value === null || String(row.value).trim() === ''
                        ? 'Nincs megadva'
                        : String(row.value);
                    const valueClass = row.multiline ? 'modal-meta-value multiline' : 'modal-meta-value';
                    return `<li><strong>${escapeHtml(row.label)}:</strong><span class="${valueClass}">${escapeHtml(value)}</span></li>`;
                }).join('');

                const images = Array.isArray(data.images) ? data.images.slice(0, 10) : [];
                if (images.length === 0) {
                    modalGallery.innerHTML = '<p class="gallery-empty">Ehhez az eseményhez nincs feltöltött kép.</p>';
                } else {
                    modalGallery.innerHTML = images.map((imageSrc, index) => {
                        const safeSrc = escapeHtml(imageSrc);
                        const altText = `Esemény kép ${index + 1}`;
                        return `<button type="button" class="gallery-thumb" data-src="${safeSrc}" aria-label="${escapeHtml(altText)} teljes képernyős megnyitása">
                                    <img src="${safeSrc}" alt="${escapeHtml(altText)}" loading="lazy" decoding="async">
                                </button>`;
                    }).join('');
                }

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

            const openImageFullscreen = (imageElement) => {
                const imageSrc = imageElement ? (imageElement.getAttribute('src') || '') : '';
                if (!imageSrc) {
                    return;
                }

                const openInNewTab = () => {
                    window.open(imageSrc, '_blank', 'noopener,noreferrer');
                };

                if (imageElement && typeof imageElement.requestFullscreen === 'function') {
                    imageElement.requestFullscreen().then(() => {
                        const fullscreenTarget = document.fullscreenElement;
                        if (!fullscreenTarget) {
                            return;
                        }

                        const closeOnClick = () => {
                            if (document.fullscreenElement) {
                                document.exitFullscreen().catch(() => {});
                            }
                        };

                        fullscreenTarget.addEventListener('click', closeOnClick, { once: true });
                    }).catch(openInNewTab);
                    return;
                }

                openInNewTab();
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

            modalGallery.addEventListener('click', (event) => {
                const thumbButton = event.target.closest('.gallery-thumb');
                if (!thumbButton) {
                    return;
                }

                const fullSrc = thumbButton.getAttribute('data-src') || '';
                const img = thumbButton.querySelector('img');
                if (fullSrc && img) {
                    openImageFullscreen(img);
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal.classList.contains('open')) {
                    closeModal();
                }
            });

            const notices = document.querySelectorAll('.state.success, .state.error');
            notices.forEach(notice => {
                setTimeout(() => {
                    notice.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    notice.style.opacity = '0';
                    notice.style.transform = 'translateY(-10px)';
                    setTimeout(() => notice.remove(), 500);
                }, 5000);
            });
        })();
    </script>
</body>

</html>
