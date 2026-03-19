<?php
session_start();

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

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO esemenyek (name, category, time_start, time_end,restriction, description, place, city, created_by)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                "sssssssss",
                $name,
                $category,
                $starttime,
                $endtime,
                $restriction,
                $description,
                $place,
                $city,
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
    <link rel="stylesheet" href="styles.css">
</head>

<body>
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
                <input type="text" name="restriction" placeholder="Korhatár">


                <button type="submit" name="userinfoSubmit">Esemény létrehozása</button>
            </form>
        </main>
    </div>
</body>

</html>