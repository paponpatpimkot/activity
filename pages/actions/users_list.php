<?php
// ========================================================================
// ไฟล์: users_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการผู้ใช้งาน, จัดการลบ, และลิงก์ไปยังหน้าเพิ่ม/แก้ไข
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php, และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "จัดการผู้ใช้งาน";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- Handle Delete Request ---
// การลบ User มีความซับซ้อนสูง ควรพิจารณา Soft Delete (ตั้งสถานะ inactive) แทนการลบจริง
// ตัวอย่างนี้จะลบจริง แต่ต้องระวัง Foreign Key Constraints จำนวนมาก
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delete_user_id = (int)$_POST['delete_user_id'];
    $current_admin_id = $_SESSION['user_id']; // ID ของ Admin ที่กำลังล็อกอิน

    // *** ป้องกันการลบตัวเอง ***
    if ($delete_user_id === $current_admin_id) {
        $message = '<p class="alert alert-warning text-white">ไม่สามารถลบบัญชีผู้ใช้ของตัวเองได้</p>';
    } else {
        // *** ตรวจสอบ Foreign Key Constraints (ตัวอย่างเบื้องต้น) ***
        // ควรตรวจสอบทุกตารางที่มี user_id เป็น FK เช่น students, advisors, staff,
        // student_groups (advisor_user_id), activities (created_by_user_id),
        // activity_bookings (student_user_id), activity_attendance (student_user_id, recorded_by_user_id)
        // การตรวจสอบทั้งหมดนี้ซับซ้อน ในตัวอย่างจะเช็คแค่ advisors, staff ก่อน
        $can_delete = true;
        $error_detail = '';

        // Check advisors
        $check_sql_advisor = "SELECT COUNT(*) as count FROM advisors WHERE user_id = ?";
        $stmt_check_advisor = $mysqli->prepare($check_sql_advisor);
        $stmt_check_advisor->bind_param('i', $delete_user_id);
        $stmt_check_advisor->execute();
        $result_check_advisor = $stmt_check_advisor->get_result();
        $row_check_advisor = $result_check_advisor->fetch_assoc();
        $stmt_check_advisor->close();
        if ($row_check_advisor['count'] > 0) {
            $can_delete = false;
            $error_detail .= ' มีข้อมูล Advisor เชื่อมโยงอยู่';
        }

        // Check staff
        $check_sql_staff = "SELECT COUNT(*) as count FROM staff WHERE user_id = ?";
        $stmt_check_staff = $mysqli->prepare($check_sql_staff);
        $stmt_check_staff->bind_param('i', $delete_user_id);
        $stmt_check_staff->execute();
        $result_check_staff = $stmt_check_staff->get_result();
        $row_check_staff = $result_check_staff->fetch_assoc();
        $stmt_check_staff->close();
        if ($row_check_staff['count'] > 0) {
            $can_delete = false;
            $error_detail .= ' มีข้อมูล Staff เชื่อมโยงอยู่';
        }

        // Check students
        $check_sql_student = "SELECT COUNT(*) as count FROM students WHERE user_id = ?";
        $stmt_check_student = $mysqli->prepare($check_sql_student);
        $stmt_check_student->bind_param('i', $delete_user_id);
        $stmt_check_student->execute();
        $result_check_student = $stmt_check_student->get_result();
        $row_check_student = $result_check_student->fetch_assoc();
        $stmt_check_student->close();
        if ($row_check_student['count'] > 0) {
            $can_delete = false;
            $error_detail .= ' มีข้อมูล Student เชื่อมโยงอยู่';
        }

        // *** ควรเพิ่มการตรวจสอบตารางอื่นๆ ที่นี่ ***

        if (!$can_delete) {
            $message = '<p class="alert alert-danger text-white">ไม่สามารถลบผู้ใช้นี้ได้ เนื่องจาก:' . $error_detail . '</p>';
        } else {
            // --- ทำการลบ (ควรอยู่ใน Transaction) ---
            $mysqli->begin_transaction();
            try {
                // ลบข้อมูลที่เกี่ยวข้องก่อน (ถ้ามี เช่น advisors, staff)
                // (ในตัวอย่างนี้ สมมติว่าไม่มีข้อมูลเชื่อมโยงแล้วจากเงื่อนไขด้านบน)
                // $mysqli->query("DELETE FROM advisors WHERE user_id = $delete_user_id");
                // $mysqli->query("DELETE FROM staff WHERE user_id = $delete_user_id");
                // ... ลบข้อมูลอื่นๆ ที่เชื่อมโยง ...

                // ลบจากตาราง users
                $sql_delete_user = "DELETE FROM users WHERE id = ?";
                $stmt_delete_user = $mysqli->prepare($sql_delete_user);
                $stmt_delete_user->bind_param('i', $delete_user_id);
                $stmt_delete_user->execute();
                $stmt_delete_user->close();

                $mysqli->commit();
                $message = '<p class="alert alert-success text-white">ลบผู้ใช้งานสำเร็จแล้ว</p>';
            } catch (mysqli_sql_exception $exception) {
                $mysqli->rollback();
                $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการลบผู้ใช้งาน: ' . $exception->getMessage() . '</p>';
            }
        }
    }
}


