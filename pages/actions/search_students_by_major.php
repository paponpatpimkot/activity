<?php
// ========================================================================
// ไฟล์: actions/search_students_by_major.php
// หน้าที่: ค้นหานักศึกษาตาม major_id และคำค้นหา แล้วส่งผลลัพธ์เป็น JSON
// ========================================================================

session_start();
// *** ตรวจสอบ Path ของ db_connect.php ให้ถูกต้อง ***
// สมมติว่า db_connect.php อยู่ในโฟลเดอร์แม่ 2 ระดับ จาก actions/ ไปที่ root ของ admin แล้วไปที่ root project
require_once '../admin/db_connect.php'; // ตัวอย่าง Path

// --- Authorization Check (ควรตรวจสอบว่าเป็น Admin หรือมีสิทธิ์ที่เกี่ยวข้อง) ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 4])) { // อนุญาต Admin(1) หรือ Staff(4)
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// --- Get Parameters ---
$major_id = filter_input(INPUT_GET, 'major_id', FILTER_VALIDATE_INT);
$search_term = trim($_GET['term'] ?? '');
$activity_id = filter_input(INPUT_GET, 'activity_id', FILTER_VALIDATE_INT); // รับ activity_id มาด้วย

$results = [];

if (!$major_id) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'Missing or invalid major_id']);
    exit;
}

if (strlen($search_term) >= 1) { // เริ่มค้นหาเมื่อมีอย่างน้อย 1 ตัวอักษร
    $search_term_like = "%" . $mysqli->real_escape_string($search_term) . "%";

    // Query นักศึกษาในสาขาที่กำหนด และยังไม่ได้ถูกเลือกสำหรับกิจกรรมนี้ (ถ้า activity_id ถูกส่งมา)
    // และต้องเป็น User ที่มี Role เป็น Student (role_id = 3)
    // และต้องมีข้อมูลในตาราง students
    $sql = "SELECT u.id, u.first_name, u.last_name, s.student_id_number
            FROM users u
            JOIN students s ON u.id = s.user_id
            JOIN student_groups sg ON s.group_id = sg.id
            WHERE u.role_id = 3
              AND sg.major_id = ?
              AND (u.first_name LIKE ? OR u.last_name LIKE ? OR s.student_id_number LIKE ?)";

    // ถ้ามี activity_id ส่งมา ให้กรองนักศึกษาที่ถูกเลือกไปแล้วสำหรับกิจกรรมนั้นออก
    if ($activity_id) {
        $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM activity_specific_student_eligibility asse
                    WHERE asse.activity_id = ? AND asse.student_user_id = u.id
                  )";
    }

    $sql .= " ORDER BY u.first_name ASC, u.last_name ASC LIMIT 10";

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        if ($activity_id) {
            $stmt->bind_param('isssi', $major_id, $search_term_like, $search_term_like, $search_term_like, $activity_id);
        } else {
            $stmt->bind_param('isss', $major_id, $search_term_like, $search_term_like, $search_term_like);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $label = htmlspecialchars($row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['student_id_number'] . ')');
            $results[] = [
                'id' => $row['id'],         // User ID ของนักศึกษา
                'value' => $label,        // ข้อความที่จะแสดงในช่องค้นหาหลังเลือก (ถ้าใช้ UI ที่รองรับ)
                'label' => $label,        // ข้อความที่จะแสดงใน list ผลลัพธ์
                'student_id_number' => $row['student_id_number'] // ส่งรหัสนักศึกษาไปด้วย
            ];
        }
        $stmt->close();
    } else {
        error_log("Search Students by Major Prepare Error: " . $mysqli->error);
        // ไม่ส่ง error กลับไปตรงๆ แต่จะคืน array ว่าง
    }
}

// --- Output JSON Response ---
header('Content-Type: application/json; charset=utf-8'); // ระบุ charset
echo json_encode($results);
exit;

?>
