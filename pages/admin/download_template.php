<?php
// ========================================================================
// ไฟล์: download_template.php
// หน้าที่: สร้างและส่งไฟล์ CSV ตัวอย่างตามประเภทข้อมูลที่ร้องขอ
// ========================================================================

session_start();
// ไม่จำเป็นต้อง include db_connect.php ถ้าไม่ต้องการข้อมูลจาก DB สำหรับสร้าง template

// --- Authorization Check ---
// ควรตรวจสอบสิทธิ์ผู้ใช้ที่นี่ (เช่น ต้องเป็น Admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { // Admin Only
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}

// --- Get Template Type ---
$type = $_GET['type'] ?? '';

// --- Generate CSV based on type ---
$filename = "template_unknown.csv";
$header = [];
$sample_data = []; // อาจจะใส่ข้อมูลตัวอย่าง 1-2 แถว

switch ($type) {
    case 'majors':
        $filename = "template_majors.csv";
        $header = ['MajorCode', 'MajorName'];
        $sample_data = [ ['20127', 'เทคนิคคอมพิวเตอร์'] ];
        break;

    case 'student_groups':
        $filename = "template_student_groups.csv";
        $header = ['GroupCode', 'GroupName', 'LevelCode', 'MajorCode', 'AdvisorUsernames'];
        $sample_data = [ ['G001', 'สท.1/1', 'PVC1', '20127', 'advisor01,advisor02'] ];
        break;

    case 'users':
        $filename = "template_users.csv";
        $header = ['Username', 'Password', 'FirstName', 'LastName', 'Email', 'RoleName'];
        $sample_data = [ ['newuser01', 'Pass@1234', 'ชื่อจริงใหม่', 'นามสกุลใหม่', 'new.user@example.com', 'student'] ];
        break;

     case 'students':
        $filename = "template_students.csv";
        $header = ['Username', 'StudentIDNumber', 'GroupCode'];
        $sample_data = [ ['student01', '6700000001', 'G001'] ];
        break;

    case 'activity_units':
        $filename = "template_activity_units.csv";
        $header = ['UnitName', 'UnitType'];
        $sample_data = [ ['งานกิจกรรมนักศึกษา', 'Internal'] ];
        break;

    // --- เพิ่ม Case สำหรับ staff ---
    case 'staff':
        $filename = "template_staff.csv";
        // RoleName จะถูกกำหนดเป็น 'staff' โดยอัตโนมัติในโค้ด Import
        $header = ['Username', 'Password', 'FirstName', 'LastName', 'Email', 'EmployeeIDNumber'];
        $sample_data = [
            ['staff02', 'StaffPass1!', 'สมศักดิ์', 'ทำงานดี', 'somsak.t@example.com', 'S002'],
            ['staff03', 'ChangeMe!789', 'มานี', 'ขยัน', '', 'S003'] // Email ว่างได้
        ];
        break;

    default:
        header('HTTP/1.1 400 Bad Request');
        exit('Invalid template type requested.');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$output = fopen('php://output', 'w');
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
fputcsv($output, $header);
if (!empty($sample_data)) {
    foreach ($sample_data as $row) {
        fputcsv($output, $row);
    }
}
exit();
?>
