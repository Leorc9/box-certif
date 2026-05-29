<?php
require_once("header.php");
require_once("config/database.php");

$tripId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

// Fetch the trip.
$stmt = $pdo->prepare("SELECT * FROM trip WHERE id = ?");
$stmt->execute([$tripId]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    http_response_code(404);
    echo "<div class='container mt-5'><div class='alert alert-danger'>Trip introuvable.</div></div>";
    exit();
}

// Fetch every share row of this trip.
$stmtShares = $pdo->prepare("SELECT * FROM shares WHERE trip_id = ?");
$stmtShares->execute([$tripId]);
$shares = $stmtShares->fetchAll(PDO::FETCH_ASSOC);

if (empty($shares)) {
    http_response_code(403);
    echo "<div class='container mt-5'><div class='alert alert-warning'>Ce trip n'est pas partagé.</div></div>";
    exit();
}

// Decide whether the current visitor can see the trip.
$sessionUserId = $_SESSION["id"] ?? null;
$ownerId       = (int) $trip["user_id"];
$hasPublic     = false;
$privateUsers  = []; // user ids the trip is privately shared with

foreach ($shares as $share) {
    if ($share["visibility"] === "public") {
        $hasPublic = true;
    } elseif ($share["shared_with_user_id"] !== null) {
        $privateUsers[] = (int) $share["shared_with_user_id"];
    }
}

$canSee = false;
if ($hasPublic) {
    // Public link: anyone can see.
    $canSee = true;
} elseif ($sessionUserId !== null) {
    // Private: must be the owner or one of the targeted users.
    $canSee = ($sessionUserId === $ownerId) || in_array($sessionUserId, $privateUsers, true);
}

if (!$canSee) {
    if ($sessionUserId === null) {
        header("Location: ../index.php");
        exit();
    }
    http_response_code(403);
    echo "<div class='container mt-5'><div class='alert alert-danger'>Accès refusé.</div></div>";
    exit();
}

// Fetch every place of the trip, ordered by visit order.
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
            <h3 class="mb-0">Trip partagé : <?= htmlspecialchars($trip["name"]) ?></h3>
            <span class="badge bg-light text-primary fs-6">
                <?= $hasPublic ? "Public" : "Privé" ?>
            </span>
        </div>

        <div class="card-body">
            <?php if (!empty($trip["total_distance_km"])): ?>
                <p class="lead">
                    Distance totale :
                    <strong><?= number_format((float) $trip["total_distance_km"], 1) ?> km</strong>
                </p>
            <?php endif; ?>

            <table class="table table-striped table-hover align-middle fs-5">
                <thead class="table-dark">
                    <tr>
                        <th>Ordre</th>
                        <th>Ville</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($places)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">Aucune étape pour ce trip.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($places as $place): ?>
                        <tr>
                            <td><?= $place["visit_order"] ?></td>
                            <td><?= htmlspecialchars($place["nom"]) ?></td>
                            <td><?= $place["latitude"] ?></td>
                            <td><?= $place["longitude"] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>