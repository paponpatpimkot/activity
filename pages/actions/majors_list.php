<?php
// ========================================================================
// ไฟล์: majors_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการสาขาวิชา, จัดการลบ, และลิงก์ไปยังหน้าเพิ่ม/แก้ไข (ปรับ UI)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก และ Controller ส่ง $mysqli และ $message (ถ้ามี) มาให้ ---
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "จัดการสาขาวิชา";
// $message = ''; // message ควรถูกส่งมาจาก Controller หลัก หรือตั้งค่าใน Session

// --- Handle Delete Request ---
// *** ส่วนนี้ควรย้ายไปประมวลผลใน Controller หลัก (index.php) ถ้าใช้ PRG Pattern ***
// *** แต่ถ้าใช้ ob_start() สามารถเก็บไว้ที่นี่ได้ (แต่ต้องจัดการ $message ผ่าน Session/Redirect) ***
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    $can_delete = true;
    $error_detail = '';

    // 1. Check student_groups
    $check_sql_group = "SELECT COUNT(*) as count FROM student_groups WHERE major_id = ?";
    $stmt_check_group = $mysqli->prepare($check_sql_group);
    $stmt_check_group->bind_param('i', $delete_id);
    $stmt_check_group->execute();
    $row_check_group = $stmt_check_group->get_result()->fetch_assoc();
    $stmt_check_group->close();
    if ($row_check_group['count'] > 0) {
        $can_delete = false;
        $error_detail .= ' กลุ่มเรียน';
    }

    // 2. Check activity_eligible_majors
    $check_sql_activity = "SELECT COUNT(*) as count FROM activity_eligible_majors WHERE major_id = ?";
    $stmt_check_activity = $mysqli->prepare($check_sql_activity);
    $stmt_check_activity->bind_param('i', $delete_id);
    $stmt_check_activity->execute();
    $row_check_activity = $stmt_check_activity->get_result()->fetch_assoc();
    $stmt_check_activity->close();
    if ($row_check_activity['count'] > 0) {
        $can_delete = false;
        $error_detail .= ($error_detail ? ' หรือ' : '') . ' กิจกรรม';
    }

    if (!$can_delete) {
        $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่สามารถลบสาขาวิชานี้ได้ เนื่องจากมีการใช้งานใน:' . $error_detail . '</p>';
    } else {
        $sql = "DELETE FROM majors WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt) {
            $stmt->bind_param('i', $delete_id);
            if ($stmt->execute()) {
                $_SESSION['form_message'] = '<p class="alert alert-success text-white">ลบสาขาวิชาสำเร็จแล้ว</p>';
            } else {
                $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการลบข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
            }
            $stmt->close();
        } else {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งลบ: ' . htmlspecialchars($mysqli->error) . '</p>';
        }
    }
    // Redirect กลับมาหน้าเดิมเสมอเพื่อแสดง message
    header('Location: index.php?page=majors_list');
    exit;
}

// --- Fetch Majors Data ---
$majors = [];
$sql = "SELECT id, major_code, name FROM majors ORDER BY major_code ASC";
$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $majors[] = $row;
    }
    $result->free();
} else {
    // การแสดง error ควรจัดการใน Controller หลัก หรือแสดงผลใน HTML ด้านล่าง
    $page_error = "เกิดข้อผิดพลาดในการดึงข้อมูลสาขาวิชา: " . htmlspecialchars($mysqli->error);
}

// --- จัดการ Message จาก Session ---
// ควรทำใน Controller หลัก แล้วส่ง $message มา
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
                        <div class="alert alert-dismissible text-white fade show mx-4 <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : 'alert-danger bg-gradient-danger'; ?>" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($page_error)) : // แสดง Error ถ้าดึงข้อมูลไม่ได้ 
                    ?>
                        <div class="alert alert-danger text-white mx-4" role="alert">
                            <?php echo $page_error; ?>
                        </div>
                    <?php endif; ?>


                    <div class="px-4 mb-3">
                        <a href="index.php?page=major_form" class="btn btn-success bg-gradient-success mb-0">
                            <i class="material-symbols-rounded text-sm">add</i>&nbsp;&nbsp;เพิ่มสาขาวิชาใหม่
                        </a>
                    </div>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 5%;">ID</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7" style="width: 20%;">รหัสสาขาวิชา</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อสาขาวิชา</th>
                                    <th class="text-secondary opacity-7">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($majors)) : ?>
                                    <?php foreach ($majors as $major) : ?>
                                        <tr>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo htmlspecialchars($major['id']); ?></span>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0 px-3"><?php echo htmlspecialchars($major['major_code']); ?></p>
                                            </td>
                                            <td>
                                                <h6 class="mb-0 text-sm"><?php echo htmlspecialchars($major['name']); ?></h6>
                                            </td>
                                            <td class="align-middle">
                                                <a href="index.php?page=major_form&id=<?php echo $major['id']; ?>" class="btn btn-warning btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top" title="แก้ไขข้อมูล">
                                                    <i class="material-symbols-rounded text-sm">edit</i>
                                                </a>
                                                <form action="index.php?page=majors_list" method="post" style="display: inline;" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบสาขาวิชานี้?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $major['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top" title="ลบข้อมูล">
                                                        <i class="material-symbols-rounded text-sm">delete</i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4" class="text-center">ยังไม่มีข้อมูลสาขาวิชา</td>
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
