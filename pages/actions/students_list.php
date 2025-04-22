<?php
// ========================================================================
// ไฟล์: students_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการนักศึกษา, จัดการลบ, และลิงก์ไปยังหน้าเพิ่ม/แก้ไข (ปรับปรุง Query)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "จัดการข้อมูลนักศึกษา";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- Handle Delete Request ---
// การลบ Student ต้องลบ User ที่เกี่ยวข้องด้วย หรือไม่อนุญาตให้ลบถ้ามีข้อมูลอื่นเชื่อมโยง
// ในตัวอย่างนี้ จะลบทั้ง students และ users (ควรทำใน Transaction และระวังมากๆ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delete_user_id = (int)$_POST['delete_user_id'];
    $current_admin_id = $_SESSION['user_id'];

    // ป้องกันการลบตัวเอง (ถ้า Admin เป็น Student ด้วย?)
    if ($delete_user_id === $current_admin_id) {
        $_SESSION['form_message'] = '<p class="alert alert-warning text-white">ไม่สามารถลบบัญชีผู้ใช้ของตัวเองได้</p>';
    } else {
        // ตรวจสอบว่า User นี้เป็น Student จริงหรือไม่ก่อน
        $check_sql = "SELECT user_id FROM students WHERE user_id = ?";
        $stmt_check = $mysqli->prepare($check_sql);
        $stmt_check->bind_param('i', $delete_user_id);
        $stmt_check->execute();
        $is_student = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();

        if (!$is_student) {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลนักศึกษาที่ต้องการลบ</p>';
        } else {
            // ตรวจสอบ Foreign Key อื่นๆ ที่อาจเชื่อมกับ students หรือ users
            // เช่น activity_bookings, activity_attendance
            $can_delete = true;
            $error_detail = '';

            // Check activity_bookings
            $chk_book = "SELECT COUNT(*) as count FROM activity_bookings WHERE student_user_id = ?";
            $stmt_book = $mysqli->prepare($chk_book);
            $stmt_book->bind_param('i', $delete_user_id);
            $stmt_book->execute();
            if ($stmt_book->get_result()->fetch_assoc()['count'] > 0) {
                $can_delete = false;
                $error_detail .= ' มีข้อมูลการจองกิจกรรม';
            }
            $stmt_book->close();

            // Check activity_attendance
            $chk_att = "SELECT COUNT(*) as count FROM activity_attendance WHERE student_user_id = ?";
            $stmt_att = $mysqli->prepare($chk_att);
            $stmt_att->bind_param('i', $delete_user_id);
            $stmt_att->execute();
            if ($stmt_att->get_result()->fetch_assoc()['count'] > 0) {
                $can_delete = false;
                $error_detail .= ($error_detail ? ' และ' : '') . ' มีข้อมูลการเข้าร่วมกิจกรรม';
            }
            $stmt_att->close();

            if (!$can_delete) {
                $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่สามารถลบนักศึกษาได้ เนื่องจาก:' . $error_detail . '</p>';
            } else {
                // --- ทำการลบ (Transaction) ---
                $mysqli->begin_transaction();
                try {
                    // 1. ลบจาก students
                    $sql_del_std = "DELETE FROM students WHERE user_id = ?";
                    $stmt_del_std = $mysqli->prepare($sql_del_std);
                    $stmt_del_std->bind_param('i', $delete_user_id);
                    $stmt_del_std->execute();
                    $stmt_del_std->close();

                    // 2. ลบจาก users (ถ้าต้องการลบ User Account ด้วย)
                    $sql_del_usr = "DELETE FROM users WHERE id = ?";
                    $stmt_del_usr = $mysqli->prepare($sql_del_usr);
                    $stmt_del_usr->bind_param('i', $delete_user_id);
                    $stmt_del_usr->execute();
                    $stmt_del_usr->close();

                    $mysqli->commit();
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">ลบข้อมูลนักศึกษา (และบัญชีผู้ใช้) สำเร็จแล้ว</p>';
                } catch (mysqli_sql_exception $exception) {
                    $mysqli->rollback();
                    $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการลบข้อมูล: ' . $exception->getMessage() . '</p>';
                }
            }
        }
    }
    // Redirect กลับมาหน้าเดิมเสมอ
    header('Location: index.php?page=students_list');
    exit;
}

// --- Fetch Students Data with Joins (แก้ไข Query) ---
$students_data = [];
// แก้ไข Query ให้ JOIN levels และเลือก level_name แทน academic_level/year
$sql = "SELECT
            s.user_id, s.student_id_number,
            u.first_name, u.last_name, u.email,
            sg.group_name,
            l.level_name -- เลือก level_name จากตาราง levels
        FROM students s
        JOIN users u ON s.user_id = u.id AND u.role_id = 3 -- Join เฉพาะ User ที่เป็น Student
        LEFT JOIN student_groups sg ON s.group_id = sg.id
        LEFT JOIN levels l ON sg.level_id = l.id -- Join ตาราง levels
        ORDER BY s.student_id_number ASC"; // เรียงตามรหัสนักศึกษา

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $students_data[] = $row;
    }
    $result->free();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลนักศึกษา: ' . htmlspecialchars($mysqli->error) . '</p>';
}

// --- จัดการ Message จาก Session ---
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
                        <div class="alert alert-dismissible text-white fade show mx-4 <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>


                    <div class="px-4 mb-3">
                        <a href="index.php?page=student_form" class="btn btn-success bg-gradient-success mb-0">
                            <i class="material-symbols-rounded text-sm">person_add</i>&nbsp;&nbsp;เพิ่มข้อมูลนักศึกษาใหม่
                        </a>
                    </div>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">รหัสนักศึกษา</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อ-สกุล</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">กลุ่มเรียน</th>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ระดับชั้นปี</th>
                                    <th class="text-secondary opacity-7">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($students_data)) : ?>
                                    <?php foreach ($students_data as $student) : ?>
                                        <tr>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0 px-3"><?php echo htmlspecialchars($student['student_id_number']); ?></p>
                                            </td>
                                            <td>
                                                <div class="d-flex px-2 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                                                        <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($student['group_name'] ?? 'N/A'); ?></p>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo htmlspecialchars($student['level_name'] ?? 'N/A'); ?></span>
                                            </td>
                                            <td class="align-middle">
                                                <a href="index.php?page=student_form&user_id=<?php echo $student['user_id']; ?>" class="btn btn-warning btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top" title="แก้ไขข้อมูล">
                                                    <i class="material-symbols-rounded text-sm">edit</i>
                                                </a>
                                                <form action="index.php?page=students_list" method="post" style="display: inline;" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบนักศึกษาและบัญชีผู้ใช้นี้?');">
                                                    <input type="hidden" name="delete_user_id" value="<?php echo $student['user_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top" title="ลบข้อมูล">
                                                        <i class="material-symbols-rounded text-sm">delete</i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="5" class="text-center">ยังไม่มีข้อมูลนักศึกษา</td>
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