<?php
// ========================================================================
// ไฟล์: download_template.php
// หน้าที่: สร้างและส่งไฟล์ CSV ตัวอย่างตามประเภทข้อมูลที่ร้องขอ
// ========================================================================

session_start();
// ไม่จำเป็นต้อง include db_connect.php ถ้าไม่ต้องการข้อมูลจาก DB สำหรับสร้าง template

// --- Authorization Check ---
// ควรตรวจสอบสิทธิ์ผู้ใช้ที่นี่ (เช่น ต้องเป็น Admin)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
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
        $sample_data = [
             ['20127', 'เทคนิคคอมพิวเตอร์']
        ];
        break;

    case 'student_groups':
        $filename = "template_student_groups.csv";
        $header = ['GroupCode', 'GroupName', 'LevelCode', 'MajorCode', 'AdvisorUsernames'];
        $sample_data = [
             ['G001', 'สท.1/1', 'PVC1', '20127', 'advisor01,advisor02'],
             ['G002', 'ชก.3/1', 'PVC3', '20106', 'advisor03']
        ];
        break;

    case 'users':
        $filename = "template_users.csv";
        $header = ['Username', 'Password', 'FirstName', 'LastName', 'Email', 'RoleName'];
        $sample_data = [
            ['student01', 'ชั่วคราว123', 'สมชาย', 'เรียนเก่ง', 'somchai.r@example.com', 'student'],
            ['advisor03', 'tempPass@456', 'สมศรี', 'สอนดี', 'somsri.s@example.com', 'advisor'],
            ['staff01', 'staffPass789', 'สมหมาย', 'ใจดี', '', 'staff']
        ];
        break;

     case 'students':
        $filename = "template_students.csv";
        $header = ['Username', 'StudentIDNumber', 'GroupCode'];
        $sample_data = [
            ['student01', '6630127001', 'G001'],
            ['student02', '6630106005', 'G002']
        ];
        break;

    // --- เพิ่ม Case สำหรับ activity_units ---
    case 'activity_units':
        $filename = "template_activity_units.csv";
        $header = ['UnitName', 'UnitType'];
        $sample_data = [
            ['งานกิจกรรมนักศึกษา', 'Internal'], // ตัวอย่าง
            ['แผนกวิชาช่างยนต์', 'Internal'], // ตัวอย่าง
            ['บริษัท ABC จำกัด', 'External']   // ตัวอย่าง
        ];
        break;

    default:
        header('HTTP/1.1 400 Bad Request');
        exit('Invalid template type requested.');
}

// --- Set Headers for CSV Download ---
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// --- Open output stream ---
$output = fopen('php://output', 'w');

// --- Add UTF-8 BOM for Excel ---
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// --- Write Header ---
fputcsv($output, $header);

// --- Write Sample Data (Optional) ---
if (!empty($sample_data)) {
    foreach ($sample_data as $row) {
        fputcsv($output, $row);
    }
}

exit();

?>
