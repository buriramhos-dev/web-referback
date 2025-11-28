<?php
// ----------------------------
// 1) โหลดตัวแปร Environment (สำหรับ Railway)
// ----------------------------
$host = getenv("MYSQLHOST") ?: "localhost";
$user = getenv("MYSQLUSER") ?: "root";
$pass = getenv("MYSQLPASSWORD") ?: "";
$dbname = getenv("MYSQLDATABASE") ?: "referback";
$port = getenv("MYSQLPORT") ?: 3306;

// ชื่อตารางที่ใช้ร่วมกับโค้ดหลัก
$tableName = "patient_records";

try {
    // ----------------------------
    // 2) สร้าง PDO (ใช้ port หาก Railway ให้มา)
    // ----------------------------
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
    exit;
}
