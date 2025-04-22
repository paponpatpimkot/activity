<?php
// ========================================================================
// ไฟล์: admin_dashboard_v2.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดง Dashboard สรุปข้อมูลสำหรับ Admin
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก และมีการกำหนด $_SESSION['user_id'], $_SESSION['role_id'] ---
// --- และ Controller ได้ require_once 'includes/functions.php' แล้ว ---

// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "Dashboard";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- 1. คำนวณข้อมูลสำหรับ Cards ---
$total_students = 0;
$students_100_percent = 0;
$students_over_50_percent = 0;
$students_under_50_percent = 0;

// Query ข้อมูลนักศึกษา ชั่วโมงที่ต้องเก็บ และชั่วโมงสะสม (Optimized)
$sql_student_hours = "SELECT
                          s.user_id,
                          l.default_required_hours AS required_hours,
                          COALESCE(SUM(aa.hours_earned), 0) AS total_earned_hours
                      FROM students s
                      JOIN users u ON s.user_id = u.id AND u.role_id = 3 -- Ensure it's a student user
                      LEFT JOIN student_groups sg ON s.group_id = sg.id
                      LEFT JOIN levels l ON sg.level_id = l.id
                      LEFT JOIN activity_attendance aa ON s.user_id = aa.student_user_id
                      GROUP BY s.user_id, l.default_required_hours";

$result_student_hours = $mysqli->query($sql_student_hours);

if ($result_student_hours) {
  $total_students = $result_student_hours->num_rows; // จำนวนนักศึกษาทั้งหมด

  while ($student_data = $result_student_hours->fetch_assoc()) {
    $required = $student_data['required_hours'] ?? 0;
    $earned = $student_data['total_earned_hours'] ?? 0;
    $percentage = 0;

    if ($required > 0) {
      $percentage = round(($earned / $required) * 100);
    } elseif ($earned > 0) {
      $percentage = 100; // ถ้า required = 0 แต่มีชั่วโมงสะสม ถือว่าครบ 100%
    }

    if ($percentage >= 100) {
      $students_100_percent++;
    }
    // นับ >50% โดยไม่รวมคนที่ครบ 100% ไปแล้ว
    if ($percentage > 50 && $percentage < 100) {
      $students_over_50_percent++;
    }
    if ($percentage < 50) {
      $students_under_50_percent++;
    }
  }
  $result_student_hours->free();
} else {
  $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลสรุปชั่วโมงนักศึกษา: ' . htmlspecialchars($mysqli->error) . '</p>';
}


// --- 2. ดึงข้อมูลกิจกรรมในอนาคต (แก้ไข Query) ---
$future_activities = []; // เปลี่ยนชื่อตัวแปร
// แก้ไข Query ให้ดึงกิจกรรมที่ยังไม่เริ่ม
$sql_future = "SELECT a.id, a.name, a.start_datetime, au.name as unit_name
               FROM activities a
               LEFT JOIN activity_units au ON a.organizer_unit_id = au.id
               WHERE a.start_datetime > NOW() -- กิจกรรมที่ยังไม่ถึงเวลาเริ่ม
               ORDER BY a.start_datetime ASC"; // เรียงตามลำดับที่จะเกิดขึ้นก่อน

$result_future = $mysqli->query($sql_future); // เปลี่ยนชื่อตัวแปร result
if ($result_future) {
  while ($row = $result_future->fetch_assoc()) {
    $future_activities[] = $row; // เปลี่ยนชื่อตัวแปร array
  }
  $result_future->free();
} else {
  $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกิจกรรมในอนาคต: ' . htmlspecialchars($mysqli->error) . '</p>';
}


// --- จัดการ Message จาก Session ---
if (isset($_SESSION['form_message'])) {
  $message = $_SESSION['form_message'];
  unset($_SESSION['form_message']);
}

?>

