<?php

$ADMIN_KEY = getenv('ADMIN_ACCESS_KEY');// ตั้งรหัสลับของคุณได้เลย

if (!isset($_GET['key']) || $_GET['key'] !== $ADMIN_KEY) {
    http_response_code(403);
    exit("<h2>403 Forbidden</h2><p>คุณไม่มีสิทธิ์เข้าหน้านี้</p>");
}
// ตั้งค่าการเชื่อมต่อฐานข้อมูล (ต้องสร้างไฟล์ db.php)
require_once __DIR__ . '/db.php';
// $tableName ถูกกำหนดไว้ใน db.php แล้ว (patient_records)

// Helper function to sanitize output
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

// Handle POST actions: add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // *** รับค่าวันที่มาเป็น String โดยตรง ไม่ต้องแปลง ***
        $date_in_text = $_POST['date_in'] ?? null;
        $time_contact = $_POST['time_contact'] ?? null;
        $status = $_POST['status'] ?? 1;

        $stmt = $pdo->prepare("INSERT INTO `{$tableName}` (
            date_in, name, surname, ward, hospital, o2_ett_icd, partner, note, time_contact, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $date_in_text, // ใช้ค่าที่ผู้ใช้พิมพ์โดยตรง
            $_POST['name'] ?: null,
            $_POST['surname'] ?: null,
            $_POST['ward'] ?: null,
            $_POST['hospital'] ?: null,
            $_POST['o2_ett_icd'] ?: null,
            $_POST['partner'] ?: null,
            $_POST['note'] ?: null,
            $time_contact ?: null,
            $status
        ]);

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        // *** รับค่าวันที่มาเป็น String โดยตรง ไม่ต้องแปลง ***
        $date_in_text = $_POST['date_in'] ?? null;
        $time_contact = $_POST['time_contact'] ?? null;
        $status = $_POST['status'] ?? 1;

        $stmt = $pdo->prepare("UPDATE `{$tableName}` SET
            date_in = ?, name = ?, surname = ?, ward = ?, hospital = ?, o2_ett_icd = ?, partner = ?, note = ?, time_contact = ?, status = ?
            WHERE id = ?");

        $stmt->execute([
            $date_in_text, // ใช้ค่าที่ผู้ใช้พิมพ์โดยตรง
            $_POST['name'] ?: null,
            $_POST['surname'] ?: null,
            $_POST['ward'] ?: null,
            $_POST['hospital'] ?: null,
            $_POST['o2_ett_icd'] ?: null,
            $_POST['partner'] ?: null,
            $_POST['note'] ?: null,
            $time_contact ?: null,
            $status,
            $id
        ]);

        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM `{$tableName}` WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Fetch rows and group by status
$allRows = $pdo->query("
    SELECT id, date_in, name, surname, ward, hospital, o2_ett_icd, partner, note,
           time_contact AS contact_time, status
    FROM `{$tableName}`
    ORDER BY status ASC, date_in DESC, id DESC
")->fetchAll();

// Group rows by status
$groupedRows = [
    1 => [], // รอรถเข้ารับ
    2 => [], // รถกำลังมารับ
    3 => []  // บุรีรัมย์ไปส่ง
];

foreach ($allRows as $row) {
    $status = (int)$row['status'];
    if (isset($groupedRows[$status])) {
        $groupedRows[$status][] = $row;
    }
}

$statusLabels = [
    1 => 'รอรถเข้ารับ',
    2 => 'รถกำลังมารับ',
    3 => 'บุรีรัมย์ไปส่ง'
];

// Fetch hospital zipcodes for search helper
$zipcodeRows = $pdo->query("
    SELECT hospital_name, zipcode
    FROM hospital_zipcodes
    WHERE zipcode IS NOT NULL AND zipcode <> ''
")->fetchAll();

$zipcodeMap = [];
foreach ($zipcodeRows as $zipRow) {
    $zip = trim($zipRow['zipcode']);
    $hospitalName = trim($zipRow['hospital_name']);
    if ($zip === '' || $hospitalName === '') {
        continue;
    }
    if (!isset($zipcodeMap[$zip])) {
        $zipcodeMap[$zip] = [];
    }
    $zipcodeMap[$zip][] = $hospitalName;
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* *** CSS Reset and Base Styles *** */
        * {
            box-sizing: border-box;
        }

        :root{
            --page-bg: #d8eefe;
            --panel-bg: #ffffff;
            --accent: #2d9bf7;
            --accent-dark: #0b6ecf;
            --muted: #6b7280;
        }

        body {
            font-family: "Prompt", Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: var(--page-bg);
            min-height: 100vh;
            color: #0f1720;
            font-size: 14px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--panel-bg);
            border-radius: 12px;
            padding: 28px;
            box-shadow: 0 8px 30px rgba(2,6,23,0.06);
        }

        h2 {
            margin-bottom: 20px;
            color: var(--accent-dark);
            font-weight: 700;
            font-size: 28px;
            text-align: center;
            margin-top: 0;
        }

        /* Header layout: images on both sides, title centered */
        .page-header { display: flex; align-items: center; justify-content: center; gap: 30px; margin-bottom: 18px; }
        .page-header h2 { text-align: center; margin: 0; flex: 0 1 auto; }
        .doctor-img, .ambulance-img { max-width: 140px; height: auto; display: block; flex: 0 0 auto; }

        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: center; gap: 15px; }
            .page-header h2 { text-align: center; }
            .doctor-img, .ambulance-img { margin: 0; }
        }

        /* Toolbar */
        .toolbar {
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 15px;
            flex-wrap: wrap;
        }

            .toolbar button {
                background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dark) 100%);
                color: #fff;
                padding: 12px 24px;
                border: none;
                border-radius: 10px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                box-shadow: 0 6px 18px rgba(45,155,247,0.12);
                transition: all 0.22s ease;
                position: relative;
                overflow: hidden;
            }

        .toolbar button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(78, 205, 196, 0.5);
        }

        /* ช่องค้นหา */
        #hospitalSearch {
            padding: 12px 16px;
            border: 2px solid #e6eefb;
            border-radius: 10px;
            font-size: 14px;
            width: 300px;
            box-shadow: 0 2px 8px rgba(2,6,23,0.04);
            transition: all 0.22s ease;
            background: white;
        }

        #hospitalSearch:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 6px 18px rgba(45,155,247,0.12);
            transform: translateY(-1px);
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        th {
            background: linear-gradient(135deg,  #55423d 0%, #6d554eff 100%);
            color: #fff;
            padding: 15px;
            font-weight: 600;
            text-align: left;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        th:first-child { border-top-left-radius: 12px; }
        th:last-child { border-top-right-radius: 12px; }

        /* Group Header Styles */
        tr.group-header {
            background: transparent;
        }

        tr.group-header td.group-header-cell {
            background: linear-gradient(135deg, #0a5e59ff 0%, #44a08d 100%);
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            padding: 15px 20px;
            text-align: left;
            border-bottom: 3px solid rgba(255, 255, 255, 0.3);
            vertical-align: middle;
        }

        tr.group-header.status-group-1 td.group-header-cell {
            background: linear-gradient(135deg, #3e8deeff 0%, #3e8deeff 100%);
        }

        tr.group-header.status-group-2 td.group-header-cell {
            background: linear-gradient(135deg, #fda81fff 0%, #fda81fff 100%);
        }

        tr.group-header.status-group-3 td.group-header-cell {
            background: linear-gradient(135deg, #06ba5dff 0%, #06ba5dff 100%);
        }

        .group-count {
            font-size: 14px;
            font-weight: 400;
            opacity: 0.9;
            margin-left: 10px;
        }

        td {
            padding: 15px;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
            background: #fff;
            vertical-align: top;
            transition: all 0.2s ease;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover td {
            background: linear-gradient(90deg, #e8faf9 0%, #fff 100%);
            transform: scale(1.01);
            box-shadow: 0 2px 8px rgba(78, 205, 196, 0.1);
        }

        /* Actions Buttons in table - จัดให้อยู่กึ่งกลางทั้งแนวนอนและแนวตั้ง */
        td.actions {
            vertical-align: middle !important;
            text-align: center !important;
            padding: 15px !important;
        }

        /* ใช้เฉพาะ selector ที่ชัดเจนเพื่อให้แน่ใจว่าปุ่มอยู่ในคอลัมน์จัดการ */
        td.actions > .actions {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            margin: 0;
        }
        
        td.actions > .actions > form {
            display: inline-block;
            margin: 0;
            padding: 0;
        }

        td.actions > .actions > button,
        td.actions > .actions > form > button {
            padding: 10px 20px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            cursor: pointer;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            white-space: nowrap;
            flex-shrink: 0;
            min-width: 75px;
            position: relative;
            overflow: hidden;
        }

        td.actions > .actions > button::before,
        td.actions > .actions > form > button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.25);
            transform: translate(-50%, -50%);
            transition: width 0.5s, height 0.5s;
        }

        td.actions > .actions > button:hover::before,
        td.actions > .actions > form > button:hover::before {
            width: 300px;
            height: 300px;
        }

        .btnEdit { 
            background: #00a651;
            border-color: rgba(0, 166, 81, 0.3);
            box-shadow: 0 2px 8px rgba(0, 166, 81, 0.2);
        }
        .btnEdit:hover { 
            background: #008f46;
            border-color: rgba(0, 143, 70, 0.4);
            box-shadow: 0 4px 12px rgba(0, 166, 81, 0.35);
            transform: translateY(-2px);
        }
        .btnEdit:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(0, 166, 81, 0.3);
        }

        .btnDelete { 
            background: #e63939;
            border-color: rgba(230, 57, 57, 0.3);
            box-shadow: 0 2px 8px rgba(230, 57, 57, 0.2);
        }
        .btnDelete:hover { 
            background: #d32f2f;
            border-color: rgba(211, 47, 47, 0.4);
            box-shadow: 0 4px 12px rgba(230, 57, 57, 0.35);
            transform: translateY(-2px);
        }
        .btnDelete:active {
            transform: translateY(0);
            box-shadow: 0 2px 6px rgba(230, 57, 57, 0.3);
        }

        /* Status Colors */
        .status-1 { color: #1f7aed; font-weight: 600; padding: 4px 12px; background: rgba(31, 122, 237, 0.1); border-radius: 20px; display: inline-block; }
        .status-2 { color: #f39c12; font-weight: 600; padding: 4px 12px; background: rgba(243, 156, 18, 0.1); border-radius: 20px; display: inline-block; }
        .status-3 { color: #00a651; font-weight: 600; padding: 4px 12px; background: rgba(0, 166, 81, 0.1); border-radius: 20px; display: inline-block; }
        
        /* Delete Confirmation Modal */
        #deleteConfirmModal {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 3000;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        #deleteConfirmModal .confirm-panel {
            background: #fff;
            padding: 32px;
            border-radius: 16px;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: confirmSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            text-align: center;
        }

        @keyframes confirmSlideIn { from { opacity: 0; transform: translateY(-40px) scale(0.9); } to { opacity: 1; transform: translateY(0) scale(1); } }

        .confirm-panel h4 {
            margin: 0 0 12px 0;
            color: #e63939;
            font-size: 20px;
            font-weight: 700;
        }

        .confirm-panel p {
            margin: 0 0 28px 0;
            color: #4a5568;
            font-size: 14px;
            line-height: 1.6;
        }

        .confirm-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
        }

        .confirm-buttons button {
            padding: 11px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.28s ease;
            min-width: 100px;
        }

        .confirm-buttons .btn-cancel {
            background: #f5f7fa;
            color: #4a5568;
            border: 2px solid #e0e7ff;
        }

        .confirm-buttons .btn-cancel:hover {
            background: #f0f4f8;
            border-color: #cbd5e0;
            transform: translateY(-1px);
        }

        .confirm-buttons .btn-delete {
            background: linear-gradient(135deg, #e63939 0%, #c92a2a 100%);
            color: #fff;
            box-shadow: 0 6px 18px rgba(230, 57, 57, 0.25);
        }

        .confirm-buttons .btn-delete:hover {
            box-shadow: 0 10px 28px rgba(230, 57, 57, 0.35);
            transform: translateY(-2px);
        }

        .confirm-buttons .btn-delete:active {
            transform: translateY(0);
        }
        
        /* Modal Styles */
        .modal {
            display: none; position: fixed; left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            align-items: center; justify-content: center;
            z-index: 2000; backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        .modal .panel {
            background: #fff; padding: 20px; border-radius: 16px;
            max-width: 900px; width: 95%; max-height: calc(100vh - 80px);
            overflow: visible; box-shadow: 0 20px 60px rgba(0,0,0,0.18);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-50px) scale(0.9); } to { opacity: 1; transform: translateY(0) scale(1); } }

        /* Grid layout to keep modal on one page */
        .form-grid { display: flex; flex-wrap: wrap; gap: 12px; }
        .form-grid .form-row { display: flex; gap: 10px; align-items: center; width: calc(50% - 6px); margin-bottom: 0; }
        .form-grid .form-row.full { width: 100%; }
        .form-grid .form-row > label { width: 120px; margin-bottom: 0; font-weight: 500; font-size: 14px; color: #555; }

        input[type=text], textarea, select {
            flex: 1; padding: 8px 12px; border: 1px solid #e6e6e6;
            border-radius: 8px; font-size: 14px; box-sizing: border-box;
            transition: all 0.18s ease; font-family: "Prompt", Arial, sans-serif;
        }

        input[type=text]:focus, textarea:focus, select:focus {
            outline: none; border-color: #4ecdc4; box-shadow: 0 0 0 3px rgba(78, 205, 196, 0.1);
        }

        textarea { resize: vertical; min-height: 72px; }
        
        .modal-footer {
            text-align: right; margin-top: 18px; padding-top: 14px;
            border-top: 1px solid #f4f4f4; display: flex; gap: 12px;
            justify-content: flex-end; align-items: center;
        }

        /* Styled modal action buttons */
        #btnCancel {
            background: transparent; border: 1px solid #d0d7d4; color: #334;
            padding: 10px 18px; border-radius: 10px; cursor: pointer; font-weight: 600;
        }
        #btnCancel:hover { background: #f7f9f8; }

        #btnSave {
            background: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
            color: #fff; padding: 10px 18px; border-radius: 10px; border: none; cursor: pointer; font-weight: 700;
            box-shadow: 0 6px 18px rgba(68,160,141,0.18);
        }
        #btnSave:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(68,160,141,0.22); }

        /* Responsive Design */
        @media (max-width: 768px) {
            body { padding: 10px; }
            .container { padding: 20px; border-radius: 15px; }
            h2 { font-size: 22px; }
            
            .toolbar { flex-direction: column; align-items: stretch; gap: 10px; }
            .toolbar button, #hospitalSearch { width: 100%; box-sizing: border-box; }

            /* Table Responsive setup */
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; top: -9999px; left: -9999px; }
            tr { border: 1px solid #e0e0e0; margin-bottom: 15px; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1); background: white; }
            
            /* Cell (td) layout */
            td {
                border: none; border-bottom: 1px solid #f0f0f0; position: relative;
                padding-left: 120px; padding-top: 12px; padding-bottom: 12px;
                text-align: left; min-height: 45px; display: flex; align-items: center; 
            }
            td:last-child { border-bottom: none; }

            /* Label (::before) styling */
            td:before {
                position: absolute; top: 0; left: 0; width: 110px; 
                padding: 12px 0 12px 15px; white-space: nowrap; 
                content: attr(data-label); font-weight: 600; text-align: left;
                color: #4ecdc4; font-size: 13px; display: flex;
                align-items: center; height: 100%; box-sizing: border-box;
                background: rgba(78, 205, 196, 0.05);
            }
            
            /* Actions cell special adjustment for Mobile */
            td.actions {
                text-align: left; 
                padding-left: 15px; 
                display: block; 
                height: auto;
                min-height: auto;
                padding-top: 15px;
            }
            td.actions:before { 
                content: "จัดการ";
                width: 100%; position: static; padding-bottom: 8px;
                display: block; height: auto; background: transparent;
                color: #4ecdc4; font-weight: 600;
            }
            td.actions .actions { 
                display: flex; flex-direction: column; 
                gap: 10px; align-items: stretch;
            }
            td.actions button {
                width: 100%;
            }

            /* Form Modal adjustment for mobile */
            .modal .panel { padding: 20px; }
            .form-row { display: block; gap: 0; }
            .form-row > label { width: auto; margin-bottom: 8px; display: block; }
            input[type=text], textarea, select { width: 100%; }
        }
    </style>
</head>
<body>

<div class="container">
<div class="page-header">
    <img src="img/doctor.png" alt="doctor" class="doctor-img">
    <h2>ผู้ป่วยรอส่งกลับไปรักษาต่อ</h2>
    <img src="img/ambulance.png" alt="doctor" class="ambulance-img">
</div>
<div class="toolbar">
    <button id="btnAdd">➕ เพิ่มข้อมูล</button>
    <input type="text" id="hospitalSearch" placeholder="🔍 ค้นหาชื่อโรงพยาบาลหรือรหัส">
</div>

<table>
    <thead>
        <tr>
         <th>วันที่</th>
            <th>ชื่อ</th>
            <th>นามสกุล</th>
            <th>ตึก</th>
            <th>โรงพยาบาล</th>
            <th>อุปกรณ์ที่ใช้</th>
            <th>พันธมิตร</th>
            <th>หมายเหตุ</th>
            <th>เวลาประสาน</th>
            <th>สถานะ</th>
            <th>จัดการ</th>
        </tr>
    </thead>
    <tbody id="dataTableBody">
    <?php 
    $hasData = false;
    foreach ($groupedRows as $status => $rows) {
        if (!empty($rows)) {
            $hasData = true;
            // Group header
            ?>
            <tr class="group-header status-group-<?= $status ?>" data-group-status="<?= $status ?>">
                <td colspan="11" class="group-header-cell">
                    <strong>กลุ่มที่ <?= $status ?>: <?= $statusLabels[$status] ?></strong>
                    <span class="group-count">(<?= count($rows) ?> รายการ)</span>
                </td>
            </tr>
            <?php
            // Group rows
            foreach ($rows as $r): ?>
                <tr data-status="<?= e($r['status']) ?>">
                    <td data-label="DATE"><?= e($r['date_in']) ?></td>
                    <td data-label="NAME"><?= e($r['name']) ?></td>
                    <td data-label="SURNAME"><?= e($r['surname']) ?></td>
                    <td data-label="WARD"><?= e($r['ward']) ?></td>
                    <td data-label="HOSPITAL"><?= e($r['hospital']) ?></td> 
                    <td data-label="O2/ETT/ICD"><?= e($r['o2_ett_icd']) ?></td>
                    <td data-label="พันธมิตร"><?= e($r['partner']) ?></td>
                    <td data-label="หมายเหตุ"><?= e($r['note']) ?></td>
                    <td data-label="เวลาประสาน"><?= e($r['contact_time']) ?></td>
                    <td data-label="สถานะ" class="status-<?= e($r['status']) ?>">
                        <?= $statusLabels[$r['status']] ?? '-' ?>
                    </td>
                    <td class="actions" data-label="จัดการ">
                        <div class="actions">
                            <button class="btnEdit" data-row='<?= json_encode($r, JSON_HEX_APOS|JSON_HEX_QUOT) ?>'>แก้ไข</button>
                            <button class="btnDelete" data-id="<?= (int)$r['id'] ?>">ลบ</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach;
        }
    }
    if (!$hasData): ?>
        <tr><td colspan="11" style="text-align:center;">ไม่มีข้อมูล</td></tr>
    <?php endif; ?>
    </tbody>
</table>
</div>

<div id="deleteConfirmModal" class="deleteConfirmModal">
    <div class="confirm-panel">
        <h4>⚠️ ยืนยันการลบ</h4>
        <p>คุณแน่ใจหรือไม่ว่าต้องการลบข้อมูลนี้?</p>
        <div class="confirm-buttons">
            <button class="btn-cancel" id="btnConfirmCancel">ยกเลิก</button>
            <button class="btn-delete" id="btnConfirmDelete">ลบข้อมูล</button>
        </div>
    </div>
</div>

<div id="modal" class="modal">
    <div class="panel">
        <h3 id="modalTitle">เพิ่มข้อมูล</h3>
        <form id="theForm" method="post">
            <input type="hidden" name="action" value="add" id="formAction">
            <input type="hidden" name="id" id="formId">

            <div class="form-grid">
                <div class="form-row"><label for="date_in">DATE</label><input type="text" name="date_in" id="date_in" placeholder=""></div>

                <div class="form-row"><label for="name">NAME</label><input type="text" name="name" id="name"></div>
                <div class="form-row"><label for="surname">SURNAME</label><input type="text" name="surname" id="surname"></div>

                <div class="form-row"><label for="ward">WARD</label><input type="text" name="ward" id="ward"></div>
                <div class="form-row"><label for="hospital">HOSPITAL</label><input type="text" name="hospital" id="hospital"></div>

                <div class="form-row"><label for="o2_ett_icd">O2/ETT/ICD</label><input type="text" name="o2_ett_icd" id="o2_ett_icd"></div>
                <div class="form-row"><label for="partner">พันธมิตร</label><input type="text" name="partner" id="partner"></div>

                <div class="form-row full"><label for="note">หมายเหตุ</label><textarea name="note" id="note" rows="3"></textarea></div>

                <div class="form-row"><label for="time_contact">เวลาประสาน</label><input type="text" name="time_contact" id="time_contact"></div>

                <div class="form-row"><label for="status">สถานะ</label>
                    <select name="status" id="status">
                        <option value="1">รอรถเข้ารับ</option>
                        <option value="2">รถกำลังมารับ</option>
                        <option value="3">บุรีรัมย์ไปส่ง</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" id="btnCancel">ยกเลิก</button>
                <button type="submit" id="btnSave">บันทึก</button>
            </div>
        </form>
    </div>
</div>

<script>
const zipcodeMap = <?= json_encode($zipcodeMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?> || {};

function getHospitalsFromZipInput(inputValue) {
    const filter = inputValue.trim().toLowerCase();
    if (!filter) return null;
    const matches = [];
    Object.entries(zipcodeMap).forEach(([zip, hospitals]) => {
        if (!zip) return;
        if (zip.toLowerCase().startsWith(filter)) {
            (hospitals || []).forEach(name => {
                const lower = (name || '').toLowerCase();
                if (lower && !matches.includes(lower)) {
                    matches.push(lower);
                }
            });
        }
    });
    return matches.length ? matches : null;
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('hospitalSearch');
    const tableBody = document.getElementById('dataTableBody');
    // คอลัมน์ HOSPITAL อยู่ลำดับที่ 4 (นับจาก 0)
    const hospitalColumnIndex = 4; 

    // *** Search Filter ***
    searchInput.addEventListener('keyup', function() {
        const rawFilter = searchInput.value.trim().toLowerCase();
        const rows = tableBody.getElementsByTagName('tr');

        const hospitalsFromZip = getHospitalsFromZipInput(rawFilter);

        const groupHasVisibleRows = {};

        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];

            // Skip group headers for now
            if (row.classList.contains('group-header')) {
                continue;
            }

            if (rows[i].children.length > 1) {
                const hospitalCell = rows[i].getElementsByTagName('td')[hospitalColumnIndex];
                
                if (hospitalCell) {
                    const hospitalText = hospitalCell.textContent || hospitalCell.innerText;
                    const hospitalLower = hospitalText.toLowerCase();

                    let matchesHospital = true;
                    if (rawFilter) {
                        matchesHospital = hospitalLower.indexOf(rawFilter) > -1;
                    }

                    let matchesZip = false;
                    if (hospitalsFromZip && hospitalsFromZip.length) {
                        matchesZip = hospitalsFromZip.some(name => hospitalLower.indexOf(name) > -1);
                    }
                    
                    if (!rawFilter || matchesHospital || matchesZip) {
                        rows[i].style.display = "";
                        const rowStatus = row.getAttribute('data-status');
                        if (rowStatus) {
                            groupHasVisibleRows[rowStatus] = true;
                        }
                    } else {
                        rows[i].style.display = "none";
                    }
                }
            }
        }

        // Update group headers visibility
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            if (row.classList.contains('group-header')) {
                const groupStatus = row.getAttribute('data-group-status');
                if (!rawFilter) {
                    row.style.display = "";
                } else {
                    if (groupHasVisibleRows[groupStatus]) {
                        row.style.display = "";
                    } else {
                        row.style.display = "none";
                    }
                }
            }
        }
    });

    // *** Modal Functions ***
    document.getElementById('btnAdd').addEventListener('click', function(){
        document.getElementById('modalTitle').textContent = 'เพิ่มข้อมูล';
        document.getElementById('formAction').value = 'add';
        document.getElementById('formId').value = '';

        // Clear form fields
        ['date_in','name','surname','ward','hospital','o2_ett_icd','partner','note','time_contact']
            .forEach(id => { document.getElementById(id).value = ''; });
        
        document.getElementById('status').value = 1;

        document.getElementById('modal').style.display = 'flex';
    });

    document.getElementById('btnCancel').addEventListener('click', function(){
        document.getElementById('modal').style.display = 'none';
    });

    document.querySelectorAll('.btnEdit').forEach(function(btn){
        btn.addEventListener('click', function(){
            var row = JSON.parse(this.getAttribute('data-row'));

            document.getElementById('modalTitle').textContent = 'แก้ไขข้อมูล';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formId').value = row.id;

            // *** แก้ไข: ใช้ค่า date_in ที่มาจากฐานข้อมูลโดยตรง ไม่ต้องแปลง ***
            document.getElementById('date_in').value = row.date_in || '';
            
            document.getElementById('name').value = row.name || '';
            document.getElementById('surname').value = row.surname || '';
            document.getElementById('ward').value = row.ward || '';
            document.getElementById('hospital').value = row.hospital || '';
            document.getElementById('o2_ett_icd').value = row.o2_ett_icd || '';
            document.getElementById('partner').value = row.partner || '';
            document.getElementById('note').value = row.note || '';
            document.getElementById('time_contact').value = row.contact_time || '';
            document.getElementById('status').value = row.status || 1;

            document.getElementById('modal').style.display = 'flex';
        });
    });

    // *** Delete Confirmation ***
    let deleteId = null;
    document.querySelectorAll('.btnDelete').forEach(function(btn){
        btn.addEventListener('click', function(e){
            e.preventDefault();
            deleteId = this.getAttribute('data-id');
            document.getElementById('deleteConfirmModal').style.display = 'flex';
        });
    });

    document.getElementById('btnConfirmCancel').addEventListener('click', function(){
        document.getElementById('deleteConfirmModal').style.display = 'none';
        deleteId = null;
    });

    document.getElementById('btnConfirmDelete').addEventListener('click', function(){
        if (deleteId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete">' +
                           '<input type="hidden" name="id" value="' + deleteId + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });

    // Close delete modal when clicking outside
    document.getElementById('deleteConfirmModal').addEventListener('click', function(e){
        if (e.target === this) {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            deleteId = null;
        }
    });

    // Close modal when clicking outside
    document.getElementById('modal').addEventListener('click', function(e){
        if (e.target === this) document.getElementById('modal').style.display='none';
    });
});
</script>

</body>
</html>