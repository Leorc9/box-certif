<?php
require_once("../header.php");
require_once("../config/database.php");

if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit();
}

$userId = $_SESSION["id"];
$tripId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;
$error = null;

// Check if the trip exists and belongs to the user
$stmt = $pdo->prepare("SELECT * FROM trip WHERE id = ? AND user_id = ?");
$stmt->execute([$tripId, $userId]);
$trip = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$trip) {
    header("Location: index.php");
    exit();
}

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
        // Build the JSON payload for the Python API
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
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);

        if ($response && $httpCode === 200) {
            $result = json_decode($response, true);

            // Map city name → place id so we can update visit_order
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

    header("Location: ?id=$tripId");
    exit();
}

// Read and clear flash messages from session
$optimizeResult = $_SESSION["optimizeResult"] ?? null;
$optimizeError  = $_SESSION["optimizeError"]  ?? null;
unset($_SESSION["optimizeResult"], $_SESSION["optimizeError"]);

// Remove a place from the trip
if (isset($_GET["action"]) && $_GET["action"] === "remove" && isset($_GET["place_id"])) {
    $placeId = (int) $_GET["place_id"];
    $stmt = $pdo->prepare("DELETE FROM trip_places WHERE trip_id = ? AND place_id = ?");
    $stmt->execute([$tripId, $placeId]);
}

// Add a new place to the trip
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cityName = trim($_POST["nom"]);

    if (!empty($cityName)) {
        // Call GeoNames API to get coordinates
        $apiUrl = "http://api.geonames.org/searchJSON?q=" . urlencode($cityName) . "&maxRows=1&featureClass=P&username=leoleo09090";

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $apiResponse = curl_exec($ch);
        $geoData = json_decode($apiResponse, true);

        if (empty($geoData["geonames"])) {
            $error = "City not found.";
        } else {
            $cityData   = $geoData["geonames"][0];
            $latitude    = (float) $cityData["lat"];
            $longitude   = (float) $cityData["lng"];
            $officialName = $cityData["name"];

            // Check if the city is already in this trip
            $stmtCheck = $pdo->prepare("
                SELECT COUNT(*) FROM places p
                JOIN trip_places tp ON tp.place_id = p.id
                WHERE tp.trip_id = ? AND LOWER(p.nom) = LOWER(?)
            ");
            $stmtCheck->execute([$tripId, $officialName]);
            $existe = $stmtCheck->fetchColumn();

            if ($existe > 0) {
                $error = "\"$officialName\" is already in this trip.";
            } else {
                // Insert the place
                $stmtPlace = $pdo->prepare("INSERT INTO places (user_id, nom, latitude, longitude) VALUES (?, ?, ?, ?)");
                $stmtPlace->execute([$userId, $officialName, $latitude, $longitude]);
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
            <h4 class="mb-0">Edit trip: <?= htmlspecialchars($trip["name"]) ?></h4>
            <div class="d-flex gap-2">
                <a href="?id=<?= $tripId ?>&action=optimize" class="btn btn-sm btn-success">
                    Optimize route
                </a>
                <a href="index.php" class="btn btn-sm btn-light">← Retour</a>
            </div>
        </div>

        <div class="card-body">
            <?php if ($optimizeError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($optimizeError) ?></div>
            <?php endif; ?>

            <?php if ($optimizeResult): ?>
                <?php
                    // Build the full ordered city list from clusters
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

            <form method="POST" action="?id=<?= $tripId ?>">
                <div class="row g-2 align-items-end">
                    <div class="col-md-10">
                        <label class="form-label">City name</label>
                        <input type="text" class="form-control" name="nom" placeholder="Ex: Paris" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </div>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger mt-3 mb-0"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<div class="container mt-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <h5 class="mb-0">Trip stops</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover align-middle">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>City</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($places)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No stops yet.</td>
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
                                        onclick="return confirm('Remove this stop?')">
                                        Remove
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