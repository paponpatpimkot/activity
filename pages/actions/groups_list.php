<?php
// ========================================================================
// ไฟล์: groups_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการกลุ่มเรียน, จัดการลบ, และลิงก์ไปยังหน้าเพิ่ม/แก้ไข (ปรับปรุง)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "จัดการกลุ่มเรียน";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- Handle Delete Request ---
// การลบกลุ่มเรียนจะลบข้อมูลใน group_advisors ด้วย (ON DELETE CASCADE)
// แต่ต้องเช็ค students ก่อน
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];

    // *** ตรวจสอบ Foreign Key ก่อนลบ (เช็คว่ามี students อ้างอิง group_id นี้หรือไม่) ***
    $check_sql = "SELECT COUNT(*) as count FROM students WHERE group_id = ?";
    $stmt_check = $mysqli->prepare($check_sql);
    $stmt_check->bind_param('i', $delete_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row_check = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($row_check['count'] > 0) {
        $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่สามารถลบกลุ่มเรียนนี้ได้ เนื่องจากมีนักศึกษาสังกัดอยู่</p>';
    } else {
        // ไม่มีนักศึกษาสังกัดอยู่, สามารถลบได้ (FK ใน group_advisors เป็น ON DELETE CASCADE)
        $sql = "DELETE FROM student_groups WHERE id = ?";
        $stmt = $mysqli->prepare($sql);

        if ($stmt === false) {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งลบ: ' . htmlspecialchars($mysqli->error) . '</p>';
        } else {
            $stmt->bind_param('i', $delete_id);
            if ($stmt->execute()) {
                $_SESSION['form_message'] = '<p class="alert alert-success text-white">ลบกลุ่มเรียนสำเร็จแล้ว</p>';
            } else {
                $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการลบข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
            }
            $stmt->close();
        }
    }
    // Redirect กลับมาหน้าเดิมเสมอเพื่อแสดง message
    header('Location: index.php?page=groups_list');
    exit;
}

// --- Fetch Student Groups Data with Joins ---
$groups_data = [];
// Query ข้อมูลกลุ่ม พร้อม JOIN เพื่อเอาชื่อ Level, Major และรายชื่อ Advisor
$sql = "SELECT
            sg.id, sg.group_code, sg.group_name,
            l.level_name,
            m.name as major_name,
            GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as advisor_names
        FROM student_groups sg
        LEFT JOIN levels l ON sg.level_id = l.id
        LEFT JOIN majors m ON sg.major_id = m.id
        LEFT JOIN group_advisors ga ON sg.id = ga.group_id
        LEFT JOIN users u ON ga.advisor_user_id = u.id AND u.role_id = 2 -- Join เฉพาะ Advisor
        GROUP BY sg.id, sg.group_code, sg.group_name, l.level_name, m.name -- Group by ข้อมูลหลักของกลุ่ม
        ORDER BY sg.group_code ASC";

$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $groups_data[] = $row;
    }
    $result->free();
} else {
    $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกลุ่มเรียน: ' . htmlspecialchars($mysqli->error) . '</p>';
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
                        <a href="index.php?page=group_form" class="btn btn-success bg-gradient-success mb-0">
                            <i class="material-symbols-rounded text-sm">add</i>&nbsp;&nbsp;เพิ่มกลุ่มเรียนใหม่
                        </a>
                    </div>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">รหัสกลุ่ม</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อกลุ่ม</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ระดับชั้นปี</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">สาขาวิชา</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">อาจารย์ที่ปรึกษา</th>
                                    <th class="text-secondary opacity-7">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($groups_data)) : ?>
                                    <?php foreach ($groups_data as $group) : ?>
                                        <tr>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0 px-3"><?php echo htmlspecialchars($group['group_code']); ?></p>
                                            </td>
                                            <td>
                                                <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($group['group_name']); ?></h6>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($group['level_name'] ?? 'N/A'); ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0"><?php echo htmlspecialchars($group['major_name'] ?? 'N/A'); ?></p>
                                            </td>
                                            <td>
                                                <p class="text-xs text-secondary mb-0"><?php echo htmlspecialchars($group['advisor_names'] ?? '-'); ?></p>
                                            </td>
                                            <td class="align-middle">
                                                <a href="index.php?page=group_form&id=<?php echo $group['id']; ?>" class="btn btn-warning btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top" title="แก้ไขข้อมูล">
                                                    <i class="material-symbols-rounded text-sm">edit</i>
                                                </a>
                                                <form action="index.php?page=groups_list" method="post" style="display: inline;" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบกลุ่มเรียนนี้?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $group['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top" title="ลบข้อมูล">
                                                        <i class="material-symbols-rounded text-sm">delete</i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="6" class="text-center">ยังไม่มีข้อมูลกลุ่มเรียน</td>
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