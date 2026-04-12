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

function formatEventDate(?string $dateValue): string
{
    if (!$dateValue) {
        return 'Nincs megadva';
    }

    $timestamp = strtotime($dateValue);
    return $timestamp ? date('Y.m.d H:i', $timestamp) : 'Nincs megadva';
}

function formatEventDateInput(?string $dateValue): string
{
    if (!$dateValue) {
        return '';
    }

    $timestamp = strtotime($dateValue);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
}

function formatRestrictionLabel($restrictionValue): string
{
    $restriction = (int) $restrictionValue;

    return $restriction > 0 ? $restriction . '+' : 'Nincs korhatár';
}

function normalizeRestrictionValue($restrictionValue): int
{
    if (is_int($restrictionValue)) {
        return max(0, $restrictionValue);
    }

    $value = trim((string) $restrictionValue);
    if ($value === '' || strcasecmp($value, 'nincs') === 0) {
        return 0;
    }

    return max(0, (int) preg_replace('/[^0-9]/', '', $value));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'update_event')) {
    $eventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    $name = trim((string) ($_POST['name'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $starttime = trim((string) ($_POST['starttime'] ?? ''));
    $endtime = trim((string) ($_POST['endtime'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $place = trim((string) ($_POST['place'] ?? ''));
    $restriction = normalizeRestrictionValue($_POST['restriction'] ?? 0);
    $maxLetszamInput = filter_input(INPUT_POST, 'max_letszam', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if (!$eventId) {
        $actionNotice = 'Érvénytelen eseményazonosító.';
        $actionNoticeType = 'error';
    } elseif ($maxLetszamInput === false || $maxLetszamInput === null) {
        $actionNotice = 'A maximális létszám nem érvényes.';
        $actionNoticeType = 'error';
    } else {
        $startDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $starttime);
        $endDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $endtime);
        $nowDateTime = new DateTime('now');

        if (!$startDateTime || !$endDateTime) {
            $actionNotice = 'Érvénytelen dátum formátum.';
            $actionNoticeType = 'error';
        } elseif ($endDateTime <= $startDateTime) {
            $actionNotice = 'A befejezés időpontjának a kezdés után kell lennie.';
            $actionNoticeType = 'error';
        } elseif ($startDateTime <= $nowDateTime || $endDateTime <= $nowDateTime) {
            $actionNotice = 'A kezdési és befejezési időpontnak a jelenlegi idő után kell lennie.';
            $actionNoticeType = 'error';
        } else {
            try {
                $stmtSelect = mysqli_prepare(
                    $conn,
                    'SELECT created_by, curr_letszam FROM esemenyek WHERE id = ? LIMIT 1'
                );
                mysqli_stmt_bind_param($stmtSelect, 'i', $eventId);
                mysqli_stmt_execute($stmtSelect);
                $result = mysqli_stmt_get_result($stmtSelect);
                $eventRow = $result ? mysqli_fetch_assoc($result) : null;
                if ($result) {
                    mysqli_free_result($result);
                }
                mysqli_stmt_close($stmtSelect);

                if (!$eventRow) {
                    $actionNotice = 'A kiválasztott esemény nem található.';
                    $actionNoticeType = 'error';
                } elseif ((string) ($eventRow['created_by'] ?? '') !== $currentUsername) {
                    $actionNotice = 'Csak a saját eseményeidet szerkesztheted.';
                    $actionNoticeType = 'error';
                } else {
                    $currLetszam = (int) ($eventRow['curr_letszam'] ?? 0);
                    if ($maxLetszamInput < $currLetszam) {
                        $actionNotice = 'A maximális létszám nem lehet kisebb a jelenlegi létszámnál.';
                        $actionNoticeType = 'error';
                    } else {
                        $stmtUpdate = mysqli_prepare(
                            $conn,
                            'UPDATE esemenyek SET name = ?, category = ?, time_start = ?, time_end = ?, restriction = ?, description = ?, place = ?, city = ?, max_letszam = ? WHERE id = ? AND created_by = ? LIMIT 1'
                        );
                        mysqli_stmt_bind_param(
                            $stmtUpdate,
                            'ssssisssiis',
                            $name,
                            $category,
                            $starttime,
                            $endtime,
                            $restriction,
                            $description,
                            $place,
                            $city,
                            $maxLetszamInput,
                            $eventId,
                            $currentUsername
                        );
                        $updateOk = mysqli_stmt_execute($stmtUpdate);
                        mysqli_stmt_close($stmtUpdate);

                        if ($updateOk) {
                            $actionNotice = 'Sikeres mentés.';
                            $actionNoticeType = 'success';
                        } else {
                            $actionNotice = 'Nem sikerült frissíteni az eseményt.';
                            $actionNoticeType = 'error';
                        }
                    }
                }
            } catch (Throwable $e) {
                $actionNotice = 'Hiba történt az esemény módosítása során.';
                $actionNoticeType = 'error';
            }
        }
    }
}

try {
    $stmtEvents = mysqli_prepare(
        $conn,
        'SELECT id, name, category, time_start, time_end, restriction, description, place, city, curr_letszam, max_letszam
         FROM esemenyek
         WHERE created_by = ?
         ORDER BY time_start DESC, id DESC'
    );
    mysqli_stmt_bind_param($stmtEvents, 's', $currentUsername);
    mysqli_stmt_execute($stmtEvents);
    $result = mysqli_stmt_get_result($stmtEvents);
    if ($result) {
        $events = mysqli_fetch_all($result, MYSQLI_ASSOC);
        mysqli_free_result($result);
    } else {
        $loadError = 'Nem sikerült betölteni a saját eseményeket.';
    }
    mysqli_stmt_close($stmtEvents);
} catch (Throwable $e) {
    $loadError = 'Hiba történt az események betöltése során.';
}

foreach ($events as $event) {
    $formattedStart = formatEventDate($event['time_start'] ?? null);
    $formattedEnd = formatEventDate($event['time_end'] ?? null);
    $location = trim((string) (($event['city'] ?? '') . ', ' . ($event['place'] ?? '')), ' ,');

    $payload = json_encode([
        'id' => (int) ($event['id'] ?? 0),
        'name' => (string) ($event['name'] ?? ''),
        'category' => (string) ($event['category'] ?? ''),
        'description' => (string) ($event['description'] ?? ''),
        'time_start' => formatEventDateInput($event['time_start'] ?? null),
        'time_end' => formatEventDateInput($event['time_end'] ?? null),
        'city' => (string) ($event['city'] ?? ''),
        'place' => (string) ($event['place'] ?? ''),
        'restriction' => (int) ($event['restriction'] ?? 0),
        'curr_letszam' => (int) ($event['curr_letszam'] ?? 0),
        'max_letszam' => (int) ($event['max_letszam'] ?? 0),
    ], JSON_UNESCAPED_UNICODE);

    $eventsForView[] = [
        'name' => (string) ($event['name'] ?? ''),
        'category' => (string) ($event['category'] ?? ''),
        'formatted_time_start' => $formattedStart,
        'formatted_time_end' => $formattedEnd,
        'location' => $location,
        'restriction_label' => formatRestrictionLabel($event['restriction'] ?? 0),
        'curr_letszam' => (string) ($event['curr_letszam'] ?? '0'),
        'max_letszam' => (string) ($event['max_letszam'] ?? '0'),
        'payload' => htmlspecialchars($payload ?: '{}', ENT_QUOTES, 'UTF-8'),
    ];
}
?>

<!DOCTYPE html>
<html lang="hu">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../navbar/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
    <title>MyEvents</title>
</head>

<body>
    <?php
    $activePage = 'myevents';
    include_once '../navbar/navbar.php';
    ?>

    <main class="page-shell">
        <h1>Saját események</h1>

        <?php if ($actionNotice !== ''): ?>
            <p class="state <?= $actionNoticeType === 'error' ? 'error' : 'success' ?>"><?= htmlspecialchars($actionNotice, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if ($loadError !== ''): ?>
            <p class="state error"><?= htmlspecialchars($loadError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php elseif (count($eventsForView) === 0): ?>
            <p class="state">Még nincs saját eseményed.</p>
        <?php else: ?>
            <section class="grid">
                <?php foreach ($eventsForView as $event): ?>
                    <article class="card" tabindex="0" role="button" data-event="<?= $event['payload'] ?>" aria-label="Szerkesztés megnyitása: <?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?>">
                        <h2><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="category"><?= htmlspecialchars($event['category'], ENT_QUOTES, 'UTF-8') ?></p>
                        <ul class="meta">
                            <li><strong>Kezdés:</strong> <?= htmlspecialchars($event['formatted_time_start'], ENT_QUOTES, 'UTF-8') ?></li>
                            <li><strong>Vége:</strong> <?= htmlspecialchars($event['formatted_time_end'], ENT_QUOTES, 'UTF-8') ?></li>
                            <li><strong>Helyszín:</strong> <?= htmlspecialchars($event['location'], ENT_QUOTES, 'UTF-8') ?></li>
                            <li><strong>Korhatár:</strong> <?= htmlspecialchars($event['restriction_label'], ENT_QUOTES, 'UTF-8') ?></li>
                            <li><strong>Létszám:</strong> <?= htmlspecialchars((string) $event['curr_letszam'], ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) $event['max_letszam'], ENT_QUOTES, 'UTF-8') ?></li>
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
                <h2 id="modalTitle">Esemény szerkesztése</h2>
                <form method="POST" class="modal-form" id="editEventForm">
                    <input type="hidden" name="action" value="update_event">
                    <input type="hidden" name="event_id" id="modalEventId" value="">

                    <div class="modal-grid">
                        <div class="field field-full">
                            <label for="modalName">Név</label>
                            <input type="text" name="name" id="modalName" required>
                        </div>

                        <div class="field field-full">
                            <label for="modalCategory">Kategória</label>
                            <input type="text" name="category" id="modalCategory" required>
                        </div>

                        <div class="field field-full">
                            <label for="modalDescription">Leírás</label>
                            <textarea name="description" id="modalDescription" rows="4"></textarea>
                        </div>

                        <div class="field">
                            <label for="modalStarttime">Kezdés időpontja</label>
                            <input type="datetime-local" name="starttime" id="modalStarttime" required>
                        </div>

                        <div class="field">
                            <label for="modalEndtime">Esemény vége</label>
                            <input type="datetime-local" name="endtime" id="modalEndtime" required>
                        </div>

                        <div class="field">
                            <label for="modalCity">Város</label>
                            <input type="text" name="city" id="modalCity">
                        </div>

                        <div class="field">
                            <label for="modalPlace">Helyszín</label>
                            <input type="text" name="place" id="modalPlace">
                        </div>

                        <div class="field">
                            <label for="modalRestriction">Korhatár</label>
                            <select name="restriction" id="modalRestriction">
                                <option value="0">Nincs korhatár</option>
                                <option value="6">6+</option>
                                <option value="12">12+</option>
                                <option value="16">16+</option>
                                <option value="18">18+</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="modalMaxLetszam">Maximális létszám</label>
                            <input type="number" name="max_letszam" id="modalMaxLetszam" min="1" required>
                        </div>
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="secondary-btn" id="modalCloseSecondary">Mégse</button>
                        <button type="submit" class="save-btn">Mentés</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('eventModal');
            const modalClose = document.getElementById('modalClose');
            const modalCloseSecondary = document.getElementById('modalCloseSecondary');
            const modalEventId = document.getElementById('modalEventId');
            const modalName = document.getElementById('modalName');
            const modalCategory = document.getElementById('modalCategory');
            const modalDescription = document.getElementById('modalDescription');
            const modalStarttime = document.getElementById('modalStarttime');
            const modalEndtime = document.getElementById('modalEndtime');
            const modalCity = document.getElementById('modalCity');
            const modalPlace = document.getElementById('modalPlace');
            const modalRestriction = document.getElementById('modalRestriction');
            const modalMaxLetszam = document.getElementById('modalMaxLetszam');
            const editForm = document.getElementById('editEventForm');
            const notices = document.querySelectorAll('.state.success, .state.error');
            const cards = document.querySelectorAll('.card[data-event]');

            if (!modal || !modalClose || !modalCloseSecondary || !modalEventId || !modalName || !modalCategory || !modalDescription || !modalStarttime || !modalEndtime || !modalCity || !modalPlace || !modalRestriction || !modalMaxLetszam || !editForm) {
                return;
            }

            notices.forEach((notice) => {
                setTimeout(() => {
                    notice.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                    notice.style.opacity = '0';
                    notice.style.transform = 'translateY(-10px)';
                    setTimeout(() => notice.remove(), 500);
                }, 5000);
            });

            if (cards.length === 0) {
                return;
            }

            const openModal = (payload) => {
                const data = payload || {};
                const eventId = Number.parseInt(data.id, 10);
                const currLetszam = Number.parseInt(data.curr_letszam, 10) || 0;
                const maxLetszam = Number.parseInt(data.max_letszam, 10) || 0;

                if (Number.isInteger(eventId) && eventId > 0) {
                    modalEventId.value = String(eventId);
                    modalName.value = data.name || '';
                    modalCategory.value = data.category || '';
                    modalDescription.value = data.description || '';
                    modalStarttime.value = data.time_start || '';
                    modalEndtime.value = data.time_end || '';
                    modalCity.value = data.city || '';
                    modalPlace.value = data.place || '';
                    modalRestriction.value = String(Number.parseInt(data.restriction, 10) || 0);
                    modalMaxLetszam.value = String(maxLetszam || 1);
                    modalMaxLetszam.min = String(currLetszam || 1);
                } else {
                    modalEventId.value = '';
                    editForm.reset();
                }

                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                window.requestAnimationFrame(() => modalName.focus());
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
            modalCloseSecondary.addEventListener('click', closeModal);

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
