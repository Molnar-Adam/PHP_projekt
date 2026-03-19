<?php
    include_once '../database.php';
    include_once '../kulcsok.php';

    $notice = '';
    $noticeType = 'success';

    if(isset($_POST['userinfoSubmit'])) {
        try{
            $username = $_POST['username'];
            $password = $_POST['pass'];
            $fullname = $_POST['fullname'];
            $email = $_POST['email'];
            $birthdate = $_POST['birthdate'];

            $hashedData = hash_hmac('sha256', $password, $key);
            
            $stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE username=? OR email=?");
            mysqli_stmt_bind_param($stmt, "ss", $username, $email);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $userinfo = mysqli_fetch_all($result);
            mysqli_stmt_close($stmt);

            if(count($userinfo) >= 1){
                $notice = "Létezik már ilyen felhasználó! (felhasználónév vagy email foglalt)";
                $noticeType = 'error';
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO users(username, password, email, fullname, birthdate) VALUES (?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt, "sssss", $username, $hashedData, $email, $fullname, $birthdate);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);

                $notice = "Sikeres regisztráció!";
                $noticeType = 'success';
            }
        }
        catch (Exception $e){
            $notice = "Hiba történt a regisztráció során.";
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
            <h1>Regisztració</h1>
            <?php if ($notice !== ''): ?>
                <div class="notice  <?php echo $noticeType === 'error' ? 'error' : 'success'; ?>">
                    <?php echo htmlspecialchars($notice, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>
            <form method="POST" action="">
                <label for="username">Felhasználonév</label>
                <input id="username" type="text" name="username" placeholder="Felhasználonév">

                <label for="password">Jelszó</label>
                <input id="password" type="password" name="pass" placeholder="Jelszó">

                <label for="fullname">Teljes név</label>
                <input id="fullname" type="text" name="fullname" placeholder="Teljes név">

                <label for="email">Email</label>
                <input id="email" type="email" name="email" placeholder="Email">

                <label for="birthdate">Születési dátum</label>
                <input id="birthdate" type="date" name="birthdate" placeholder="Születési dátum">

                <button type="submit" name="userinfoSubmit">Regisztráció</button>
                <a class="button-secondary" href="../login/index.php">Vissza a bejelentkezéshez</a>
            </form>
        </main>
    </div>
</body>
</html>