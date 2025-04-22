<?php
// ========================================================================
// ไฟล์: logout.php
// หน้าที่: ทำลาย Session และ redirect กลับไปหน้า Login
// (ไม่มีการเปลี่ยนแปลง)
// ========================================================================

session_start(); // เริ่มต้น Session เพื่อเข้าถึงข้อมูล Session

// 1. ลบตัวแปร Session ทั้งหมด
$_SESSION = array();

// 2. ถ้าใช้ Session Cookie, ให้ลบ Cookie ด้วย
// Note: This will destroy the session, and not just the session data!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย Session
session_destroy();

// 4. Redirect กลับไปยังหน้า Login
header("Location: ../../login.php");
exit;
?>