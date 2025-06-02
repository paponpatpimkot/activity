<?php
// ========================================================================
// ไฟล์: search_staff.php
// หน้าที่: ค้นหา Staff ตามคำค้น และส่งผลลัพธ์เป็น JSON
// ========================================================================

session_start();
// *** ตรวจสอบ Path ของ db_connect.php ให้ถูกต้อง เทียบกับตำแหน่งของไฟล์นี้ ***
require '../admin/db_connect.php'; // สมมติว่า db_connect.php อยู่ในโฟลเดอร์แม่ 1 ระดับ

// --- Authorization Check (ควรตรวจสอบว่าเป็น Admin หรือมีสิทธิ์) ---
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 4])) { // อนุญาต Admin หรือ Staff
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// --- Get Search Term ---
$searchTerm = $_GET['term'] ?? '';
$results = [];

if (strlen(trim($searchTerm)) >= 1) {
    $searchTermLike = "%" . $mysqli->real_escape_string(trim($searchTerm)) . "%";

    // ค้นหาจาก first_name หรือ last_name ที่มี role_id = 4 (Staff)
    $sql = "SELECT id, first_name, last_name, username
            FROM users
            WHERE role_id = 4 AND (first_name LIKE ? OR last_name LIKE ? OR username LIKE ?)
            LIMIT 10"; // จำกัดจำนวนผลลัพธ์

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('sss', $searchTermLike, $searchTermLike, $searchTermLike);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $label = $row['first_name'] . ' ' . $row['last_name'] . ' (' . $row['username'] . ')';
            $results[] = [
                'id' => $row['id'],         // User ID ของ Staff
                'value' => $label,        // ข้อความที่จะแสดงในช่องค้นหาหลังเลือก
                'label' => $label         // ข้อความที่จะแสดงใน list ผลลัพธ์
            ];
        }
        $stmt->close();
    } else {
         error_log("Search Staff Prepare Error: " . $mysqli->error);
    }
}

// --- Output JSON Response ---
header('Content-Type: application/json');
echo json_encode($results);
exit;

?>
