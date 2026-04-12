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
include_once '../kulcsok.php';

$notice = '';
$noticeType = 'success';
$minDateTime = (new DateTime('now'))->format('Y-m-d\TH:i');
$maxEventImages = 10;

function processUploadedImages(?array $imagesInput, int $maxEventImages): array
{
    $selectedImageIndexes = [];

    if (is_array($imagesInput['name'] ?? null)) {
        foreach ($imagesInput['name'] as $index => $originalName) {
            if (trim((string) $originalName) !== '') {
                $selectedImageIndexes[] = $index;
            }
        }
    }

    if (count($selectedImageIndexes) > $maxEventImages) {
        return [
            'images' => [],
            'error' => 'Maximum 10 képet tölthetsz fel egy eseményhez.',
        ];
    }

    $allowedMimeToExtension = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($fileInfo === false) {
        return [
            'images' => [],
            'error' => 'A képek ellenőrzése nem sikerült.',
        ];
    }

    $validatedImages = [];

    foreach ($selectedImageIndexes as $index) {
        $errorCode = $imagesInput['error'][$index] ?? UPLOAD_ERR_NO_FILE;
        if ($errorCode !== UPLOAD_ERR_OK) {
            $validatedImages = [];
            break;
        }

        $tmpName = (string) ($imagesInput['tmp_name'][$index] ?? '');
        $mimeType = finfo_file($fileInfo, $tmpName);
        if (!isset($allowedMimeToExtension[$mimeType])) {
            $validatedImages = [];
            break;
        }

        $validatedImages[] = [
            'tmp_name' => $tmpName,
            'extension' => $allowedMimeToExtension[$mimeType],
        ];
    }

    finfo_close($fileInfo);

    if (count($selectedImageIndexes) !== count($validatedImages)) {
        return [
            'images' => [],
            'error' => 'Csak JPG vagy PNG képek tölthetők fel, és minden fájlnak hibamentesnek kell lennie.',
        ];
    }

    return [
        'images' => $validatedImages,
        'error' => null,
    ];
}

