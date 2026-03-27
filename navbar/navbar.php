<?php
$activePage = $activePage ?? '';
?>
<header class="top-nav">
    <nav class="nav-inner" aria-label="Main navigation">
        <div class="nav-links">
            <a class="<?php echo $activePage === 'eventcreate' ? 'active' : ''; ?>" href="/PHP_projekt/EventCreate/index.php">EventCreate</a>
            <a class="<?php echo $activePage === 'eventsearch' ? 'active' : ''; ?>" href="/PHP_projekt/EventSeach/index.php">EventSearch</a>
        </div>
        <form method="POST" action="/PHP_projekt/navbar/logout.php" class="nav-logout">
            <button type="submit" class="logout-btn">Kijelentkezés</button>
        </form>
    </nav>
</header>
