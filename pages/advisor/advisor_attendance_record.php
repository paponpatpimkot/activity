<?php
// ========================================================================
// ไฟล์: advisor_attendance_record.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายชื่อนักศึกษาในที่ปรึกษาที่จองกิจกรรม และบันทึกสถานะการเข้าร่วม
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), Authorization check (Role Advisor) ---
// --- และ require_once 'includes/functions.php' ได้ทำไปแล้วใน Controller หลัก ---

// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 2) { exit('Unauthorized'); }

$advisor_user_id = $_SESSION['user_id'];
$page_title = "บันทึกการเข้าร่วมกิจกรรม";
$message = '';
$activity_id = null;
$activity_info = null;
$advisees_booked = [];
$attendance_data = []; // เก็บข้อมูล attendance ที่มีอยู่แล้ว key คือ user_id

// --- Get Activity ID from URL ---
if (isset($_GET['activity_id']) && is_numeric($_GET['activity_id'])) {
  $activity_id = (int)$_GET['activity_id'];
} else {
  $_SESSION['form_message'] = '<p class="alert alert-warning text-white">กรุณาเลือกกิจกรรมที่ต้องการเช็คชื่อ</p>';
  header('Location: index.php?page=advisor_attendance_select_activity');
  exit;
}

// --- Fetch Activity Info ---
$sql_activity = "SELECT id, name, start_datetime, hours_participant, penalty_hours
                 FROM activities WHERE id = ?";
$stmt_activity = $mysqli->prepare($sql_activity);
if ($stmt_activity) {
  $stmt_activity->bind_param('i', $activity_id);
  $stmt_activity->execute();
  $result_activity = $stmt_activity->get_result();
  if ($result_activity->num_rows === 1) {
    $activity_info = $result_activity->fetch_assoc();
    $page_title .= ' - ' . htmlspecialchars($activity_info['name']);
  } else {
    $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลกิจกรรมที่ระบุ</p>';
    header('Location: index.php?page=advisor_attendance_select_activity');
    exit;
  }
  $stmt_activity->close();
} else {
  $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกิจกรรม: ' . htmlspecialchars($mysqli->error) . '</p>';
}


