<?php
// ========================================================================
// ไฟล์: handle_login.php
// หน้าที่: ประมวลผลข้อมูลล็อกอิน ตรวจสอบกับฐานข้อมูล และสร้าง Session (ใช้ MySQLi)
// ========================================================================

session_start(); // เริ่มต้น Session ก่อนเสมอ

// ตรวจสอบว่ามีการส่งข้อมูลมาจากฟอร์มหรือไม่
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require 'config.php'; // เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล ($mysqli)

    $username = trim($_POST['username']); // รับค่า username และตัดช่องว่าง
    $password = $_POST['password']; // รับค่า password

    // ตรวจสอบว่าค่าที่รับมาไม่ว่างเปล่า
    if (empty($username) || empty($password)) {
        $mysqli->close(); // ปิด connection
        header('Location: login.php?error=1'); // ส่งกลับไปหน้า login พร้อม error
        exit;
    }

    // เตรียมคำสั่ง SQL โดยใช้ Prepared Statements ของ MySQLi
    $sql = "SELECT id, username, password, role_id, first_name, last_name FROM users WHERE username = ? LIMIT 1";

    // เตรียม Statement
    $stmt = $mysqli->prepare($sql);

    if ($stmt === false) {
        // กรณี prepare statement ไม่สำเร็จ
        // error_log("MySQLi prepare error: " . $mysqli->error);
        $mysqli->close(); // ปิด connection
        header('Location: login.php?error=2');
        exit;
    }

    // Bind ค่า username เข้ากับ placeholder ('s' คือ type string)
    $stmt->bind_param('s', $username);

    // Execute คำสั่ง
    if (!$stmt->execute()) {
         // กรณี execute statement ไม่สำเร็จ
        // error_log("MySQLi execute error: " . $stmt->error);
        $stmt->close(); // ปิด statement ก่อน
        $mysqli->close(); // ปิด connection
        header('Location: login.php?error=2');
        exit;
    }

    // ดึงผลลัพธ์
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // พบผู้ใช้งาน
        $user = $result->fetch_assoc(); // ดึงข้อมูลผู้ใช้เป็น associative array

        // ตรวจสอบรหัสผ่านด้วย password_verify()
        if (password_verify($password, $user['password'])) {
            // --- ล็อกอินสำเร็จ ---

            // เก็บข้อมูลผู้ใช้ที่จำเป็นลงใน Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];

            // สร้าง Session ID ใหม่เพื่อป้องกัน Session Fixation
            session_regenerate_id(true);

            // ปิด statement และ connection
            $stmt->close();
            $mysqli->close();

            // --- Redirect ไปยังหน้า Dashboard ตาม role_id ---
            $role_id = $_SESSION['role_id'];
            if ($role_id == 1) {
                header('Location: pages/admin/index.php');
            } elseif ($role_id == 2) {
                header('Location: pages/advisor/index.php');
            } elseif ($role_id == 3) {
                header('Location: pages/student/index.php');
            } elseif ($role_id == 4) {
                header('Location: pages/staff/index.php');
            } else {
                // กรณี Role ไม่ถูกต้อง (ไม่ควรเกิดขึ้นถ้าข้อมูลใน DB ถูกต้อง)
                header('Location: login.php?error=invalid_role');
            }
            exit;

        } else {
            // --- รหัสผ่านไม่ถูกต้อง ---
            $stmt->close();
            $mysqli->close();
            header('Location: login.php?error=1');
            exit;
        }
    } else {
        // --- ไม่พบผู้ใช้งาน ---
        $stmt->close();
        $mysqli->close();
        header('Location: login.php?error=1');
        exit;
    }

} else {
    // ถ้าไม่ได้เข้าถึงหน้านี้ผ่าน POST method ให้ redirect กลับไปหน้า login
    header('Location: login.php');
    exit;
}