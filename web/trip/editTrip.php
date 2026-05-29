<?php
require_once("../header.php");
require_once("../config/database.php");

if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION["id"];
$tripId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$erreur = null;

// Check if the trip exists and belongs to the user
$stmt = $pdo->prepare("SELECT * FROM trip WHERE id = ? AND user_id = ?");
$stmt->execute([$tripId, $userId]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    header("Location: index.php");
    exit();
}

// Remove a place from the trip
if (isset($_GET["action"]) && $_GET["action"] === "remove" && isset($_GET["place_id"])) {
    $placeId = (int) $_GET["place_id"];
    $stmt = $pdo->prepare("DELETE FROM trip_places WHERE trip_id = ? AND place_id = ?");
    $stmt->execute([$tripId, $placeId]);
}

// Add a new place to the trip
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nomVille = trim($_POST["nom"]);

    if (!empty($nomVille)) {
        // Call GeoNames API to get coordinates
        $apiUrl = "http://api.geonames.org/searchJSON?q=" . urlencode($nomVille) . "&maxRows=1&featureClass=P&username=leoleo09090";

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $apiResponse = curl_exec($ch);
        $geoData = json_decode($apiResponse, true);

        if (empty($geoData["geonames"])) {
            $erreur = "Ville introuvable.";
        } else {
            $villeData   = $geoData["geonames"][0];
            $latitude    = (float) $villeData["lat"];
            $longitude   = (float) $villeData["lng"];
            $nomOfficiel = $villeData["name"];

            // Check if the city is already in this trip
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) FROM places p
                JOIN trip_places tp ON tp.place_id = p.id
                WHERE tp.trip_id = ? AND LOWER(p.nom) = LOWER(?)
            ");
            $stmtCheck->execute([$tripId, $nomOfficiel]);
            $existe = $stmtCheck->fetchColumn();

            if ($existe > 0) {
                $erreur = "\"$nomOfficiel\" est déjà dans ce trip.";
            } else {
                // Insert the place
                $stmtPlace = $pdo->prepare("INSERT INTO places (user_id, nom, latitude, longitude) VALUES (?, ?, ?, ?)");
                $stmtPlace->execute([$userId, $nomOfficiel, $latitude, $longitude]);
                $placeId = $pdo->lastInsertId();

                // Get the next visit order
                $stmtOrder = $pdo->prepare("SELECT COALESCE(MAX(visit_order), 0) + 1 FROM trip_places WHERE trip_id = ?");
                $stmtOrder->execute([$tripId]);
                $nextOrder = $stmtOrder->fetchColumn();

                // Link the place to the trip
                $stmtLink = $pdo->prepare("INSERT INTO trip_places (trip_id, place_id, visit_order) VALUES (?, ?, ?)");
                $stmtLink->execute([$tripId, $placeId, $nextOrder]);
            }
        }
    }
}

// Fetch all places for this trip
$stmtPlaces = $pdo->prepare("
    SELECT p.*, tp.visit_order
    FROM places p
    JOIN trip_places tp ON tp.place_id = p.id
    WHERE tp.trip_id = ?
    ORDER BY tp.visit_order ASC
");
$stmtPlaces->execute([$tripId]);
$places = $stmtPlaces->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Modifier le trip : <?= htmlspecialchars($trip["name"]) ?></h4>
            <a href="index.php" class="btn btn-sm btn-light">← Retour</a>
        </div>

        <div class="card-body">
            <form method="POST" action="?id=<?= $tripId ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-md-10">
                        <label class="form-label">Nom de la ville</label>
                        <input type="text" class="form-control" name="nom" placeholder="Ex: Paris" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Ajouter</button>
                    </div>
                </div>
                <?php if (!empty($erreur)): ?>
                    <div class="alert alert-danger mt-3 mb-0"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Étapes du trip</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Ordre</th>
                        <th>Ville</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($places)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">Aucune étape pour le moment.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($places as $place): ?>
                            <tr>
                                <td><?= $place["visit_order"] ?></td>
                                <td><?= htmlspecialchars($place["nom"]) ?></td>
                                <td><?= $place["latitude"] ?></td>
                                <td><?= $place["longitude"] ?></td>
                                <td class="text-end">
                                    <a href="?id=<?= $tripId ?>&action=remove&place_id=<?= $place["id"] ?>"
                                        class="btn btn-sm btn-danger"
                                        onclick="return confirm('Retirer cette étape ?')">
                                        Retirer
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