// --- Handle Form Submission (Save Attendance) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activity_id']) && $_POST['activity_id'] == $activity_id && isset($_POST['attendance_status']) && $activity_info) {
  $attendance_statuses = $_POST['attendance_status']; // Array [student_user_id => status]
  $recorded_by = $advisor_user_id; // Advisor ที่กำลังล็อกอิน
  $errors_saving = [];
  $success_count = 0;

  $mysqli->begin_transaction();
  try {
    $sql_upsert = "INSERT INTO activity_attendance
                       (student_user_id, activity_id, attendance_status, hours_earned, recorded_by_user_id, created_at, updated_at)
                       VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                       ON DUPLICATE KEY UPDATE
                       attendance_status = VALUES(attendance_status),
                       hours_earned = VALUES(hours_earned),
                       recorded_by_user_id = VALUES(recorded_by_user_id),
                       updated_at = NOW()";
    $stmt_upsert = $mysqli->prepare($sql_upsert);
    if (!$stmt_upsert) throw new Exception("Prepare statement failed: " . $mysqli->error);

    foreach ($attendance_statuses as $student_user_id_from_form => $status) {
      $student_user_id_from_form = (int)$student_user_id_from_form;
      $status = trim($status);

      // ตรวจสอบอีกครั้งว่านักศึกษาคนนี้อยู่ในที่ปรึกษาของ Advisor จริงๆ และจองกิจกรรมนี้
      // (เพื่อความปลอดภัย ป้องกันการ submit ข้อมูลมั่ว)
      $sql_verify_advisee = "SELECT s.user_id FROM students s
                                    JOIN student_groups sg ON s.group_id = sg.id
                                    JOIN group_advisors ga ON sg.id = ga.group_id
                                    JOIN activity_bookings ab ON s.user_id = ab.student_user_id
                                    WHERE s.user_id = ? AND ga.advisor_user_id = ? AND ab.activity_id = ? AND ab.status = 'booked'";
      $stmt_verify_advisee = $mysqli->prepare($sql_verify_advisee);
      $stmt_verify_advisee->bind_param('iii', $student_user_id_from_form, $advisor_user_id, $activity_id);
      $stmt_verify_advisee->execute();
      $is_valid_advisee_booking = $stmt_verify_advisee->get_result()->num_rows > 0;
      $stmt_verify_advisee->close();

      if (!$is_valid_advisee_booking) {
        $errors_saving[] = "User ID $student_user_id_from_form: ไม่ใช่นักศึกษาในที่ปรึกษา หรือ ไม่ได้จองกิจกรรมนี้";
        continue; // ข้ามไปคนถัดไป
      }


      $valid_statuses = ['attended', 'absent'];
      if (!in_array($status, $valid_statuses)) {
        $status = 'absent';
      }

      $hours_earned = 0.0;
      if ($status === 'attended') {
        $hours_earned = floatval($activity_info['hours_participant'] ?? 0.0);
      } elseif ($status === 'absent') {
        $penalty = $activity_info['penalty_hours'] ?? 0.0;
        $hours_earned = -abs(floatval($penalty));
      }

      $stmt_upsert->bind_param("iisdi", $student_user_id_from_form, $activity_id, $status, $hours_earned, $recorded_by);
      if ($stmt_upsert->execute()) {
        $success_count++;
      } else {
        $errors_saving[] = "User ID $student_user_id_from_form: " . $stmt_upsert->error;
      }
    }
    $stmt_upsert->close();

    if (empty($errors_saving)) {
      $mysqli->commit();
      $_SESSION['form_message'] = '<p class="alert alert-success text-white">บันทึกข้อมูลการเข้าร่วม ' . $success_count . ' รายการ สำเร็จแล้ว</p>';
    } else {
      $mysqli->rollback();
      $_SESSION['last_attendance_input'] = $attendance_statuses; // เก็บค่าที่ user กรอกล่าสุด
      $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูลบางรายการ:<br>' . implode('<br>', $errors_saving) . '</p>';
    }

    if (empty($message) || !empty($_SESSION['form_message'])) {
      header('Location: index.php?page=advisor_attendance_select_activity');
      exit;
    }
  } catch (Exception $e) {
    $mysqli->rollback();
    if (isset($attendance_statuses)) {
      $_SESSION['last_attendance_input'] = $attendance_statuses;
    }
    $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดร้ายแรง: ' . $e->getMessage() . '</p>';
  }
}


// --- Fetch Booked Advisees for this activity ---
if ($activity_id) {
  $sql_booked_advisees = "SELECT u.id as user_id, u.username, u.first_name, u.last_name, s.student_id_number
                            FROM activity_bookings ab
                            JOIN students s ON ab.student_user_id = s.user_id
                            JOIN users u ON s.user_id = u.id
                            JOIN student_groups sg ON s.group_id = sg.id
                            JOIN group_advisors ga ON sg.id = ga.group_id
                            WHERE ab.activity_id = ? AND ab.status = 'booked' AND ga.advisor_user_id = ?
                            ORDER BY s.student_id_number ASC";
  $stmt_booked_advisees = $mysqli->prepare($sql_booked_advisees);
  if ($stmt_booked_advisees) {
    $stmt_booked_advisees->bind_param('ii', $activity_id, $advisor_user_id);
    $stmt_booked_advisees->execute();
    $result_booked_advisees = $stmt_booked_advisees->get_result();
    while ($row = $result_booked_advisees->fetch_assoc()) {
      $advisees_booked[] = $row;
    }
    $stmt_booked_advisees->close();
  } else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงรายชื่อนักศึกษาในที่ปรึกษาที่จอง: ' . htmlspecialchars($mysqli->error) . '</p>';
  }

  // --- Fetch Existing Attendance Data ---
  $sql_att = "SELECT student_user_id, attendance_status FROM activity_attendance WHERE activity_id = ?";
  $stmt_att = $mysqli->prepare($sql_att);
  if ($stmt_att) {
    $stmt_att->bind_param('i', $activity_id);
    $stmt_att->execute();
    $result_att = $stmt_att->get_result();
    while ($row = $result_att->fetch_assoc()) {
      $attendance_data[$row['student_user_id']] = $row['attendance_status'];
    }
    $stmt_att->close();
  } else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลการเข้าร่วมเดิม: ' . htmlspecialchars($mysqli->error) . '</p>';
  }
}

