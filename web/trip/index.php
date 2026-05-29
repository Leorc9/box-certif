<?php
require_once("../header.php");
require_once("../config/database.php");
require_once("../functions/loginCheck.php");

checkLogin();

$userId = $_SESSION["id"];

// Create a new trip
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $tripName = trim($_POST["name"]);
    if (!empty($tripName)) {
        $stmtInsert = $pdo->prepare("INSERT INTO trip (name, user_id) VALUES (?, ?)");
        $stmtInsert->execute([$tripName, $userId]);
    }
    header("Location: index.php");
    exit();
}

// Delete a trip
if (isset($_GET["action"]) && $_GET["action"] === "delete" && isset($_GET["id"])) {
    $tripId = (int) $_GET["id"];
    $stmtDelete = $pdo->prepare("DELETE FROM trip WHERE id = ? AND user_id = ?");
    $stmtDelete->execute([$tripId, $userId]);
    header("Location: index.php");
    exit();
}

// Fetch all trips for the current user
$stmtTrips = $pdo->prepare("SELECT * FROM trip WHERE user_id = ? ORDER BY id DESC");
$stmtTrips->execute([$userId]);
$trips = $stmtTrips->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Créer un trip</h4>
        </div>

        <div class="card-body">
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="name" class="form-label">Titre du trip</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    Créer le trip
                </button>
            </form>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Liste des trips</h5>
        </div>

        <div class="card-body">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nom</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trips)): ?>
                        <tr>
                            <td colspan="3" class="text-center text-muted">Aucun trip pour le moment.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($trips as $trip): ?>
                            <tr>
                                <td><?= $trip["id"] ?></td>
                                <td><?= htmlspecialchars($trip["name"]) ?></td>
                                <td class="text-end">
                                    <a href="editTrip.php?id=<?= $trip["id"] ?>" class="btn btn-sm btn-primary">
                                        Modifier
                                    </a>
                                    <a href="index.php?action=delete&id=<?= $trip["id"] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Supprimer ce trip ?')">
                                        Supprimer
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>