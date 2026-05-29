<?php
session_start();

// Include the database connection
require_once "../config/dataBase.php";

// Variable used to display an error message
$errorMessage = "";

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get form values
    $email = $_POST["email"];
    $password = $_POST["password"];

    // Search for the user using the email
    $request = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $request->execute([$email]);

    $user = $request->fetch();

    // Verify if the user exists and if the password is correct
    if ($user && password_verify($password, $user["password"])) {

        // Save user information in the session
        $_SESSION["id"] = $user["id"];
        $_SESSION["name"] = $user["name"];

        // Redirect to the home page
        header("Location: ../index.php");
        exit();
    } else {
        // Error message if login failed
        $errorMessage = "Incorrect email or password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Log In</title>

    <link rel="stylesheet" href="../style/headerStyle.css">
    <link rel="stylesheet" href="../style/authStyle.css">
</head>

<body>

    <?php require_once "../header.php"; ?>

    <main class="authContainer">

        <form class="authCard" method="POST">

            <h1>Log In</h1>

            <p class="errorMessage">
                <?php echo $errorMessage; ?>
            </p>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <button type="submit">Log In</button>

            <p class="smallText">
                Don't have an account ?
                <a href="signUp.php">Register here</a>
            </p>

        </form>

    </main>

</body>

</html>