// --- Fetch Users Data ---
$users_data = [];
$sql = "SELECT u.id, u.username, u.first_name, u.last_name, u.email, u.created_at, r.name as role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        ORDER BY u.first_name ASC, u.last_name ASC";

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users_data[] = $row;
    }
    $result->free();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลผู้ใช้งาน: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- จัดการ Message จาก Session (ถ้ามีการ redirect มา) ---
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
          <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
            <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
          </div>
        </div>
        <div class="card-body px-0 pb-2">

          <?php if (!empty($message)) : ?>
          <div
            class="alert alert-dismissible text-white fade show mx-4 <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>"
            role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <?php endif; ?>


          <div class="px-4 mb-3">
            <a href="index.php?page=user_form" class="btn btn-success bg-gradient-success mb-0">
              <i class="material-symbols-rounded text-sm">person_add</i>&nbsp;&nbsp;เพิ่มผู้ใช้งานใหม่
            </a>
          </div>

          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ผู้ใช้งาน (ชื่อ-สกุล)
                  </th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">Username</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">บทบาท
                    (Role)</th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                    วันที่สร้าง</th>
                  <th class="text-secondary opacity-7"></th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($users_data)) : ?>
                <?php foreach ($users_data as $user) : ?>
                <tr>
                  <td>
                    <div class="d-flex px-2 py-1">
                      <div class="d-flex flex-column justify-content-center">
                        <h6 class="mb-0 text-sm">
                          <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h6>
                        <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?>
                        </p>
                      </div>
                    </div>
                  </td>
                  <td>
                    <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($user['username']); ?></p>
                  </td>
                  <td class="align-middle text-center text-sm">
                    <?php
                                                $role_class = 'bg-gradient-secondary'; // Default
                                                if ($user['role_name'] == 'admin') $role_class = 'bg-gradient-danger';
                                                elseif ($user['role_name'] == 'advisor') $role_class = 'bg-gradient-info';
                                                elseif ($user['role_name'] == 'student') $role_class = 'bg-gradient-success';
                                                elseif ($user['role_name'] == 'staff') $role_class = 'bg-gradient-warning';
                                                ?>
                    <span
                      class="badge badge-sm <?php echo $role_class; ?>"><?php echo htmlspecialchars($user['role_name']); ?></span>
                  </td>
                  <td class="align-middle text-center">
                    <span
                      class="text-secondary text-xs font-weight-bold"><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></span>
                  </td>
                  <td class="align-middle">
                    <a href="index.php?page=user_form&user_id=<?php echo $user['id']; ?>"
                      class="btn btn-warning btn-sm mb-0" data-toggle="tooltip" data-original-title="แก้ไขข้อมูล">
                      <i class="material-icons text-sm">edit</i>
                    </a>
                    <?php if ($user['id'] !== $_SESSION['user_id']): // ป้องกันปุ่มลบตัวเอง 
                                                ?>
                    <form action="index.php?page=users_list" method="post" style="display: inline;"
                      onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้งานนี้? (การกระทำนี้อาจส่งผลกระทบต่อข้อมูลอื่น)');">
                      <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                      <button type="submit" class="btn btn-danger btn-sm mb-0" data-toggle="tooltip"
                        data-original-title="ลบข้อมูล">
                        <i class="material-icons text-sm">delete</i>
                      </button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php else : ?>
                <tr>
                  <td colspan="5" class="text-center">ยังไม่มีข้อมูลผู้ใช้งาน</td>
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