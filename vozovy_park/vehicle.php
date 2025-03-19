<?php
require_once 'config.php'; // Připojení k databázi

// Funkce pro rezervaci vozidla
function reserveVehicle($vehicle_id, $user_id) {
    $conn = getDbConnection();

    date_default_timezone_set('Europe/Prague');
    setlocale(LC_TIME, 'cs_CZ.UTF-8');

    $start_date = date('Y-m-d H:i:s', strtotime('now')); // Aktuální čas zaokrouhlený na minuty
    $end_date = date('Y-m-d H:i:s', strtotime('+3 days'));
    

    // Kontrola dostupnosti vozidla
    $query = "SELECT status FROM vozidla WHERE vehicle_id = $1";
    $result = pg_query_params($conn, $query, [$vehicle_id]);

    if (!$result || pg_num_rows($result) === 0) {
        return "Vozidlo nebylo nalezeno.";
    }

    $vehicle = pg_fetch_assoc($result);

    if ($vehicle['status'] !== 'Dostupné') {
        return "Vozidlo není dostupné.";
    }

    // Rezervace vozidla
    pg_query($conn, "BEGIN");

    $query = "INSERT INTO vypujcky (vehicle_id, user_id, start_date, end_date, status) 
          VALUES ($1, $2, $3, $4, 'Aktivní')";
$result = pg_query_params($conn, $query, [$vehicle_id, $user_id, $start_date, $end_date]);

    if (!$result) {
        pg_query($conn, "ROLLBACK");
        return "Chyba při rezervaci vozidla: " . pg_last_error($conn);
    }

    $query = "UPDATE vozidla SET status = 'Půjčeno' WHERE vehicle_id = $1";
    $result = pg_query_params($conn, $query, [$vehicle_id]);

    if (!$result) {
        pg_query($conn, "ROLLBACK");
        return "Chyba při aktualizaci stavu vozidla: " . pg_last_error($conn);
    }

    pg_query($conn, "COMMIT");
    pg_close($conn);
    return "Rezervace vozidla byla úspěšná.";
}

// Funkce pro ukončení rezervace
function endReservation($reservation_id) {
    $conn = getDbConnection();

    // Načtení informace o rezervaci
    $query = "SELECT vehicle_id FROM vypujcky WHERE reservation_id = $1";
    $result = pg_query_params($conn, $query, [$reservation_id]);

    if (!$result || pg_num_rows($result) === 0) {
        return "Rezervace nebyla nalezena.";
    }

    $reservation = pg_fetch_assoc($result);
    $vehicle_id = $reservation['vehicle_id'];

    // Ukončení rezervace
    $query = "UPDATE vypujcky SET status = 'Ukončeno', end_date = NOW() WHERE reservation_id = $1";
    $result = pg_query_params($conn, $query, [$reservation_id]);

    if (!$result) {
        return "Chyba při ukončování rezervace: " . pg_last_error($conn);
    }

    // Změna stavu vozidla na "Servis"
    $query = "UPDATE vozidla SET status = 'Servis' WHERE vehicle_id = $1";
    $result = pg_query_params($conn, $query, [$vehicle_id]);

    if (!$result) {
        return "Chyba při aktualizaci stavu vozidla: " . pg_last_error($conn);
    }

    pg_close($conn);
    return "Rezervace byla úspěšně ukončena.";
}

function vypocetNajetychKilometru($staryTachometr, $novyTachometr) {
    $ujetokm = $novyTachometr - $staryTachometr;

    if ($ujetokm < 0) {
        return "Chyba: Nový stav tachometru nemůže být menší než původní.";
    }

    return $ujetokm;
}

// Aktualizace tachometru vozidla
function updateOdometer($vehicle_id, $new_odometer, $user_id) {
    $conn = getDbConnection();

    // Opravený dotaz: získáváme `odometer` z `vozidla`, `najeto_km` z `vypujcky`
    $query = "
        SELECT v.odometer, COALESCE(r.najeto_km, 0) AS najeto_km
        FROM vozidla v
        LEFT JOIN vypujcky r ON v.vehicle_id = r.vehicle_id 
        WHERE v.vehicle_id = $1 AND r.user_id = $2 AND r.status = 'Aktivní'
    ";
    $result = pg_query_params($conn, $query, [$vehicle_id, $user_id]);

    if (!$result || pg_num_rows($result) === 0) {
        return "Chyba: Rezervace nebyla nalezena.";
    }

    $reservation = pg_fetch_assoc($result);
    $old_odometer = (int) $reservation['odometer']; // Správné získání tachometru
    $previous_driven_km = (int) $reservation['najeto_km']; // Předchozí najeté km

    // Výpočet rozdílu kilometrů
    $driven_km = $new_odometer - $old_odometer;

    if ($driven_km < 0) {
        return "Nemůžete zadat nižší počet kilometrů než aktuální stav tachometru.";
    }

    // Celkový součet najetých km (předchozí + nově najeté)
    $total_driven_km = $previous_driven_km + $driven_km;

    // Aktualizace tachometru ve vozidle
    $query = "UPDATE vozidla SET odometer = $1 WHERE vehicle_id = $2";
    pg_query_params($conn, $query, [$new_odometer, $vehicle_id]);

    // Aktualizace celkových najetých km v rezervaci
    $query = "UPDATE vypujcky SET najeto_km = $1 WHERE vehicle_id = $2 AND user_id = $3 AND status = 'Aktivní'";
    pg_query_params($conn, $query, [$total_driven_km, $vehicle_id, $user_id]);

    pg_close($conn);

    // Vrátíme celkový počet najetých km
    return $total_driven_km;
}

