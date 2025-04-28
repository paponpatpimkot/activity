<?php
// ========================================================================
// ไฟล์: students_list.php (เนื้อหาสำหรับ include)
// หน้าที่: แสดงรายการนักศึกษา, ค้นหา, แบ่งหน้า, จัดการลบ, และลิงก์ไปยังหน้าเพิ่ม/แก้ไข
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// require 'db_connect.php'; // $mysqli should be available
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

$page_title = "จัดการข้อมูลนักศึกษา";
$message = ''; // สำหรับแสดงข้อความแจ้งเตือน

// --- Pagination Configuration ---
$items_per_page = 50;
$current_page = isset($_GET['p']) && is_numeric($_GET['p']) ? (int)$_GET['p'] : 1;
if ($current_page < 1) { $current_page = 1; }
$offset = ($current_page - 1) * $items_per_page;

// --- Search Parameters ---
$search_student_id = isset($_GET['search_sid']) ? trim($_GET['search_sid']) : '';
$search_group_code = isset($_GET['search_gcode']) ? trim($_GET['search_gcode']) : '';

// --- Handle Delete Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    $delete_user_id = (int)$_POST['delete_user_id'];
    $current_admin_id = $_SESSION['user_id'];

    if ($delete_user_id === $current_admin_id) {
        $_SESSION['form_message'] = '<p class="alert alert-warning text-white">ไม่สามารถลบบัญชีผู้ใช้ของตัวเองได้</p>';
    } else {
        $check_sql = "SELECT user_id FROM students WHERE user_id = ?";
        $stmt_check = $mysqli->prepare($check_sql);
        $stmt_check->bind_param('i', $delete_user_id); $stmt_check->execute();
        $is_student = $stmt_check->get_result()->num_rows > 0; $stmt_check->close();

        if (!$is_student) {
             $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลนักศึกษาที่ต้องการลบ</p>';
        } else {
            $can_delete = true; $error_detail = '';
            // Check bookings
            $chk_book = "SELECT COUNT(*) as count FROM activity_bookings WHERE student_user_id = ?";
            $stmt_book = $mysqli->prepare($chk_book); $stmt_book->bind_param('i', $delete_user_id); $stmt_book->execute();
            if ($stmt_book->get_result()->fetch_assoc()['count'] > 0) { $can_delete = false; $error_detail .= ' มีข้อมูลการจองกิจกรรม'; } $stmt_book->close();
            // Check attendance
            $chk_att = "SELECT COUNT(*) as count FROM activity_attendance WHERE student_user_id = ?";
            $stmt_att = $mysqli->prepare($chk_att); $stmt_att->bind_param('i', $delete_user_id); $stmt_att->execute();
            if ($stmt_att->get_result()->fetch_assoc()['count'] > 0) { $can_delete = false; $error_detail .= ($error_detail ? ' และ' : '') . ' มีข้อมูลการเข้าร่วมกิจกรรม'; } $stmt_att->close();

            if (!$can_delete) {
                 $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่สามารถลบนักศึกษาได้ เนื่องจาก:' . $error_detail . '</p>';
            } else {
                $mysqli->begin_transaction();
                try {
                    $sql_del_std = "DELETE FROM students WHERE user_id = ?"; $stmt_del_std = $mysqli->prepare($sql_del_std); $stmt_del_std->bind_param('i', $delete_user_id); $stmt_del_std->execute(); $stmt_del_std->close();
                    $sql_del_usr = "DELETE FROM users WHERE id = ?"; $stmt_del_usr = $mysqli->prepare($sql_del_usr); $stmt_del_usr->bind_param('i', $delete_user_id); $stmt_del_usr->execute(); $stmt_del_usr->close();
                    $mysqli->commit();
                    $_SESSION['form_message'] = '<p class="alert alert-success text-white">ลบข้อมูลนักศึกษา (และบัญชีผู้ใช้) สำเร็จแล้ว</p>';
                } catch (mysqli_sql_exception $exception) {
                    $mysqli->rollback(); $_SESSION['form_message'] = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการลบข้อมูล: ' . $exception->getMessage() . '</p>';
                }
            }
        }
    }
    // Redirect กลับมาหน้าเดิมพร้อม search term และ page
    $redirect_url = "index.php?page=students_list";
    $query_params = [];
    if (!empty($search_student_id)) $query_params['search_sid'] = $search_student_id;
    if (!empty($search_group_code)) $query_params['search_gcode'] = $search_group_code;
    if ($current_page > 1) $query_params['p'] = $current_page;
    if (!empty($query_params)) $redirect_url .= '&' . http_build_query($query_params);
    header('Location: ' . $redirect_url);
    exit;
}

// --- Build WHERE clause for Search ---
$where_clauses = [];
$params = [];
$types = ""; // *** กำหนดค่าเริ่มต้นให้ $types ***

