<?php
// db_connect.php

$servername = "localhost"; // หรือ IP Address ของ Server ฐานข้อมูล
$username = "root";        // Username ของฐานข้อมูล (ค่าเริ่มต้นของ XAMPP คือ root)
$password = "";            // Password ของฐานข้อมูล (ค่าเริ่มต้นของ XAMPP คือ ว่าง)
$dbname = "activity_db";           // ชื่อฐานข้อมูลที่คุณสร้าง

// สร้างการเชื่อมต่อ
$mysqli = mysqli_connect($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if (!$mysqli) {
  die("Connection failed: " . mysqli_connect_error());
}

// ตั้งค่า Character Set เป็น UTF-8 (สำคัญมากสำหรับภาษาไทย)
mysqli_set_charset($mysqli, "utf8mb4");

// สามารถเพิ่มการตั้งค่า Timezone ได้ หากต้องการ
// date_default_timezone_set('Asia/Bangkok');
?>