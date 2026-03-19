<?php

    if(isset($_SESSION['username']) && isset($_SESSION['fullname'])){
        echo "Felhasználó be van lépve!";
        echo '<br>';
        echo $_SESSION['username'];
        echo '<br>';
        echo $_SESSION['fullname'];
        echo '<br>';
        print_r($_SESSION);
    }
    else {
        echo "Felhasználó nincs belépve!";
    }
?>