function autoCancelExpiredReservations() {
    $conn = getDbConnection();

    $query = "
        UPDATE vypujcky 
        SET status = 'Ukončeno', end_date = NOW()
        WHERE status = 'Aktivní' 
        AND start_date <= NOW() - INTERVAL '30 days'
    ";

    pg_query($conn, $query);
    pg_close($conn);
}

// Funkce pro načtení rezervací konkrétního uživatele s detaily
function getUserReservationsWithDetails($user_id) {
    $conn = getDbConnection();

    $sql = "
        SELECT 
            r.reservation_id,
            v.vehicle_id,
            v.brand,
            v.model,
            r.start_date,
            r.end_date,
            r.status,
            v.next_due_km
        FROM vypujcky r
        JOIN vozidla v ON r.vehicle_id = v.vehicle_id
        WHERE r.user_id = $1
        ORDER BY r.start_date DESC
    ";

    $result = pg_query_params($conn, $sql, [$user_id]);

    if (!$result) {
        return [];
    }

    $reservations = pg_fetch_all($result) ?: [];
    pg_close($conn);
    return $reservations;
}

// Funkce pro načtení vozidel
function getAllVehiclesWithDetails() {
    $conn = getDbConnection();

    $sql = "
        SELECT 
            v.vehicle_id, 
            v.brand, 
            v.model, 
            v.year, 
            v.odometer, 
            v.fuel_cons, 
            v.status,
            v.next_due_km,
            v.photo_path,
            CASE 
                WHEN v.status = 'Servis' THEN 'Nedostupné'
                WHEN EXISTS (
                    SELECT 1 
                    FROM vypujcky 
                    WHERE vypujcky.vehicle_id = v.vehicle_id 
                    AND vypujcky.status = 'Aktivní'
                ) THEN 'Půjčeno'
                ELSE 'Dostupné'
            END AS rental_status
        FROM vozidla v
        ORDER BY v.vehicle_id;
    ";

    $result = pg_query($conn, $sql);

    if (!$result) {
        return [];
    }

    $vehicles = pg_fetch_all($result) ?: [];
    pg_close($conn);
    return $vehicles;
}

// Funkce pro načtení detailů uživatele
function getUserDetails($user_id) {
    $conn = getDbConnection();

    $query = "SELECT username, email FROM uzivatele WHERE user_id = $1";
    $result = pg_query_params($conn, $query, [$user_id]);

    if (!$result) {
        return null;
    }

    $user = pg_fetch_assoc($result);
    pg_close($conn);

    return $user ?: null;
}
//Funkce pro načtení ukončených rezervací
function getCompletedReservations() {
    $conn = getDbConnection();

    $sql = "
        SELECT 
            r.reservation_id,
            u.username,
            u.email,
            v.brand,
            v.model,
            r.start_date,
            r.end_date,
            r.najeto_km
        FROM vypujcky r
        JOIN vozidla v ON r.vehicle_id = v.vehicle_id
        JOIN uzivatele u ON r.user_id = u.user_id
        WHERE r.status = 'Ukončeno'
        ORDER BY r.end_date DESC
    ";

    $result = pg_query($conn, $sql);

    if (!$result) {
        return [];
    }

    $reservations = pg_fetch_all($result) ?: [];
    pg_close($conn);
    return $reservations;
}

// Funkce pro smazání vozidla
function deleteVehicle($vehicle_id) {
    $conn = getDbConnection();

    // Smazání historie servisu vozidla (pokud existuje)
    $query = "DELETE FROM service_history WHERE vehicle_id = $1";
    pg_query_params($conn, $query, [$vehicle_id]);

    // Nyní můžeme smazat vozidlo
    $query = "DELETE FROM vozidla WHERE vehicle_id = $1";
    $result = pg_query_params($conn, $query, [$vehicle_id]);

    if (!$result) {
        return "Chyba při mazání vozidla: " . pg_last_error($conn);
    }

    pg_close($conn);
    return "Vozidlo bylo úspěšně smazáno.";
}

// Funkce pro odstranění ukončené rezervace
function deleteReservation($reservation_id) {
    $conn = getDbConnection();

    $query = "DELETE FROM vypujcky WHERE reservation_id = $1";
    $result = pg_query_params($conn, $query, [$reservation_id]);

    if (!$result) {
        return "Chyba při mazání rezervace: " . pg_last_error($conn);
    }

    pg_close($conn);
    return "Rezervace byla úspěšně smazána.";
}

// Funkce pro uložení fotografie vozidla
function saveVehiclePhoto($vehicle_id, $file) {
    $targetDir = "uploads/";
    $fileName = basename($file["name"]);
    $targetFilePath = $targetDir . $fileName;

    if (move_uploaded_file($file["tmp_name"], $targetFilePath)) {
        $conn = getDbConnection();
        $query = "UPDATE vozidla SET photo_path = $1 WHERE vehicle_id = $2";
        $result = pg_query_params($conn, $query, [$targetFilePath, $vehicle_id]);

        if (!$result) {
            return "Chyba při ukládání fotografie: " . pg_last_error($conn);
        }

        pg_close($conn);
        return "Fotografie vozidla byla úspěšně uložena.";
    } else {
        return "Chyba při přesouvání fotografie na server.";
    }
}