if (!empty($search_student_id)) {
    $where_clauses[] = "s.student_id_number LIKE ?";
    $params[] = "%" . $mysqli->real_escape_string($search_student_id) . "%";
    $types .= "s";
}
if (!empty($search_group_code)) {
    $where_clauses[] = "sg.group_code LIKE ?";
    $params[] = "%" . $mysqli->real_escape_string($search_group_code) . "%";
    $types .= "s";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(" AND ", $where_clauses);
}

// --- Count Total Matching Students ---
$total_items = 0;
$sql_count = "SELECT COUNT(s.user_id) as total
              FROM students s
              JOIN users u ON s.user_id = u.id AND u.role_id = 3
              LEFT JOIN student_groups sg ON s.group_id = sg.id
              LEFT JOIN levels l ON sg.level_id = l.id" . $where_sql; // ใช้ WHERE clause ที่สร้างไว้

$stmt_count = $mysqli->prepare($sql_count);
if ($stmt_count) {
    // *** แก้ไข: ตรวจสอบ $types ก่อน bind ***
    if (!empty($types)) {
        $stmt_count->bind_param($types, ...$params); // ใช้ params เดิมจาก search
    }
    $stmt_count->execute();
    $total_items = $stmt_count->get_result()->fetch_assoc()['total'] ?? 0;
    $stmt_count->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการนับจำนวนนักศึกษา: ' . htmlspecialchars($mysqli->error) . '</p>';
}
$total_pages = ceil($total_items / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $items_per_page;
}


// --- Fetch Paginated and Searched Students Data ---
$students_data = [];
$sql = "SELECT
            s.user_id, s.student_id_number,
            u.first_name, u.last_name, u.email,
            sg.group_code, -- ดึง group_code มาด้วย
            l.level_name
        FROM students s
        JOIN users u ON s.user_id = u.id AND u.role_id = 3
        LEFT JOIN student_groups sg ON s.group_id = sg.id
        LEFT JOIN levels l ON sg.level_id = l.id"
       . $where_sql . // ใช้ WHERE clause ที่สร้างไว้
       " ORDER BY s.student_id_number ASC
         LIMIT ? OFFSET ?"; // เพิ่ม LIMIT และ OFFSET

// เพิ่ม LIMIT และ OFFSET parameters
$list_params = $params; // เอา params จาก search มาใช้
// *** แก้ไข: กำหนดค่าเริ่มต้นให้ $list_types ก่อน ***
$list_types = $types; // ใช้ types จาก search เป็นค่าเริ่มต้น
$list_params[] = $items_per_page;
$list_types .= "i";
$list_params[] = $offset;
$list_types .= "i";

