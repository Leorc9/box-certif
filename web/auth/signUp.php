<?php
// Start the session
session_start();

// Include the database connection
require_once "../config/dataBase.php";

// Create an empty error message variable
$errorMessage = "";

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get the form values
    $name = $_POST["name"];
    $familyname = $_POST["familyname"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $confirmPassword = $_POST["confirmPassword"];

    // Check if the password and confirm password are different
    if ($password !== $confirmPassword) {

        // Display an error message if passwords do not match
        $errorMessage = "Passwords do not match.";
    } else {

        // Hash the password before saving it in the database
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {

            // Prepare the SQL request to insert a new user
            $request = $pdo->prepare("
                INSERT INTO users(name, familyname, email, password)
                VALUES(?, ?, ?, ?)
            ");

            // Execute the request with the form values
            $request->execute([
                $name,
                $familyname,
                $email,
                $hashedPassword
            ]);

            // Redirect the user to the login page
            header("Location: logIn.php");
            exit();
        } catch (Exception $error) {

            // Display an error message if the email already exists
            $errorMessage = "Email already exists.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sign Up</title>

    <link rel="stylesheet" href="../style/headerStyle.css">
    <link rel="stylesheet" href="../style/authStyle.css">
</head>

<body>

    <?php require_once "../header.php"; ?>

    <main class="authContainer">

        <form class="authCard signUpCard" method="POST">

            <h1>Sign Up</h1>

            <!-- Display error message -->
            <p class="errorMessage">
                <?php echo $errorMessage; ?>
            </p>

            <div class="nameRow">

                <div>
                    <label>First Name</label>
                    <input type="text" name="name" required>
                </div>

                <div>
                    <label>Last Name</label>
                    <input type="text" name="familyname" required>
                </div>

            </div>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Confirm Password</label>
            <input type="password" name="confirmPassword" required>

            <button type="submit">Sign Up</button>

        </form>

    </main>

</body>

</html>