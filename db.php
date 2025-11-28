<?php
// ----------------------------
// 1) โหลดตัวแปร Environment (รองรับทั้ง Local และ Railway)
// ----------------------------
// ตั้งค่าตัวแปรใน Railway Dashboard:
//   DB_HOST = mysql.railway.internal
//   DB_PORT = 3306
//   DB_USER, DB_PASS, DB_NAME ตามที่ Railway สร้างให้

$host = getenv('DB_HOST') ?: 'mysql.railway.internal';
$port = getenv('DB_PORT') ?: 3306;
$user = getenv('DB_USER') ?: getenv('MYSQLUSER') ?: 'root';
$pass = getenv('DB_PASS') ?: getenv('MYSQLPASSWORD') ?: '';
$dbname = getenv('DB_NAME') ?: getenv('MYSQLDATABASE') ?: 'referback';

// หาก Railway ให้ค่าแบบ connection string ในตัวแปร DATABASE_URL (หรือ CLEARDB_DATABASE_URL)
// ให้ parse แล้วใช้ค่านั้นแทน (รองรับหลายรูปแบบ)
$databaseUrl = getenv('DATABASE_URL') ?: getenv('CLEARDB_DATABASE_URL') ?: getenv('MYSQL_URL') ?: '';
if ($databaseUrl) {
    $parts = parse_url($databaseUrl);
    if (!empty($parts['host'])) $host = $parts['host'];
    if (!empty($parts['port'])) $port = $parts['port'];
    if (!empty($parts['user'])) $user = $parts['user'];
    if (!empty($parts['pass'])) $pass = $parts['pass'];
    if (!empty($parts['path'])) $dbname = ltrim($parts['path'], '/');
}

// ชื่อตารางที่ใช้ร่วมกับโค้ดหลัก
$tableName = "patient_records";

try {
    // ----------------------------
    // 2) สร้าง PDO (ใช้ Host/Port จาก env หรือ fallback)
    // ----------------------------
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5
        ]
    );

} catch (PDOException $e) {
    // แสดงข้อความ Error ที่ชัดเจน
    echo "Database connection failed: " . $e->getMessage();
    exit;
}