$stmt_list = $mysqli->prepare($sql);
if ($stmt_list) {
    // *** แก้ไข: ตรวจสอบ $list_types ก่อน bind ***
    if (!empty($list_types)) {
        $stmt_list->bind_param($list_types, ...$list_params);
    }
    if ($stmt_list->execute()) {
        $result = $stmt_list->get_result();
        while ($row = $result->fetch_assoc()) {
            $students_data[] = $row;
        }
        $result->free();
    } else {
         $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลนักศึกษา: ' . htmlspecialchars($stmt_list->error) . '</p>';
    }
    $stmt_list->close();
} else {
     $message .= '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการ Prepare Query นักศึกษา: ' . htmlspecialchars($mysqli->error) . '</p>';
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
        <div class="card-body px-4 pb-2">

          <?php if (!empty($message)) : ?>
          <div
            class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : ((strpos($message, 'warning') !== false || strpos($message, 'เตือน') !== false) ? 'alert-warning bg-gradient-warning' : 'alert-danger bg-gradient-danger'); ?>"
            role="alert">
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

          <form action="index.php" method="get" class="row g-3 align-items-end mb-4">
            <input type="hidden" name="page" value="students_list">
            <div class="col-md-4">
              <div class="input-group input-group-static"> <label for="search_sid">ค้นหารหัสนักศึกษา</label>
                <input type="text" class="form-control" id="search_sid" name="search_sid"
                  value="<?php echo htmlspecialchars($search_student_id); ?>">
              </div>
            </div>
            <div class="col-md-4">
              <div class="input-group input-group-static">
                <label for="search_gcode">ค้นหารหัสกลุ่มเรียน</label>
                <input type="text" class="form-control" id="search_gcode" name="search_gcode"
                  value="<?php echo htmlspecialchars($search_group_code); ?>">
              </div>
            </div>
            <div class="col-md-auto">
              <button type="submit" class="btn bg-gradient-primary mb-0">
                <i class="material-symbols-rounded text-sm">search</i>&nbsp;ค้นหา
              </button>
              <?php if (!empty($search_student_id) || !empty($search_group_code)): ?>
              <a href="index.php?page=students_list" class="btn btn-outline-secondary mb-0">ล้างค้นหา</a>
              <?php endif; ?>
            </div>
            <div class="col-md text-md-end">
              <a href="index.php?page=student_form" class="btn btn-success bg-gradient-success mb-0">
                <i class="material-symbols-rounded text-sm">person_add</i>&nbsp;&nbsp;เพิ่มข้อมูลนักศึกษา
              </a>
            </div>
          </form>


          <div class="table-responsive p-0">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">รหัสนักศึกษา</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">ชื่อ-สกุล</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">รหัสกลุ่มเรียน
                  </th>
                  <th class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                    ระดับชั้นปี</th>
                  <th class="text-secondary opacity-7">จัดการ</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($students_data)) : ?>
                <?php foreach ($students_data as $student) : ?>
                <tr>
                  <td>
                    <p class="text-xs font-weight-bold mb-0 px-3">
                      <?php echo htmlspecialchars($student['student_id_number']); ?></p>
                  </td>
                  <td>
                    <div class="d-flex px-2 py-1">
                      <div class="d-flex flex-column justify-content-center">
                        <h6 class="mb-0 text-sm">
                          <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h6>
                        <p class="text-xs text-secondary mb-0">
                          <?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?></p>
                      </div>
                    </div>
                  </td>
                  <td>
                    <p class="text-xs font-weight-bold mb-0">
                      <?php echo htmlspecialchars($student['group_code'] ?? 'N/A'); ?></p>
                  </td>
                  <td class="align-middle text-center text-sm">
                    <span
                      class="text-secondary text-xs font-weight-bold"><?php echo htmlspecialchars($student['level_name'] ?? 'N/A'); ?></span>
                  </td>
                  <td class="align-middle">
                    <a href="index.php?page=student_form&user_id=<?php echo $student['user_id']; ?>"
                      class="btn btn-warning btn-sm mb-0" data-bs-toggle="tooltip" data-bs-placement="top"
                      title="แก้ไขข้อมูล">
                      <i class="material-symbols-rounded text-sm">edit</i>
                    </a>
                    <form action="index.php?page=students_list" method="post" style="display: inline;"
                      onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบนักศึกษาและบัญชีผู้ใช้นี้?');">
                      <input type="hidden" name="delete_user_id" value="<?php echo $student['user_id']; ?>">
                      <button type="submit" class="btn btn-danger btn-sm mb-0" data-bs-toggle="tooltip"
                        data-bs-placement="top" title="ลบข้อมูล">
                        <i class="material-symbols-rounded text-sm">delete</i>
                      </button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php else : ?>
                <tr>
                  <td colspan="5" class="text-center">
                    <?php echo (!empty($search_student_id) || !empty($search_group_code)) ? 'ไม่พบข้อมูลนักศึกษาที่ตรงกับเงื่อนไข' : 'ยังไม่มีข้อมูลนักศึกษา'; ?>
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <?php if ($total_pages > 1): ?>
          <nav aria-label="Page navigation" class="mt-4 d-flex justify-content-center">
            <ul class="pagination pagination-primary">
              <?php
                          // Build query string for pagination links
                          $query_params = [];
                          if (!empty($search_student_id)) $query_params['search_sid'] = $search_student_id;
                          if (!empty($search_group_code)) $query_params['search_gcode'] = $search_group_code;
                          $base_page_url = "index.php?page=students_list&" . http_build_query($query_params);

                          // Previous button
                          $prev_page = $current_page - 1;
                        ?>
              <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_page_url; ?>&p=<?php echo $prev_page; ?>"
                  aria-label="Previous">
                  <span class="material-symbols-rounded">keyboard_arrow_left</span>
                  <span class="sr-only"></span>
                </a>
              </li>

              <?php
                          // Page numbers
                          $start_page = max(1, $current_page - 2);
                          $end_page = min($total_pages, $current_page + 2);

                          if ($start_page > 1) {
                              echo '<li class="page-item"><a class="page-link" href="' . $base_page_url . '&p=1">1</a></li>';
                              if ($start_page > 2) {
                                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                              }
                          }

                          for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
              <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                <a class="page-link" href="<?php echo $base_page_url; ?>&p=<?php echo $i; ?>"><?php echo $i; ?></a>
              </li>
              <?php endfor;

                          if ($end_page < $total_pages) {
                              if ($end_page < $total_pages - 1) {
                                  echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                              }
                              echo '<li class="page-item"><a class="page-link" href="' . $base_page_url . '&p=' . $total_pages . '">' . $total_pages . '</a></li>';
                          }
                        ?>

              <?php
                          // Next button
                          $next_page = $current_page + 1;
                        ?>
              <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $base_page_url; ?>&p=<?php echo $next_page; ?>" aria-label="Next">
                  <span class="material-symbols-rounded">keyboard_arrow_right</span>
                  <span class="sr-only"></span>
                </a>
              </li>
            </ul>
          </nav>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>