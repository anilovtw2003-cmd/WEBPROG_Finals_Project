<?php
// ESP32 soil moisture monitoring - backend API
require_once 'config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

$dataFile = 'sensor_data.json';

// rate limiting (min 2s between writes per IP)
function checkRateLimit() {
    $file = sys_get_temp_dir() . '/soil_ratelimit_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.txt';
    if (file_exists($file) && (microtime(true) - floatval(@file_get_contents($file))) < 2) return false;
    @file_put_contents($file, microtime(true));
    return true;
}

function getSensorData($dataFile) {
    if (file_exists($dataFile) && ($data = json_decode(file_get_contents($dataFile), true))) return $data;
    return ['temperature' => 0, 'humidity' => 0, 'moisture' => 0, 'rawSoil' => 0, 'soilState' => 'Waiting...', 'waterDist' => 0, 'waterState' => 'Unknown', 'timestamp' => date('Y-m-d H:i:s')];
}

function getSensorDataFromDB() {
    $db = getDBConnection();
    if (!$db) return null;
    try {
        $row = $db->query("SELECT * FROM History ORDER BY time DESC LIMIT 1")->fetch();
        if ($row) return ['temperature' => floatval($row['temp']), 'humidity' => floatval($row['humidity']), 'moisture' => floatval($row['moisture']), 'rawSoil' => 0, 'soilState' => $row['soil'], 'waterDist' => 0, 'waterState' => $row['water'], 'timestamp' => $row['time']];
    } catch (PDOException $e) { error_log("db read error: " . $e->getMessage()); }
    return null;
}

function saveSensorData($dataFile, $data) {
    $data['timestamp'] = date('Y-m-d H:i:s');
    foreach (['temperature','humidity','moisture','waterDist'] as $k) {
        if (isset($data[$k])) $data[$k] = round(floatval($data[$k]), 1);
    }
    return file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

function saveSensorDataToDB($data) {
    $db = getDBConnection();
    if (!$db) return ['success' => false, 'error' => 'connection failed'];
    try {
        $stmt = $db->prepare("INSERT INTO History (time, temp, humidity, moisture, soil, water) VALUES (NOW(), ?, ?, ?, ?, ?)");
        $stmt->execute([floatval($data['temperature']), floatval($data['humidity']), floatval($data['moisture']), $data['soilState'] ?? 'Unknown', $data['waterState'] ?? 'Unknown']);
        $id = $db->lastInsertId();
        // keep only last 1000 records
        try {
            $count = $db->query("SELECT COUNT(*) FROM History")->fetchColumn();
            if ($count > 1000) $db->exec("DELETE FROM History ORDER BY id ASC LIMIT " . intval($count - 1000));
        } catch (PDOException $e) { error_log("db cleanup warning: " . $e->getMessage()); }
        return ['success' => true, 'inserted_id' => $id];
    } catch (PDOException $e) {
        error_log("db save error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// normalize raw input (GET/POST/JSON) into canonical sensor data array
function normalizeSensorInput($raw) {
    return [
        'temperature' => floatval($raw['temp'] ?? $raw['temperature'] ?? 0),
        'humidity' => floatval($raw['hum'] ?? $raw['humidity'] ?? 0),
        'moisture' => floatval($raw['soil'] ?? $raw['moisture'] ?? 0),
        'rawSoil' => intval($raw['soil_raw'] ?? $raw['raw'] ?? $raw['rawSoil'] ?? 0),
        'soilState' => $raw['soilState'] ?? $raw['soil_state'] ?? $raw['state'] ?? 'Unknown',
        'waterDist' => floatval($raw['water_dist'] ?? $raw['waterDist'] ?? 0),
        'waterState' => $raw['water_state'] ?? $raw['waterState'] ?? 'Unknown'
    ];
}

function validateSensorData($data) {
    $hasTemp = isset($data['temp']) && is_numeric($data['temp']) || isset($data['temperature']) && is_numeric($data['temperature']);
    $hasHum = isset($data['hum']) && is_numeric($data['hum']) || isset($data['humidity']) && is_numeric($data['humidity']);
    $hasSoil = isset($data['soil']) && is_numeric($data['soil']) || isset($data['moisture']) && is_numeric($data['moisture']);
    return $hasTemp && $hasHum && $hasSoil;
}

// save normalized sensor data to both DB and JSON, return response
function processSensorData($sensorData, $dataFile, $method = 'GET') {
    $dbResult = saveSensorDataToDB($sensorData);
    $dbSaved = is_array($dbResult) ? $dbResult['success'] : $dbResult;
    $jsonSaved = (bool)@saveSensorData($dataFile, $sensorData);

    if ($dbSaved || $jsonSaved) {
        echo json_encode(['success' => true, 'message' => "Data saved ($method)", 'db_saved' => $dbSaved, 'db_info' => $dbResult, 'json_saved' => $jsonSaved, 'data' => $sensorData]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'failed to save data']);
    }
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['temp']) || isset($_GET['soil']) || isset($_GET['temperature']) || isset($_GET['moisture'])) {
            // ESP32 sending data via GET
            if (!checkRateLimit()) {
                http_response_code(429);
                echo json_encode(['success' => false, 'message' => 'too many requests']);
                break;
            }
            if (validateSensorData($_GET)) {
                processSensorData(normalizeSensorInput($_GET), $dataFile, 'GET');
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'invalid data. Required: temp, hum, soil (numeric)']);
            }
        } elseif (($_GET['action'] ?? '') === 'get_history') {
            // admin history API
            if (!isLoggedIn() || !isAdmin()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'unauthorized']);
                break;
            }
            $db = getDBConnection();
            if ($db) {
                try {
                    echo json_encode(['success' => true, 'data' => $db->query("SELECT * FROM History ORDER BY id DESC LIMIT 500")->fetchAll()]);
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'database error']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'database connection failed']);
            }
        } else {
            // frontend requesting current data
            $data = getSensorDataFromDB() ?? getSensorData($dataFile);
            echo json_encode(['success' => true, 'data' => $data]);
        }
        break;

    case 'POST':
        if (!checkRateLimit()) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'too many requests']);
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = array_merge($_POST, $_GET);
        if (validateSensorData($data)) {
            processSensorData(normalizeSensorInput($data), $dataFile, 'POST');
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'invalid data. Required: temp, hum, soil (numeric)']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'method not allowed']);
}
?>
