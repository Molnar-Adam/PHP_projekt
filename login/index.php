<?php
    session_start();
    ob_start();

    include_once '../database.php';
    include_once '../kulcsok.php';

    $error = '';

    if(isset($_POST['userinfoSubmit'])) {
        $username = $_POST['username'];
        $password = $_POST['pass'];

        $hashedPassword = hash_hmac('sha256', $password, $key);

        $stmt = mysqli_prepare($conn, "SELECT id, username, fullname, password FROM users WHERE username=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $userinfo = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);



        if($userinfo) {
            if($hashedPassword == $userinfo['password']) {
                $_SESSION["username"] = $username;
                $_SESSION["fullname"] = $userinfo['fullname'];
                $_SESSION["valid"] = true;
                header("Location: ../EventCreate/index.php");
                exit;
            } else {
                $error = 'Hibás felhasználónév / jelszó.';
            }
        } else {
            $error = 'Hibás felhasználónév / jelszó.';
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="login-wrap">

        <main class="card">
            <h1>Bejelentkezés</h1>
                    <?php if ($error !== ''): ?>
            <div class="notice error">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
            <form method="POST" action="">
                <label for="username">Felhasználónév</label>
                <input id="username" type="text" name="username" autocomplete="username" required>

                <label for="password">Jelszó</label>
                <input id="password" type="password" name="pass" autocomplete="current-password" required>

                <button type="submit" name="userinfoSubmit">Bejelentkezés</button>
                <a class="button-secondary" href="../register/index.php">Regisztráció</a>
            </form>
        </main>
    </div>
</body>
</html>