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

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO esemenyek (name, category, time_start, time_end, restriction, description, place, city, max_letszam, curr_letszam, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssiis",
                $name,
                $category,
                $starttime,
                $endtime,
                $restriction,
                $description,
                $place,
                $city,
                $maxLetszam,
                $currLetszam,
                $createdBy
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            $notice = "Esemény meghírdetve";
            $noticeType = "success";
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
    <link rel="stylesheet" href="../navbar/navbar.css">
    <link rel="stylesheet" href="styles.css">
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
                <form method="POST" action="">
                    <label>Név</label>
                    <input type="text" name="name" placeholder="Név">

                    <label>Leírás</label>
                    <input type="text" name="description" placeholder="Leírás">

                    <label>Kategória</label>
                    <input type="text" name="category" placeholder="Kategória">

                    <label>Kezdés időpontja</label>
                    <input type="datetime-local" name="starttime" placeholder="Kezdés időpontja">

                    <label>Esemény vége</label>
                    <input type="datetime-local" name="endtime" placeholder="Esemény vége">

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


                    <button type="submit" name="userinfoSubmit">Esemény létrehozása</button>
                </form>
            </main>
        </div>
    </div>
</body>

</html>