<div class="container-fluid py-4">

  <?php if (!empty($message)) : ?>
    <div class="row mb-3">
      <div class="col-12">
        <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
          <?php echo $message; ?>
          <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div class="row">
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
      <div class="card">
        <div class="card-header p-2 ps-3">
          <div class="d-flex justify-content-between">
            <div>
              <p class="text-sm mb-0 text-capitalize">นักศึกษาทั้งหมด</p>
              <h4 class="mb-0"><?php echo number_format($total_students); ?> คน</h4>
            </div>
            <div class="icon icon-md icon-shape bg-gradient-info shadow-dark shadow text-center border-radius-lg">
              <i class="material-symbols-rounded opacity-10">group</i>
            </div>
          </div>
        </div>
        <hr class="dark horizontal my-0">
        <div class="card-footer p-2 ps-3">
          <p class="mb-0 text-sm">&nbsp;</p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
      <div class="card">
        <div class="card-header p-2 ps-3">
          <div class="d-flex justify-content-between">
            <div>
              <p class="text-sm mb-0 text-capitalize">เก็บชั่วโมงครบ (100%)</p>
              <h4 class="mb-0"><?php echo number_format($students_100_percent); ?> คน</h4>
            </div>
            <div class="icon icon-md icon-shape bg-gradient-success shadow-dark shadow text-center border-radius-lg">
              <i class="material-symbols-rounded opacity-10">check_circle</i>
            </div>
          </div>
        </div>
        <hr class="dark horizontal my-0">
        <div class="card-footer p-2 ps-3">
          <p class="mb-0 text-sm">
            <?php echo ($total_students > 0) ? round(($students_100_percent / $total_students) * 100) : 0; ?>% ของทั้งหมด
          </p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
      <div class="card">
        <div class="card-header p-2 ps-3">
          <div class="d-flex justify-content-between">
            <div>
              <p class="text-sm mb-0 text-capitalize">เก็บชั่วโมง > 50%</p>
              <h4 class="mb-0"><?php echo number_format($students_over_50_percent); ?> คน</h4>
            </div>
            <div class="icon icon-md icon-shape bg-gradient-warning shadow-dark shadow text-center border-radius-lg">
              <i class="material-symbols-rounded opacity-10">hourglass_top</i>
            </div>
          </div>
        </div>
        <hr class="dark horizontal my-0">
        <div class="card-footer p-2 ps-3">
          <p class="mb-0 text-sm">
            <?php echo ($total_students > 0) ? round(($students_over_50_percent / $total_students) * 100) : 0; ?>% ของทั้งหมด
          </p>
        </div>
      </div>
    </div>

    <div class="col-xl-3 col-sm-6">
      <div class="card">
        <div class="card-header p-2 ps-3">
          <div class="d-flex justify-content-between">
            <div>
              <p class="text-sm mb-0 text-capitalize">เก็บชั่วโมง < 50%</p>
                  <h4 class="mb-0"><?php echo number_format($students_under_50_percent); ?> คน</h4>
            </div>
            <div class="icon icon-md icon-shape bg-gradient-danger shadow-dark shadow text-center border-radius-lg">
              <i class="material-symbols-rounded opacity-10">hourglass_bottom</i>
            </div>
          </div>
        </div>
        <hr class="dark horizontal my-0">
        <div class="card-footer p-2 ps-3">
          <p class="mb-0 text-sm">
            <?php echo ($total_students > 0) ? round(($students_under_50_percent / $total_students) * 100) : 0; ?>% ของทั้งหมด
          </p>
        </div>
      </div>
    </div>
  </div>
  <div class="row mt-4">
    <div class="col-12">
      <div class="card my-4">
        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
          <div class="bg-gradient-secondary shadow-secondary border-radius-lg pt-4 pb-3">
            <h6 class="text-white text-capitalize ps-3">กิจกรรมในอนาคต</h6>
          </div>
        </div>
        <div class="card-body px-0 pb-2">
          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ชื่อกิจกรรม</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">หน่วยงานจัด</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">วันเวลาเริ่ม</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($future_activities)) : ?>
                  <?php foreach ($future_activities as $activity) : ?>
                    <tr>
                      <td>
                        <div class="d-flex px-2 py-1">
                          <div class="d-flex flex-column justify-content-center">
                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($activity['name']); ?></h6>
                          </div>
                        </div>
                      </td>
                      <td>
                        <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($activity['unit_name'] ?? 'N/A'); ?></p>
                      </td>
                      <td class="align-middle text-center text-sm">
                        <span class="text-secondary text-xs font-weight-bold"><?php echo format_datetime_th($activity['start_datetime']); ?></span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else : ?>
                  <tr>
                    <td colspan="3" class="text-center p-3">ไม่มีกิจกรรมในอนาคต</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>