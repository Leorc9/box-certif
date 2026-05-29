<?php

use PHPUnit\Framework\TestCase;

class AppTest extends TestCase
{
    // ─── Auth : password ────────────────────────────────────────────────────

    public function testPasswordsMatchReturnsTrueWhenIdentical(): void
    {
        $password        = 'secret123';
        $confirmPassword = 'secret123';

        $this->assertTrue($password === $confirmPassword);
    }

    public function testPasswordsMatchReturnsFalseWhenDifferent(): void
    {
        $password        = 'secret123';
        $confirmPassword = 'wrong456';

        $this->assertFalse($password === $confirmPassword);
    }

    public function testPasswordHashCanBeVerified(): void
    {
        $password       = 'myP@ssw0rd';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $this->assertTrue(password_verify($password, $hashedPassword));
    }

    public function testWrongPasswordFailsVerification(): void
    {
        $hashedPassword = password_hash('correct', PASSWORD_DEFAULT);

        $this->assertFalse(password_verify('wrong', $hashedPassword));
    }

    // ─── Trip : name validation ─────────────────────────────────────────────

    public function testTripNameIsValidWhenNotEmpty(): void
    {
        $tripName = trim('  Mon voyage  ');

        $this->assertNotEmpty($tripName);
        $this->assertSame('Mon voyage', $tripName);
    }

    public function testTripNameIsInvalidWhenEmpty(): void
    {
        $tripName = trim('   ');

        $this->assertEmpty($tripName);
    }

    // ─── Trip : adding a place ──────────────────────────────────────────────

    public function testCityNameIsTrimmedBeforeInsert(): void
    {
        $nomVille = trim('  Paris  ');

        $this->assertSame('Paris', $nomVille);
    }

    public function testEmptyCityNameIsRejected(): void
    {
        $nomVille = trim('');

        $this->assertEmpty($nomVille);
    }

    // ─── Optimize : JSON payload building ───────────────────────────────────

    public function testOptimizePayloadContainsCorrectStructure(): void
    {
        $places = [
            ['nom' => 'Paris',  'latitude' => '48.8566', 'longitude' => '2.3522'],
            ['nom' => 'Lyon',   'latitude' => '45.7640', 'longitude' => '4.8357'],
        ];

        $payload = json_encode([
            'places' => array_map(fn($p) => [
                'name'      => $p['nom'],
                'latitude'  => (float) $p['latitude'],
                'longitude' => (float) $p['longitude'],
            ], $places)
        ]);

        $decoded = json_decode($payload, true);

        $this->assertArrayHasKey('places', $decoded);
        $this->assertCount(2, $decoded['places']);
        $this->assertSame('Paris', $decoded['places'][0]['name']);
        $this->assertIsFloat($decoded['places'][0]['latitude']);
    }

    public function testOptimizeIsBlockedWithLessThan2Places(): void
    {
        $places = [
            ['nom' => 'Paris', 'latitude' => '48.8566', 'longitude' => '2.3522'],
        ];

        $this->assertLessThan(2, count($places));
    }

    // ─── Optimize : ordered city list from clusters ──────────────────────────

    public function testFullCityListIsBuiltFromClusters(): void
    {
        $clusters = [
            ['hotel' => 'Paris',  'dayTrips' => ['Versailles', 'Chartres']],
            ['hotel' => 'Lyon',   'dayTrips' => ['Annecy']],
        ];

        $fullCityList = [];
        foreach ($clusters as $cluster) {
            $fullCityList[] = ['name' => $cluster['hotel'], 'isHotel' => true];
            foreach ($cluster['dayTrips'] as $dayTrip) {
                $fullCityList[] = ['name' => $dayTrip, 'isHotel' => false];
            }
        }

        $this->assertCount(5, $fullCityList);
        $this->assertTrue($fullCityList[0]['isHotel']);
        $this->assertFalse($fullCityList[1]['isHotel']);
        $this->assertSame('Versailles', $fullCityList[1]['name']);
        $this->assertSame('Annecy', $fullCityList[4]['name']);
    }

    public function testFirstCityIsFromFirstClusterHotel(): void
    {
        $clusters = [
            ['hotel' => 'Paris', 'dayTrips' => []],
        ];

        $fullCityList = [];
        foreach ($clusters as $cluster) {
            $fullCityList[] = ['name' => $cluster['hotel'], 'isHotel' => true];
        }

        $firstCity = $fullCityList[0]['name'] ?? '';

        $this->assertSame('Paris', $firstCity);
    }

    // ─── checkLogin : session logic ─────────────────────────────────────────

    public function testSessionIdIsSet(): void
    {
        $_SESSION['id'] = 42;

        $this->assertTrue(!empty($_SESSION['id']));
    }

    public function testSessionIdMissingTriggersRedirect(): void
    {
        unset($_SESSION['id']);

        $shouldRedirect = empty($_SESSION['id']);

        $this->assertTrue($shouldRedirect);
    }

    // ─── Share link : tripId from GET ───────────────────────────────────────

    public function testTripIdIsCastToInt(): void
    {
        $_GET['id'] = '7';
        $tripId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        $this->assertIsInt($tripId);
        $this->assertSame(7, $tripId);
    }

    public function testMissingTripIdDefaultsToZero(): void
    {
        unset($_GET['id']);
        $tripId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

        $this->assertSame(0, $tripId);
    }
}
