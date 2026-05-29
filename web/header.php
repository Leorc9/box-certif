<?php
// Start the session only if it is not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link rel="stylesheet" href="/style/headerStyle.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<link href="/assets/css/style.css" rel="stylesheet">
<link rel="stylesheet" href="/style/mainStyle.css">

<header class="mainHeader">
    <nav class="mainNav">
        <!-- Main navigation links -->
        <a href="/index.php">Home</a>
        <a href="/trip/index.php">Trip</a>
        <a href="#">History</a>

        <!-- If the user is connected, show Log Out, else show Log In -->
        <?php if (isset($_SESSION["id"])) { ?>
            <a class="authButton" href="/auth/logOut.php">Log Out</a>
        <?php } else { ?>
            <a class="authButton" href="/auth/logIn.php">Log In</a>
        <?php } ?>
    </nav>
</header>

<body>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>