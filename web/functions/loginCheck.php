<?php
function checkLogin()
{
    if (!$_SESSION['id']) {
        header('Location: ../needLogin.php');
    }
}