$last_input = $_SESSION['last_attendance_input'] ?? null;
unset($_SESSION['last_attendance_input']);

if (isset($_SESSION['form_message'])) {
  $message = $_SESSION['form_message'];
  unset($_SESSION['form_message']);
}

?>

<div class="container-fluid py-4">
  <div class="row">
    <div class="col-12">
      <div class="card my-4">
        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
          <div class="bg-gradient-info shadow-info border-radius-lg pt-4 pb-3">
            <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
            <?php if ($activity_info): ?>
              <p class="text-white ps-3 mb-0 text-sm">วันที่: <?php echo format_datetime_th($activity_info['start_datetime'], true); ?> | ชม.เข้าร่วม: <?php echo number_format($activity_info['hours_participant'] ?? 0, 0); ?> | ชม.หัก: <?php echo number_format($activity_info['penalty_hours'] ?? 0, 0); ?></p>
            <?php endif; ?>
          </div>
        </div>
        <div class="card-body pb-2">
          <?php if (!empty($message)) : ?>
            <div class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
              <?php echo $message; ?>
              <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
          <?php endif; ?>

          <?php if ($activity_id && $activity_info): ?>
            <form action="index.php?page=advisor_attendance_record&activity_id=<?php echo $activity_id; ?>" method="post">
              <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">
              <div class="table-responsive p-0">
                <table class="table align-items-center mb-0 table-hover">
                  <thead>
                    <tr>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 5%;">ลำดับที่</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 20%;">รหัสนักศึกษา</th>
                      <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อ-สกุล</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 10%;">เข้าร่วม</th>
                      <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 10%;">ไม่เข้าร่วม</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (!empty($advisees_booked)):
                      $counter = 1;
                    ?>
                      <?php foreach ($advisees_booked as $student):
                        $current_status = $last_input[$student['user_id']] ?? $attendance_data[$student['user_id']] ?? 'absent';
                      ?>
                        <tr>
                          <td class="align-middle text-center">
                            <span class="text-secondary text-xs font-weight-bold"><?php echo $counter++; ?></span>
                          </td>
                          <td>
                            <p class="text-xs font-weight-bold mb-0 px-3"><?php echo htmlspecialchars($student['student_id_number']); ?></p>
                          </td>
                          <td>
                            <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                          </td>
                          <td class="align-middle text-center text-sm">
                            <div class="form-check d-flex justify-content-center ps-0">
                              <input class="form-check-input" type="radio"
                                name="attendance_status[<?php echo $student['user_id']; ?>]"
                                id="status_<?php echo $student['user_id']; ?>_attended"
                                value="attended"
                                <?php echo ($current_status === 'attended') ? 'checked' : ''; ?>>
                            </div>
                          </td>
                          <td class="align-middle text-center text-sm">
                            <div class="form-check d-flex justify-content-center ps-0">
                              <input class="form-check-input" type="radio"
                                name="attendance_status[<?php echo $student['user_id']; ?>]"
                                id="status_<?php echo $student['user_id']; ?>_absent"
                                value="absent"
                                <?php echo ($current_status === 'absent') ? 'checked' : ''; ?>>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="5" class="text-center p-3">ไม่พบนักศึกษาในที่ปรึกษาของท่านที่จองกิจกรรมนี้</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <?php if (!empty($advisees_booked)): ?>
                <div class="text-center mt-4">
                  <button type="submit" class="btn bg-gradient-primary w-50 my-4 mb-2">บันทึกข้อมูลการเข้าร่วม</button>
                </div>
              <?php endif; ?>
            </form>
          <?php else: ?>
            <p class="text-center text-danger">ไม่สามารถโหลดข้อมูลกิจกรรมได้</p>
            <div class="text-center">
              <a href="index.php?page=advisor_attendance_select_activity" class="btn btn-outline-secondary mb-0">กลับไปเลือกกิจกรรม</a>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>