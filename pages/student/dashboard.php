<?php
$student_user_id = $_SESSION['user_id'];
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน


// --- Fetch Student Required Hours (แก้ไข Query) ---
// ดึง required_hours จากตาราง levels ผ่าน group_id ของนักศึกษา
$sql_req_hours = "SELECT l.default_required_hours as required_hours
                  FROM students s
                  JOIN student_groups sg ON s.group_id = sg.id
                  JOIN levels l ON sg.level_id = l.id
                  WHERE s.user_id = ?";
$stmt_req_hours = $mysqli->prepare($sql_req_hours);
if ($stmt_req_hours) {
  $stmt_req_hours->bind_param('i', $student_user_id);
  $stmt_req_hours->execute();
  $result_req_hours = $stmt_req_hours->get_result();
  if ($req_data = $result_req_hours->fetch_assoc()) {
    $required_hours = $req_data['required_hours'] ?? 0; // ใช้ค่าที่ดึงมา หรือ 0 ถ้าไม่พบ
  } else {
    // ไม่พบข้อมูลกลุ่ม/level ของนักศึกษา อาจจะยังไม่ได้กำหนดกลุ่ม
    $message .= '<p class="alert alert-warning text-white">ไม่พบข้อมูลระดับชั้นของนักศึกษา ไม่สามารถคำนวณชั่วโมงที่ต้องเก็บได้</p>';
  }
  $stmt_req_hours->close();
} else {
  $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลชั่วโมงที่ต้องเก็บ: ' . htmlspecialchars($mysqli->error) . '</p>';
}



/* --- Fetch Student Data ---
$student_info = null;
$required_hours = 0;
$sql_student = "SELECT required_hours FROM students WHERE user_id = ?";
$stmt_student = $mysqli->prepare($sql_student);
if ($stmt_student) {
  $stmt_student->bind_param('i', $student_user_id);
  $stmt_student->execute();
  $result_student = $stmt_student->get_result();
  if ($student_info = $result_student->fetch_assoc()) {
    $required_hours = $student_info['required_hours'];
  } else {
    $message .= '<p class="alert alert-danger text-white">ไม่พบข้อมูลนักศึกษา</p>';
  }
  $stmt_student->close();
} else {
  $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลนักศึกษา: ' . htmlspecialchars($mysqli->error) . '</p>';
}
*/

// --- Calculate Earned Hours (จาก Attendance - ปรับปรุง Query) ---
$total_earned_hours = 0.0;
// *** แก้ไข SQL: SUM ค่า hours_earned ทั้งหมด โดยไม่ต้องกรอง status = 'attended' ***
$sql_earned = "SELECT SUM(hours_earned) as total_earned
               FROM activity_attendance
               WHERE student_user_id = ?";
$stmt_earned = $mysqli->prepare($sql_earned);
if ($stmt_earned) {
  $stmt_earned->bind_param('i', $student_user_id);
  $stmt_earned->execute();
  $result_earned = $stmt_earned->get_result();
  if ($row_earned = $result_earned->fetch_assoc()) {
    // ยอดรวมจะเป็นค่าสุทธิ (บวกจาก attended, ลบจาก absent ที่มี penalty)
    $total_earned_hours = $row_earned['total_earned'] ?? 0.0;
  }
  $stmt_earned->close();
} else {
  $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการคำนวณชั่วโมงสะสม: ' . htmlspecialchars($mysqli->error) . '</p>';
}

/*---------------------------
$total_earned_hours = 0.0;
$sql_earned = "SELECT SUM(hours_earned) as total_earned
               FROM activity_attendance
               WHERE student_user_id = ? AND attendance_status = 'attended'"; // นับเฉพาะที่เข้าร่วมจริง
$stmt_earned = $mysqli->prepare($sql_earned);
if ($stmt_earned) {
  $stmt_earned->bind_param('i', $student_user_id);
  $stmt_earned->execute();
  $result_earned = $stmt_earned->get_result();
  if ($row_earned = $result_earned->fetch_assoc()) {
    $total_earned_hours = $row_earned['total_earned'] ?? 0.0;
  }
  $stmt_earned->close();
} else {
  $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการคำนวณชั่วโมงสะสม: ' . htmlspecialchars($mysqli->error) . '</p>';
}
*/

// --- Calculate Booked Hours (แก้ไข Query: นับเฉพาะที่ยังไม่เช็คชื่อ) ---
$total_booked_hours = 0.0;
// *** เพิ่มเงื่อนไข NOT EXISTS เพื่อไม่นับกิจกรรมที่เช็คชื่อแล้ว ***
$sql_booked = "SELECT SUM(a.hours_participant) as total_booked
               FROM activity_bookings b
               JOIN activities a ON b.activity_id = a.id
               WHERE b.student_user_id = ? AND b.status = 'booked'
               AND NOT EXISTS (
                   SELECT 1 FROM activity_attendance att
                   WHERE att.student_user_id = b.student_user_id
                   AND att.activity_id = b.activity_id
               )";
