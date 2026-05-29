<?php
require_once("header.php");
require_once("config/database.php");

if (!isset($_SESSION["id"])) {
    header("Location: ../index.php");
    exit();
}

$userId  = $_SESSION["id"];
$message = null;
$error   = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? null;
    $tripId = isset($_POST["tripId"]) ? (int) $_POST["tripId"] : 0;

    // Make sure the trip belongs to the current user before changing anything.
    $stmtCheck = $pdo->prepare("SELECT id FROM trip WHERE id = ? AND user_id = ?");
    $stmtCheck->execute([$tripId, $userId]);
    $ownsTrip = $stmtCheck->fetch();

    if ($ownsTrip) {
        // --- Toggle the public link (insert or remove the public row). ---
        if ($action === "togglePublic") {
            $stmtPub = $pdo->prepare(
                "SELECT id FROM shares
                 WHERE trip_id = ? AND visibility = 'public' AND shared_with_user_id IS NULL"
            );
            $stmtPub->execute([$tripId]);
            $publicRow = $stmtPub->fetch(PDO::FETCH_ASSOC);

            if ($publicRow) {
                $stmt = $pdo->prepare("DELETE FROM shares WHERE id = ?");
                $stmt->execute([$publicRow["id"]]);
                $message = "Public link removed.";
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO shares (trip_id, shared_with_user_id, visibility)
                     VALUES (?, NULL, 'public')"
                );
                $stmt->execute([$tripId]);
                $message = "Trip made public.";
            }
        }

        // --- Share privately with a user identified by their email. ---
        elseif ($action === "addPrivate") {
            $email = trim($_POST["email"] ?? "");

            if ($email === "") {
                $error = "Email required.";
            } else {
                $stmtUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmtUser->execute([$email]);
                $targetUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

                if (!$targetUser) {
                    $error = "No user found with this email.";
                } elseif ((int) $targetUser["id"] === $userId) {
                    $error = "You cannot share a trip with yourself.";
                } else {
                    // Upsert: avoids a "Duplicate entry" error if the trip
                    // is already shared with this user (UNIQUE key on
                    // trip_id + shared_with_user_id).
                    $stmtInsert = $pdo->prepare(
                        "INSERT INTO shares (trip_id, shared_with_user_id, visibility)
                         VALUES (?, ?, 'private')
                         ON DUPLICATE KEY UPDATE visibility = 'private'"
                    );
                    $stmtInsert->execute([$tripId, $targetUser["id"]]);
                    $message = "Private share added for " . $email . ".";
                }
            }
        }

        // --- Remove a single share row (works for both public and private). ---
        elseif ($action === "removeShare") {
            $shareId = isset($_POST["shareId"]) ? (int) $_POST["shareId"] : 0;
            // Join makes sure the share belongs to a trip owned by the current user.
            $stmt = $pdo->prepare(
                "DELETE s FROM shares s
                 JOIN trip t ON t.id = s.trip_id
                 WHERE s.id = ? AND t.user_id = ?"
            );
            $stmt->execute([$shareId, $userId]);
            $message = "Share removed.";
        }
    }
}

// Fetch the user's trips.
$stmt = $pdo->prepare("SELECT * FROM trip WHERE user_id = ? ORDER BY id DESC");
$stmt->execute([$userId]);
$trips = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch every share row for those trips, grouped by trip id.
$sharesByTrip = [];
if (!empty($trips)) {
    $tripIds      = array_column($trips, "id");
    $placeholders = implode(",", array_fill(0, count($tripIds), "?"));
    $stmtShares   = $pdo->prepare("
        SELECT s.*, u.email AS recipientEmail
        FROM shares s
        LEFT JOIN users u ON u.id = s.shared_with_user_id
        WHERE s.trip_id IN ($placeholders)
    ");
    $stmtShares->execute($tripIds);
    foreach ($stmtShares->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sharesByTrip[(int) $row["trip_id"]][] = $row;
    }
}

// Build the base URL used for share links (rtrim avoids "//" at the root).
$basePath = rtrim(dirname($_SERVER["PHP_SELF"]), "/");
$baseUrl  = "http://" . $_SERVER["HTTP_HOST"] . $basePath . "/share.php";
?>

<div class="container mt-5">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h4 class="mb-0">Share my trips</h4>
        </div>

        <div class="card-body">
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (empty($trips)): ?>
                <p class="text-muted">You don't have any trips yet.</p>
            <?php else: ?>
                <?php foreach ($trips as $trip): ?>
                    <?php
                    // Split the shares of this trip into "public" and "private" buckets.
                    $tripShares    = $sharesByTrip[(int) $trip["id"]] ?? [];
                    $publicShare   = null;
                    $privateShares = [];
                    foreach ($tripShares as $shareRow) {
                        if ($shareRow["visibility"] === "public") {
                            $publicShare = $shareRow;
                        } else {
                            $privateShares[] = $shareRow;
                        }
                    }
                    $shareUrl = $baseUrl . "?id=" . $trip["id"];
                    ?>

                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong><?= htmlspecialchars($trip["name"]) ?></strong>
                            <?php if ($publicShare): ?>
                                <span class="badge bg-success">Public</span>
                            <?php elseif (!empty($privateShares)): ?>
                                <span class="badge bg-warning text-dark">Private</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not shared</span>
                            <?php endif; ?>
                        </div>

                        <div class="card-body">
                            <p class="mb-2">
                                Link:
                                <a href="<?= htmlspecialchars($shareUrl) ?>" target="_blank">
                                    <?= htmlspecialchars($shareUrl) ?>
                                </a>
                            </p>

                            <!-- Public toggle -->
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="tripId" value="<?= $trip["id"] ?>">
                                <input type="hidden" name="action" value="togglePublic">
                                <?php if ($publicShare): ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Remove public link
                                    </button>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-sm btn-success">
                                        Make public
                                    </button>
                                <?php endif; ?>
                            </form>

                            <hr>

                            <!-- Private share form: pick a user by their email. -->
                            <form method="POST" class="row g-2 align-items-end">
                                <input type="hidden" name="tripId" value="<?= $trip["id"] ?>">
                                <input type="hidden" name="action" value="addPrivate">
                                <div class="col-md-8">
                                    <label class="form-label mb-0">Share privately with (email):</label>
                                    <input type="email" name="email" class="form-control" required>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" class="btn btn-warning w-100">
                                        Share privately
                                    </button>
                                </div>
                            </form>

                            <?php if (!empty($privateShares)): ?>
                                <p class="mt-3 mb-1"><strong>Privately shared with:</strong></p>
                                <ul class="list-group">
                                    <?php foreach ($privateShares as $privShare): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <?= htmlspecialchars($privShare["recipientEmail"]) ?>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="tripId" value="<?= $trip["id"] ?>">
                                                <input type="hidden" name="action" value="removeShare">
                                                <input type="hidden" name="shareId" value="<?= $privShare["id"] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Remove
                                                </button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>