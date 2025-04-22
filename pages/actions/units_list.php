<?php
// ========================================================================
// ไฟล์: units_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการหน่วยงานกิจกรรม, จัดการลบ, และลิงก์ไปยังหน้าเพิ่ม/แก้ไข (ปรับ UI)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "จัดการหน่วยงานกิจกรรม";
// $message = ''; // message ควรถูกส่งมาจาก Controller หลัก หรือตั้งค่าใน Session

// --- Handle Delete Request ---
// (ใช้ ob_start() ใน Controller หลัก)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];

    // *** เพิ่มการตรวจสอบ Foreign Key ก่อนลบ ***
    $check_sql = "SELECT COUNT(*) as count FROM activities WHERE organizer_unit_id = ?";
    $stmt_check = $mysqli->prepare($check_sql);
    $stmt_check->bind_param('i', $delete_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $row_check = $result_check->fetch_assoc();
    $stmt_check->close();

    if ($row_check['count'] > 0) {
        $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่สามารถลบหน่วยงานนี้ได้ เนื่องจากมีการใช้งานในกิจกรรมอยู่</p>';
    } else {
        // ลบได้
        $sql = "DELETE FROM activity_units WHERE id = ?";
        $stmt = $mysqli->prepare($sql);
        if ($stmt === false) {
            $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการเตรียมคำสั่งลบ: ' . htmlspecialchars($mysqli->error) . '</p>';
        } else {
            $stmt->bind_param('i', $delete_id);
            if ($stmt->execute()) {
                $_SESSION['form_message'] = '<p class="alert alert-success text-white">ลบหน่วยงานกิจกรรมสำเร็จแล้ว</p>';
            } else {
                $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการลบข้อมูล: ' . htmlspecialchars($stmt->error) . '</p>';
            }
            $stmt->close();
        }
    }
    // Redirect กลับมาหน้าเดิมเสมอเพื่อแสดง message
    header('Location: index.php?page=units_list');
    exit;
}

// --- Fetch Activity Units Data ---
$units = [];
$sql = "SELECT id, name, type FROM activity_units ORDER BY name ASC"; // เอา created_at, updated_at ออกถ้าไม่ใช้แสดง
$result = $mysqli->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $units[] = $row;
    }
    $result->free();
} else {
    $page_error = 'เกิดข้อผิดพลาดในการดึงข้อมูลหน่วยงาน: ' . htmlspecialchars($mysqli->error);
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
                    <?php if (!empty($page_error)) : ?>
                        <div class="alert alert-danger text-white mx-4" role="alert">
                            <?php echo $page_error; ?>
                        </div>
                    <?php endif; ?>

                    <div class="px-4 mb-3">
                        <a href="index.php?page=unit_form" class="btn btn-success bg-gradient-success mb-0">
                            <i class="material-symbols-rounded text-sm">add</i>&nbsp;&nbsp;เพิ่มหน่วยงานใหม่
                        </a>
                    </div>

                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อหน่วยงาน</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ประเภท</th>
                                    <th class="text-secondary opacity-7">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($units)) : ?>
                                    <?php foreach ($units as $unit) : ?>
                                        <tr>
                                            <td class="align-middle text-center">
                                                <span class="text-secondary text-xs font-weight-bold"><?php echo htmlspecialchars($unit['id']); ?></span>
                                            </td>
                                            <td>
                                                <h6 class="mb-0 text-sm ps-2"><?php echo htmlspecialchars($unit['name']); ?></h6>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0 ps-2"><?php echo htmlspecialchars($unit['type']); ?></p>
                                            </td>
                                            <td class="align-middle">
                                                <a href="index.php?page=unit_form&id=<?php echo $unit['id']; ?>" class="btn btn-warning btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top" title="แก้ไขข้อมูล">
                                                    <i class="material-symbols-rounded text-sm">edit</i>
                                                </a>
                                                <form action="index.php?page=units_list" method="post" style="display: inline;" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบหน่วยงานกิจกรรมนี้?');">
                                                    <input type="hidden" name="delete_id" value="<?php echo $unit['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top" title="ลบข้อมูล">
                                                        <i class="material-symbols-rounded text-sm">delete</i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="4" class="text-center">ยังไม่มีข้อมูลหน่วยงานกิจกรรม</td>
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