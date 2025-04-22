<?php
// ========================================================================
// ไฟล์: export_csv.php
// หน้าที่: สร้างไฟล์ CSV สรุปข้อมูลชั่วโมงกิจกรรมของนักศึกษาตามกลุ่มเรียน
// ========================================================================

// --- เริ่มต้น Session และ Include ไฟล์ที่จำเป็น ---
session_start();
require 'db_connect.php'; // ไฟล์เชื่อมต่อฐานข้อมูล ($mysqli)

// --- Authorization Check ---
// 1. ตรวจสอบว่าล็อกอินหรือยัง และเป็น Advisor (role_id = 2)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) {
    // ไม่ได้รับอนุญาต - อาจจะ redirect หรือแสดงข้อความ
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}
$advisor_user_id = $_SESSION['user_id'];

// --- Get and Validate Group ID ---
$group_id = filter_input(INPUT_GET, 'group_id', FILTER_VALIDATE_INT);
if (!$group_id) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid Group ID');
}

// --- Verify Advisor's Permission for this Group & Get Group Name (แก้ไข Query) ---
$group_name = null;
// แก้ไข Query: เช็คสิทธิ์ผ่าน group_advisors
$sql_verify_group = "SELECT sg.group_name
                     FROM student_groups sg
                     JOIN group_advisors ga ON sg.id = ga.group_id
                     WHERE sg.id = ? AND ga.advisor_user_id = ?";
$stmt_verify_group = $mysqli->prepare($sql_verify_group);
if ($stmt_verify_group) {
    $stmt_verify_group->bind_param('ii', $group_id, $advisor_user_id);
    $stmt_verify_group->execute();
    $result_verify_group = $stmt_verify_group->get_result();
    if ($group_data = $result_verify_group->fetch_assoc()) {
        $group_name = $group_data['group_name'];
    } else {
        // Advisor ไม่มีสิทธิ์ดูกลุ่มนี้ หรือ group_id ไม่มีอยู่
        header('HTTP/1.1 403 Forbidden');
        exit('Access Denied or Group not found for this advisor');
    }
    $stmt_verify_group->close();
} else {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Database error during group verification');
}

// --- Fetch Student Data for the selected group (แก้ไข Query) ---
$students_export_data = [];
// แก้ไข Query: ดึง required_hours จาก levels
$sql_students = "SELECT
                    s.user_id, s.student_id_number,
                    u.first_name, u.last_name,
                    l.default_required_hours as required_hours -- ดึง required hours จาก levels
                 FROM students s
                 JOIN users u ON s.user_id = u.id
                 JOIN student_groups sg ON s.group_id = sg.id -- Join groups
                 JOIN levels l ON sg.level_id = l.id -- Join levels
                 WHERE s.group_id = ?
                 ORDER BY s.student_id_number ASC";

$stmt_students = $mysqli->prepare($sql_students);
if ($stmt_students) {
    $stmt_students->bind_param('i', $group_id);
    $stmt_students->execute();
    $result_students = $stmt_students->get_result();

    $counter = 1; // สำหรับลำดับที่

    // --- Prepare statement for fetching earned hours (reusable) ---
    // *** หมายเหตุ: การ Query ใน Loop อาจจะช้าถ้ามีนักศึกษาเยอะมาก ***
    $sql_earned = "SELECT SUM(hours_earned) as total_earned
                   FROM activity_attendance
                   WHERE student_user_id = ?";
    $stmt_earned = $mysqli->prepare($sql_earned);

    while ($student = $result_students->fetch_assoc()) {
        $student_user_id = $student['user_id'];
        $total_earned_hours = 0.0;

        // Calculate earned hours for this student
        if ($stmt_earned) {
            $stmt_earned->bind_param('i', $student_user_id);
            $stmt_earned->execute();
            $result_earned = $stmt_earned->get_result();
            if ($row_earned = $result_earned->fetch_assoc()) {
                $total_earned_hours = $row_earned['total_earned'] ?? 0.0;
            }
            // ไม่ต้อง close $stmt_earned ใน loop ถ้าจะใช้ซ้ำ
        }

        // ใช้ required_hours ที่ดึงมาจาก Query หลัก
        $required_hours = $student['required_hours'] ?? 0;
        $remaining_hours = max(0, $required_hours - $total_earned_hours);

        // เก็บข้อมูลสำหรับ Export
        $students_export_data[] = [
            'no' => $counter++,
            'student_id' => $student['student_id_number'],
            'full_name' => $student['first_name'] . ' ' . $student['last_name'],
            'required' => number_format($required_hours, 0), // Format เป็นจำนวนเต็ม
            'earned' => number_format($total_earned_hours, 0), // Format เป็นจำนวนเต็ม
            'remaining' => number_format($remaining_hours, 0) // Format เป็นจำนวนเต็ม
        ];
    }
    if ($stmt_earned) $stmt_earned->close(); // Close statement หลัง Loop
    $stmt_students->close();

} else {
     header('HTTP/1.1 500 Internal Server Error');
     exit('Database error fetching student data');
}

// --- Generate CSV ---
$filename = "สรุปชั่วโมง_กลุ่ม_" . preg_replace('/[^a-zA-Z0-9_\-]/', '_', $group_name) . "_" . date('Ymd') . ".csv";

// Set Headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Open output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility with Thai characters
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add Header Row
$header = ['ลำดับที่', 'รหัสนักศึกษา', 'ชื่อ-สกุล', 'ชม.ที่ต้องเก็บ', 'ชม.สะสม', 'ชม.ที่ยังขาด'];
fputcsv($output, $header);

// Add Data Rows
if (!empty($students_export_data)) {
    foreach ($students_export_data as $row) {
        // จัดลำดับข้อมูลให้ตรงกับ Header
        $csv_row = [
            $row['no'],
            $row['student_id'], // ใส่ ' หน้าตัวเลขถ้าต้องการให้ Excel มองเป็น Text
            $row['full_name'],
            $row['required'],
            $row['earned'],
            $row['remaining']
        ];
        fputcsv($output, $csv_row);
    }
}

exit(); // จบการทำงานหลังจากสร้างไฟล์ CSV

?>
