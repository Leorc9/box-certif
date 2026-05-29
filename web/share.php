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
    echo "<div class='container mt-5'><div class='alert alert-danger'>Trip not found.</div></div>";
    exit();
}

// Fetch every share row of this trip.
$stmtShares = $pdo->prepare("SELECT * FROM shares WHERE trip_id = ?");
$stmtShares->execute([$tripId]);
$shares = $stmtShares->fetchAll(PDO::FETCH_ASSOC);

if (empty($shares)) {
    http_response_code(403);
    echo "<div class='container mt-5'><div class='alert alert-warning'>This trip is not shared.</div></div>";
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
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access denied.</div></div>";
    exit();
}

$isOwner = ($sessionUserId !== null && $sessionUserId === $ownerId);

// Optimize the trip route using the Python API
if (isset($_GET["action"]) && $_GET["action"] === "optimize") {
    $stmtAll = $pdo->prepare("
        SELECT p.id, p.nom, p.latitude, p.longitude
        FROM places p
        JOIN trip_places tp ON tp.place_id = p.id
        WHERE tp.trip_id = ?
        ORDER BY tp.visit_order ASC
    ");
    $stmtAll->execute([$tripId]);
    $placesToOptimize = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

    if (count($placesToOptimize) < 2) {
        $_SESSION["optimizeError"] = "Add at least 2 cities before optimizing.";
    } else {
        $payload = json_encode([
            "places" => array_map(fn($p) => [
                "name"      => $p["nom"],
                "latitude"  => (float) $p["latitude"],
                "longitude" => (float) $p["longitude"],
            ], $placesToOptimize)
        ]);

        $ch = curl_init("http://127.0.0.1:8000/optimize");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($response && $httpCode === 200) {
            $result = json_decode($response, true);

            // Map city name → place id to update visit_order
            $nameToId = array_column($placesToOptimize, "id", "nom");

            // Update visit_order: hotel first, then its day-trip cities
            $order = 1;
            $stmtUpdateOrder = $pdo->prepare("UPDATE trip_places SET visit_order = ? WHERE trip_id = ? AND place_id = ?");
            foreach ($result["clusters"] as $cluster) {
                if (isset($nameToId[$cluster["hotel"]])) {
                    $stmtUpdateOrder->execute([$order++, $tripId, $nameToId[$cluster["hotel"]]]);
                }
                foreach ($cluster["dayTrips"] as $dayTripName) {
                    if (isset($nameToId[$dayTripName])) {
                        $stmtUpdateOrder->execute([$order++, $tripId, $nameToId[$dayTripName]]);
                    }
                }
            }

            // Save total distance in the trip row
            $stmtDist = $pdo->prepare("UPDATE trip SET total_distance_km = ? WHERE id = ?");
            $stmtDist->execute([$result["totalDistance"], $tripId]);

            $_SESSION["optimizeResult"] = $result;
        } else {
            $_SESSION["optimizeError"] = "Optimization API is unreachable. Make sure the Python server is running.";
        }
    }

    header("Location: share.php?id=$tripId");
    exit();
}

// Read and clear flash messages from session
$optimizeResult = $_SESSION["optimizeResult"] ?? null;
$optimizeError  = $_SESSION["optimizeError"]  ?? null;
unset($_SESSION["optimizeResult"], $_SESSION["optimizeError"]);

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
            <h3 class="mb-0">Shared trip: <?= htmlspecialchars($trip["name"]) ?></h3>
            <div class="d-flex align-items-center gap-2">
                <a href="share.php?id=<?= $tripId ?>&action=optimize" class="btn btn-sm btn-success">
                    Optimize route
                </a>
                <span class="badge bg-light text-primary fs-6">
                    <?= $hasPublic ? "Public" : "Private" ?>
                </span>
            </div>
        </div>

        <div class="card-body">
            <?php if ($optimizeError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($optimizeError) ?></div>
            <?php endif; ?>

            <?php if ($optimizeResult): ?>
                <?php
                    $fullCityList = [];
                    foreach ($optimizeResult["clusters"] as $cluster) {
                        $fullCityList[] = ["name" => $cluster["hotel"], "isHotel" => true];
                        foreach ($cluster["dayTrips"] as $dayTrip) {
                            $fullCityList[] = ["name" => $dayTrip, "isHotel" => false];
                        }
                    }
                    $firstCity = $fullCityList[0]["name"] ?? "";
                ?>
                <div class="alert alert-success">
                    <strong>Route optimized!</strong> — Total: <?= $optimizeResult["totalDistance"] ?> km
                    <ol class="mb-0 mt-2">
                        <?php foreach ($fullCityList as $city): ?>
                            <li>
                                <?= htmlspecialchars($city["name"]) ?>
                                <?php if ($city["isHotel"]): ?>
                                    <span class="badge bg-primary ms-1">hotel</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                        <li class="text-muted fst-italic">Return to <?= htmlspecialchars($firstCity) ?></li>
                    </ol>
                </div>
            <?php endif; ?>

            <?php if (!empty($trip["total_distance_km"])): ?>
                <p class="lead">
                    Total distance:
                    <strong><?= number_format((float) $trip["total_distance_km"], 1) ?> km</strong>
                </p>
            <?php endif; ?>

            <table class="table table-striped table-hover align-middle fs-5">
                <thead class="table-dark">
                    <tr>
                        <th>Order</th>
                        <th>City</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($places)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">No stops for this trip.</td>
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