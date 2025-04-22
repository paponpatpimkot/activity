<?php
// ========================================================================
// ไฟล์: search_advisors.php
// หน้าที่: ค้นหา Advisor ตามคำค้น และส่งผลลัพธ์เป็น JSON
// ========================================================================

session_start();
require 'db_connect.php'; // ตรวจสอบ Path ให้ถูกต้อง

// --- Authorization Check (ควรตรวจสอบว่าเป็น Admin หรือมีสิทธิ์) ---
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { // อนุญาตเฉพาะ Admin
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// --- Get Search Term ---
$searchTerm = $_GET['term'] ?? ''; // รับคำค้นจาก GET parameter 'term'
$results = [];

if (strlen(trim($searchTerm)) >= 1) { // เริ่มค้นหาเมื่อมีอย่างน้อย 1 ตัวอักษร
    $searchTermLike = "%" . $mysqli->real_escape_string(trim($searchTerm)) . "%";

    // ค้นหาจาก first_name หรือ last_name ที่มี role_id = 2 (Advisor)
    $sql = "SELECT id, first_name, last_name
            FROM users
            WHERE role_id = 2 AND (first_name LIKE ? OR last_name LIKE ?)
            LIMIT 10"; // จำกัดจำนวนผลลัพธ์

    $stmt = $mysqli->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ss', $searchTermLike, $searchTermLike);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            // สร้าง label สำหรับแสดงผล (อาจจะรวม username ด้วยก็ได้)
            $label = $row['first_name'] . ' ' . $row['last_name'];
            $results[] = [
                'id' => $row['id'],         // User ID ของ Advisor
                'value' => $label,        // ข้อความที่จะแสดงในช่องค้นหาหลังเลือก (ถ้าใช้ autocomplete UI)
                'label' => $label         // ข้อความที่จะแสดงใน list ผลลัพธ์
            ];
        }
        $stmt->close();
    } else {
        // สามารถส่ง error กลับไปเป็น JSON ได้
        // $results = ['error' => 'Database query error: ' . $mysqli->error];
        // หรือจะปล่อยเป็น array ว่างก็ได้
         error_log("Search Advisor Prepare Error: " . $mysqli->error); // Log error ไว้ฝั่ง server
    }
}

// --- Output JSON Response ---
header('Content-Type: application/json');
echo json_encode($results);
exit;

?>
