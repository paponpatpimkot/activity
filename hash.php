<?php
// รหัสผ่านที่คุณต้องการสำหรับ user แต่ละคน
$passwordForAdmin = '1234';
$passwordForAdvisor = 'hashed_password_advisor';
$passwordForStudent = 'hashed_password_student';
$passwordForStaff = 'hashed_password_staff';

// สร้าง Hash
$hashedAdminPassword = password_hash($passwordForAdmin, PASSWORD_DEFAULT);
$hashedAdvisorPassword = password_hash($passwordForAdvisor, PASSWORD_DEFAULT);
$hashedStudentPassword = password_hash($passwordForStudent, PASSWORD_DEFAULT);
$hashedStaffPassword = password_hash($passwordForStaff, PASSWORD_DEFAULT);

// แสดงค่า Hash ที่ได้ (นำค่าเหล่านี้ไปใส่ในฐานข้อมูล)
echo "Admin Hash: " . $hashedAdminPassword . "<br>";
echo "Advisor Hash: " . $hashedAdvisorPassword . "<br>";
echo "Student Hash: " . $hashedStudentPassword . "<br>";
echo "Staff Hash: " . $hashedStaffPassword . "<br>";

/* ตัวอย่างคำสั่ง UPDATE ฐานข้อมูล (ทำผ่าน phpMyAdmin หรือ SQL Client อื่นๆ)
   UPDATE users SET password = '[ค่า Hash ที่ได้สำหรับ Admin]' WHERE username = 'admin';
   UPDATE users SET password = '[ค่า Hash ที่ได้สำหรับ Advisor]' WHERE username = 'advisor01';
   UPDATE users SET password = '[ค่า Hash ที่ได้สำหรับ Student]' WHERE username = 'student01';
   UPDATE users SET password = '[ค่า Hash ที่ได้สำหรับ Staff]' WHERE username = 'staff01';
   -- ทำซ้ำสำหรับผู้ใช้ตัวอย่างอื่นๆ
*/
?>