$stmt_booked = $mysqli->prepare($sql_booked);
if ($stmt_booked) {
  $stmt_booked->bind_param('i', $student_user_id);
  $stmt_booked->execute();
  $result_booked = $stmt_booked->get_result();
  if ($row_booked = $result_booked->fetch_assoc()) {
    $total_booked_hours = $row_booked['total_booked'] ?? 0.0;
  }
  $stmt_booked->close();
} else {
  $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการคำนวณชั่วโมงที่จอง: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- Calculate Remaining Hours ---
$remaining_hours = $required_hours - $total_earned_hours;
// ทำให้ไม่ติดลบ (ถ้าทำเกิน)
$remaining_hours = max(0, $remaining_hours);

// --- Calculate Percentages ---
$earned_percentage = 0;
$remaining_percentage = 0; // เพิ่มตัวแปรสำหรับ % ที่ยังขาด
if ($required_hours > 0) {
  // คำนวณ % ที่ทำได้
  $earned_percentage = round(($total_earned_hours / $required_hours) * 100);
  $earned_percentage = min(100, $earned_percentage); // จำกัดไม่เกิน 100%

  // คำนวณ % ที่ยังขาด
  $remaining_percentage = 100 - $earned_percentage;
  // หรือคำนวณโดยตรง: $remaining_percentage = round(($remaining_hours / $required_hours) * 100);
  // $remaining_percentage = max(0, $remaining_percentage); // ป้องกันค่าติดลบ (ไม่ควรเกิดถ้า earned_percentage ถูกต้อง)

} else {
  // กรณี required_hours เป็น 0 อาจจะให้ remaining เป็น 0 หรือ 100 ก็ได้
  $remaining_percentage = ($total_earned_hours <= 0) ? 0 : 100; // ถ้ายังไม่ได้ทำชม.เลย = ขาด 100% (ถ้า required=0)
}

?>
<!--Dashboard -->
<div class="row mt-5">
  <div class="ms-3">
    <h5 class="mb-0 h4 font-weight-bolder">ยินดีต้อนรับ<span class="text-primary"><?php echo '  ' . $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] ?></span></h5>
    <p class="mb-4">

    </p>
  </div>
  <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
    <div class="card">
      <div class="card-header p-2 ps-3">
        <div class="d-flex justify-content-between">
          <div>
            <p class="text-sm mb-0 text-capitalize">ชั่วโมงที่ต้องสะสม</p>
            <h4 class="mb-0"><?php echo number_format($required_hours, 0); ?></h4>
          </div>
          <div class="icon icon-md icon-shape bg-gradient-primary shadow-dark shadow text-center border-radius-lg">
            <i class="material-symbols-rounded opacity-10">weekend</i>
          </div>
        </div>
      </div>
      <hr class="dark horizontal my-0">
      <div class="card-footer p-2 ps-3">
        <p class="mb-0 text-sm"><span class="text-success font-weight-bolder">100% </span></p>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
    <div class="card">
      <div class="card-header p-2 ps-3">
        <div class="d-flex justify-content-between">
          <div>
            <p class="text-sm mb-0 text-capitalize">ชั่วโมงที่เก็บแล้ว</p>
            <h4 class="mb-0"><?php echo number_format($total_earned_hours, 0); ?></h4>
          </div>
          <div class="icon icon-md icon-shape bg-gradient-info shadow-dark shadow text-center border-radius-lg">
            <i class="material-symbols-rounded opacity-10">person</i>
          </div>
        </div>
      </div>
      <hr class="dark horizontal my-0">
      <div class="card-footer p-2 ps-3">
        <p class="mb-0 text-sm"><span class="text-success font-weight-bolder"><?php echo $earned_percentage; ?>% &nbsp;</span>ของชั่วโมงที่ต้องสะสม</p>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
    <div class="card">
      <div class="card-header p-2 ps-3">
        <div class="d-flex justify-content-between">
          <div>
            <p class="text-sm mb-0 text-capitalize">ชั่วโมงที่ยังขาด</p>
            <h4 class="mb-0"><?php echo number_format($remaining_hours, 0); ?></h4>
          </div>
          <div class="icon icon-md icon-shape bg-gradient-success shadow-dark shadow text-center border-radius-lg">
            <i class="material-symbols-rounded opacity-10">leaderboard</i>
          </div>
        </div>
      </div>
      <hr class="dark horizontal my-0">
      <div class="card-footer p-2 ps-3">
        <p class="mb-0 text-sm"><span class="text-danger font-weight-bolder"><?php echo $remaining_percentage; ?>% </span>ที่ยังต้องสะสม</p>
      </div>
    </div>
  </div>
  <div class="col-xl-3 col-sm-6">
    <div class="card">
      <div class="card-header p-2 ps-3">
        <div class="d-flex justify-content-between">
          <div>
            <p class="text-sm mb-0 text-capitalize">ชั่วโมงที่จองแล้ว</p>
            <h4 class="mb-0"><?php echo number_format($total_booked_hours, 0); ?></h4>
          </div>
          <div class="icon icon-md icon-shape bg-gradient-danger shadow-dark shadow text-center border-radius-lg">
            <i class="material-symbols-rounded opacity-10">weekend</i>
          </div>
        </div>
      </div>
      <hr class="dark horizontal my-0">
      <div class="card-footer p-2 ps-3">
        <a class="mb-0 text-sm text-success font-weight-bolder" href="">คลิกเพื่อตรวจสอบรายการจอง</ฟ>
      </div>
    </div>
  </div>
</div>

<!-- table -->
<?php include 'student_activity.php'; ?>