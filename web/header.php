<?php
// Start the session only if it is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link rel="stylesheet" href="/style/headerStyle.css">

<header class="mainHeader">
    <nav class="mainNav">
        <!-- Main navigation links -->
        <a href="/index.php">Home</a>
        <a href="#">Trip</a>
        <a href="#">History</a>

        <!-- If the user is connected, show Log Out, else show Log In -->
        <?php if (isset($_SESSION["id"])) { ?>
            <a class="authButton" href="/auth/logOut.php">Log Out</a>
        <?php } else { ?>
            <a class="authButton" href="/auth/logIn.php">Log In</a>
        <?php } ?>
    </nav>
</header>