if (isset($_POST['userinfoSubmit'])) {
    try {
        $createdBy = $_SESSION['username'] ?? '';
        if ($createdBy === '') {
            $notice = 'Hiba';
            $noticeType = 'error';
        } else {
            $name = $_POST['name'];
            $description = $_POST['description'];
            $category = $_POST['category'];
            $starttime = $_POST['starttime'];
            $endtime = $_POST['endtime'];
            $city = $_POST['city'];
            $place = $_POST['place'];
            $restriction = $_POST['restriction'];
            $maxLetszam = (int) $_POST['max_letszam'];
            $currLetszam = 0;

            $startDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $starttime);
            $endDateTime = DateTime::createFromFormat('Y-m-d\TH:i', $endtime);
            $nowDateTime = new DateTime('now');

            if (!$startDateTime || !$endDateTime) {
                $notice = 'Érvénytelen dátum formátum';
                $noticeType = 'error';
            } elseif ($endDateTime <= $startDateTime) {
                $notice = 'A befejezés időpontjának a kezdés időpontja után kell lennie';
                $noticeType = 'error';
            } elseif ($startDateTime <= $nowDateTime || $endDateTime <= $nowDateTime) {
                $notice = 'A kezdési és befejezési időpontnak a jelenlegi idő után kell lennie';
                $noticeType = 'error';
            } else {
                $imageProcessing = processUploadedImages($_FILES['images'] ?? null, $maxEventImages);
                $validatedImages = $imageProcessing['images'];

                if ($imageProcessing['error'] !== null) {
                    $notice = $imageProcessing['error'];
                    $noticeType = 'error';
                } else {
                        mysqli_begin_transaction($conn);
                        $movedFiles = [];

                        try {
                            $stmt = mysqli_prepare(
                                $conn,
                                "INSERT INTO esemenyek (name, category, time_start, time_end, restriction, description, place, city, max_letszam, curr_letszam, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                            );
                            mysqli_stmt_bind_param($stmt, "ssssssssiis", $name, $category, $starttime, $endtime, $restriction, $description, $place, $city, $maxLetszam, $currLetszam, $createdBy);
                            mysqli_stmt_execute($stmt);
                            $eventId = mysqli_insert_id($conn);
                            mysqli_stmt_close($stmt);

                            if (!empty($validatedImages)) {
                                $uploadDir = '../uploads';

                                $stmtImage = mysqli_prepare($conn, "INSERT INTO event_images (image, event_id) VALUES (?, ?)");

                                foreach ($validatedImages as $index => $imageData) {
                                    $uniqueName = sprintf(
                                        'event_%d_%d_%s.%s',
                                        $eventId,
                                        $index + 1,
                                        bin2hex(random_bytes(8)),
                                        $imageData['extension']
                                    );
                                    $relativePath = 'uploads/' . $uniqueName;
                                    $targetPath = $uploadDir . '/' . $uniqueName;

                                    if (!move_uploaded_file($imageData['tmp_name'], $targetPath)) {
                                        throw new RuntimeException('A kép mentése sikertelen.');
                                    }

                                    $movedFiles[] = $targetPath;
                                    mysqli_stmt_bind_param($stmtImage, "si", $relativePath, $eventId);
                                    mysqli_stmt_execute($stmtImage);
                                }

                                mysqli_stmt_close($stmtImage);
                            }

                            mysqli_commit($conn);
                            $notice = 'Esemény meghirdetve';
                            $noticeType = 'success';
                        } catch (Throwable $uploadException) {
                            mysqli_rollback($conn);

                            foreach ($movedFiles as $savedFilePath) {
                                if (file_exists($savedFilePath)) {
                                    unlink($savedFilePath);
                                }
                            }

                            $notice = 'Hiba történt az esemény meghirdetése során';
                            $noticeType = 'error';
                        }
                }
            }
        }
    } catch (Exception $e) {
        $notice = "Hiba történt az esemény meghirdetése során";
        $noticeType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../navbar/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="styles.css?v=<?php echo time(); ?>">
</head>

<body>
    <?php
    $activePage = 'eventcreate';
    include_once '../navbar/navbar.php';
    ?>

    <div class="page-content">
        <div class="login-wrap">
            <main class="card">
                <h1>Esemény létrehozása</h1>
                <?php if ($notice !== ''): ?>
                    <div class="notice  <?php echo $noticeType === 'error' ? 'error' : 'success'; ?>">
                        <?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
                <form method="POST" action="" enctype="multipart/form-data">
                    <label>Név</label>
                    <input type="text" name="name" placeholder="Név">

                    <label>Leírás</label>
                    <textarea name="description" placeholder="Leírás" rows="6" maxlength="1000"></textarea>

                    <label>Kategória</label>
                    <input type="text" name="category" placeholder="Kategória">

                    <label>Kezdés időpontja</label>
                    <input type="datetime-local" name="starttime" placeholder="Kezdés időpontja"
                        min="<?php echo $minDateTime; ?>" required>

                    <label>Esemény vége</label>
                    <input type="datetime-local" name="endtime" placeholder="Esemény vége"
                        min="<?php echo $minDateTime; ?>" required>

                    <label>Város</label>
                    <input type="text" name="city" placeholder="Város">

                    <label>Helyszín</label>
                    <input type="text" name="place" placeholder="Helyszín">

                    <label>Korhatár</label>
                    <select name="restriction" id="restriction">
                        <option value="nincs">Nincs Korhatár</option>
                        <option value="6+">6+</option>
                        <option value="12+">12+</option>
                        <option value="16+">16+</option>
                        <option value="18+">18+</option>
                    </select>

                    <label>Maximális létszám</label>
                    <input type="number" name="max_letszam" min="1" placeholder="Maximális létszám" required>

                    <label>Képek (max 10)</label>
                    <input type="file" name="images[]" accept="image/*" multiple>


                    <button type="submit" name="userinfoSubmit">Esemény létrehozása</button>
                </form>
            </main>
        </div>
    </div>

    <script>
        (function () {
            // Auto-hide notices after 5 seconds
            const notices = document.querySelectorAll('.notice.success, .notice.error');
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