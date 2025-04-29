<?php
// ========================================================================
// ไฟล์: group_form.php (เนื้อหาสำหรับ include)
// หน้าที่: ฟอร์มสำหรับเพิ่ม หรือ แก้ไข กลุ่มเรียน (ปรับปรุง Advisor Selection)
// ========================================================================

// --- สมมติว่า session_start(), db_connect.php ($mysqli), และ Authorization check ---
// --- ได้ทำไปแล้วในไฟล์ Controller หลัก ---
// if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) { exit('Unauthorized'); }

// --- กำหนดค่าเริ่มต้น ---
$is_edit_mode = isset($_GET['id']) && is_numeric($_GET['id']);
$group_id = $is_edit_mode ? (int)$_GET['id'] : null;
$page_title = $is_edit_mode ? "แก้ไขกลุ่มเรียน" : "เพิ่มกลุ่มเรียนใหม่";
$form_action = "index.php?page=group_form" . ($is_edit_mode ? "&id=" . $group_id : "");

// ค่าเริ่มต้นสำหรับฟอร์ม
$group_code = '';
$group_name = '';
$level_id = '';
$major_id = '';
$selected_advisor_ids = []; // Array ของ Advisor IDs ที่ถูกเลือก
$selected_advisors_data = []; // Array เก็บข้อมูล Advisor ที่เลือกไว้แล้ว (สำหรับแสดงผลตอน Edit)
$message = $message ?? '';

// --- ดึงข้อมูลสำหรับ Dropdowns ---
// Levels
$levels = [];
$sql_levels = "SELECT id, level_name FROM levels ORDER BY id ASC";
$result_levels = $mysqli->query($sql_levels);
if ($result_levels) { while ($row = $result_levels->fetch_assoc()) { $levels[] = $row; } $result_levels->free(); }

// Majors
$majors = [];
$sql_majors = "SELECT id, name, major_code FROM majors ORDER BY major_code ASC";
$result_majors = $mysqli->query($sql_majors);
if ($result_majors) { while ($row = $result_majors->fetch_assoc()) { $majors[] = $row; } $result_majors->free(); }

// --- ไม่ต้องดึง Advisor ทั้งหมดมาตรงนี้แล้ว ---
// $all_advisors = [];


// --- ดึงข้อมูลเดิมถ้าเป็นการแก้ไข ---
if ($is_edit_mode && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Fetch group data
    $sql_edit = "SELECT group_code, group_name, level_id, major_id FROM student_groups WHERE id = ?";
    $stmt_edit = $mysqli->prepare($sql_edit);
    if ($stmt_edit) {
        $stmt_edit->bind_param('i', $group_id);
        $stmt_edit->execute();
        $result_edit = $stmt_edit->get_result();
        if ($result_edit->num_rows === 1) {
            $group_data = $result_edit->fetch_assoc();
            $group_code = $group_data['group_code'];
            $group_name = $group_data['group_name'];
            $level_id = $group_data['level_id'];
            $major_id = $group_data['major_id'];

            // Fetch selected advisors data (ID and Name)
            $sql_selected_adv = "SELECT ga.advisor_user_id, u.first_name, u.last_name
                                 FROM group_advisors ga
                                 JOIN users u ON ga.advisor_user_id = u.id
                                 WHERE ga.group_id = ?";
            $stmt_selected_adv = $mysqli->prepare($sql_selected_adv);
            if ($stmt_selected_adv) {
                $stmt_selected_adv->bind_param('i', $group_id);
                $stmt_selected_adv->execute();
                $result_selected_adv = $stmt_selected_adv->get_result();
                while ($row_adv = $result_selected_adv->fetch_assoc()) {
                    $selected_advisor_ids[] = $row_adv['advisor_user_id']; // เก็บ ID สำหรับ POST
                    $selected_advisors_data[] = $row_adv; // เก็บข้อมูลสำหรับแสดงผล
                }
                $stmt_selected_adv->close();
            } else {
                 $message .= '<p class="alert alert-warning text-white">เกิดข้อผิดพลาดในการดึงข้อมูลอาจารย์ที่ปรึกษาเดิม</p>';
            }

        } else {
             $_SESSION['form_message'] = '<p class="alert alert-danger text-white">ไม่พบข้อมูลกลุ่มเรียนที่ต้องการแก้ไข</p>';
             header('Location: index.php?page=groups_list');
             exit;
        }
        $stmt_edit->close();
    } else {
        $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการดึงข้อมูลกลุ่มเรียนเดิม</p>';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ถ้า POST กลับมาพร้อม Error ให้ใช้ค่าจาก POST (Controller ควรส่งกลับมา)
    $group_code = $_POST['group_code'] ?? $group_code;
    $group_name = $_POST['group_name'] ?? $group_name;
    $level_id = $_POST['level_id'] ?? $level_id;
    $major_id = $_POST['major_id'] ?? $major_id;
    $submitted_advisors = $_POST['advisors'] ?? [];
    $selected_advisor_ids = $submitted_advisors; // ใช้ค่าที่ส่งมาล่าสุด
    // ต้องดึงข้อมูล advisor ที่เลือกมาแสดงใหม่ ถ้าต้องการแสดงชื่อตอนมี error
    if (!empty($selected_advisor_ids)) {
        $placeholders = implode(',', array_fill(0, count($selected_advisor_ids), '?'));
        $types = str_repeat('i', count($selected_advisor_ids));
        $sql_refetch_advisors = "SELECT id as advisor_user_id, first_name, last_name FROM users WHERE id IN ($placeholders)";
        $stmt_refetch = $mysqli->prepare($sql_refetch_advisors);
        if ($stmt_refetch) {
            $stmt_refetch->bind_param($types, ...$selected_advisor_ids);
            $stmt_refetch->execute();
            $result_refetch = $stmt_refetch->get_result();
            while ($row_refetch = $result_refetch->fetch_assoc()) {
                $selected_advisors_data[] = $row_refetch;
            }
            $stmt_refetch->close();
        }
    }
}


// --- Handle Form Submission (Add or Edit) ---
// *** ส่วนนี้จะทำงานเมื่อถูก include ใน Controller และ Controller ตรวจสอบว่าเป็น POST request สำหรับหน้านี้ ***
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าจากฟอร์ม
    $group_code = trim($_POST['group_code']);
    $group_name = trim($_POST['group_name']);
    $level_id = filter_input(INPUT_POST, 'level_id', FILTER_VALIDATE_INT);
    $major_id = filter_input(INPUT_POST, 'major_id', FILTER_VALIDATE_INT);
    $submitted_advisors = $_POST['advisors'] ?? []; // รับเป็น Array
    $current_group_id = $is_edit_mode ? $group_id : 0;

    // --- Validate Input ---
    $errors = [];
    if (empty($group_code)) $errors[] = "กรุณากรอกรหัสกลุ่ม";
    if (empty($group_name)) $errors[] = "กรุณากรอกชื่อกลุ่ม";
    if (empty($level_id)) $errors[] = "กรุณาเลือกระดับชั้นปี";
    if (empty($major_id)) $errors[] = "กรุณาเลือกสาขาวิชา";
    // Validate advisor IDs (ensure they are integers)
    $validated_advisor_ids = [];
     if (!empty($submitted_advisors)) {
         foreach ($submitted_advisors as $adv_id) {
             if (filter_var($adv_id, FILTER_VALIDATE_INT)) {
                 $validated_advisor_ids[] = (int)$adv_id;
             } else {
                 $errors[] = "รูปแบบรหัสอาจารย์ที่ปรึกษาไม่ถูกต้อง";
                 break; // พบข้อผิดพลาด หยุดเช็ค
             }
         }
     }
     // อาจจะเพิ่มการตรวจสอบว่า Advisor ID ที่ส่งมา มี Role เป็น Advisor จริงหรือไม่ (Query ซ้ำ)

    // --- Check for duplicate group_code ---
    if (empty($errors)) {
        $sql_check_code = "SELECT id FROM student_groups WHERE group_code = ? AND id != ?";
        $stmt_check_code = $mysqli->prepare($sql_check_code);
        $stmt_check_code->bind_param('si', $group_code, $current_group_id);
        $stmt_check_code->execute();
        if ($stmt_check_code->get_result()->num_rows > 0) {
            $errors[] = "รหัสกลุ่ม '$group_code' นี้มีอยู่แล้ว";
        }
        $stmt_check_code->close();
    }

    if (!empty($errors)) {
        $message = '<p class="alert alert-danger text-white">' . implode('<br>', $errors) . '</p>';
        $selected_advisor_ids = $validated_advisor_ids; // ใช้ ID ที่ผ่านการ Validate เบื้องต้น
        // ต้องดึงข้อมูล advisor ที่เลือกมาแสดงใหม่
        $selected_advisors_data = [];
        if (!empty($selected_advisor_ids)) {
            $placeholders = implode(',', array_fill(0, count($selected_advisor_ids), '?'));
            $types = str_repeat('i', count($selected_advisor_ids));
            $sql_refetch_advisors = "SELECT id as advisor_user_id, first_name, last_name FROM users WHERE id IN ($placeholders)";
            $stmt_refetch = $mysqli->prepare($sql_refetch_advisors);
            if ($stmt_refetch) {
                $stmt_refetch->bind_param($types, ...$selected_advisor_ids);
                $stmt_refetch->execute();
                $result_refetch = $stmt_refetch->get_result();
                while ($row_refetch = $result_refetch->fetch_assoc()) { $selected_advisors_data[] = $row_refetch; }
                $stmt_refetch->close();
            }
        }
    } else {
        // --- Process Add or Edit ---
        $mysqli->begin_transaction();
        try {
            $target_group_id = null;

            if ($is_edit_mode && $group_id !== null) {
                // --- Update ---
                $sql_update_group = "UPDATE student_groups SET group_code = ?, group_name = ?, level_id = ?, major_id = ? WHERE id = ?";
                $stmt_update_group = $mysqli->prepare($sql_update_group);
                if (!$stmt_update_group) throw new Exception("Prepare Update Group Error: " . $mysqli->error);
                $stmt_update_group->bind_param('ssiii', $group_code, $group_name, $level_id, $major_id, $group_id);
                if (!$stmt_update_group->execute()) throw new Exception("Execute Update Group Error: " . $stmt_update_group->error);
                $stmt_update_group->close();
                $target_group_id = $group_id;
                $_SESSION['form_message'] = '<p class="alert alert-success text-white">แก้ไขข้อมูลกลุ่มเรียนสำเร็จแล้ว</p>';

            } else {
                // --- Insert ---
                $sql_insert_group = "INSERT INTO student_groups (group_code, group_name, level_id, major_id) VALUES (?, ?, ?, ?)";
                $stmt_insert_group = $mysqli->prepare($sql_insert_group);
                 if (!$stmt_insert_group) throw new Exception("Prepare Insert Group Error: " . $mysqli->error);
                $stmt_insert_group->bind_param('ssii', $group_code, $group_name, $level_id, $major_id);
                if (!$stmt_insert_group->execute()) throw new Exception("Execute Insert Group Error: " . $stmt_insert_group->error);
                $target_group_id = $mysqli->insert_id;
                $stmt_insert_group->close();
            }

            // --- Synchronize Advisors ---
            if ($target_group_id) {
                // 1. Delete existing advisors
                $sql_delete_adv = "DELETE FROM group_advisors WHERE group_id = ?";
                $stmt_delete_adv = $mysqli->prepare($sql_delete_adv);
                 if (!$stmt_delete_adv) throw new Exception("Prepare Delete Advisors Error: " . $mysqli->error);
                $stmt_delete_adv->bind_param('i', $target_group_id);
                if (!$stmt_delete_adv->execute()) throw new Exception("Execute Delete Advisors Error: " . $stmt_delete_adv->error);
                $stmt_delete_adv->close();

                // 2. Insert selected advisors
                if (!empty($validated_advisor_ids)) { // ใช้ ID ที่ผ่านการ Validate แล้ว
                    $sql_insert_adv = "INSERT INTO group_advisors (group_id, advisor_user_id) VALUES (?, ?)";
                    $stmt_insert_adv = $mysqli->prepare($sql_insert_adv);
                    if (!$stmt_insert_adv) throw new Exception("Prepare Insert Advisors Error: " . $mysqli->error);

                    foreach ($validated_advisor_ids as $advisor_id_int) {
                        $stmt_insert_adv->bind_param('ii', $target_group_id, $advisor_id_int);
                        if (!$stmt_insert_adv->execute()) {
                            error_log("Error inserting advisor ID $advisor_id_int for group ID $target_group_id: " . $stmt_insert_adv->error);
                        }
                    }
                    $stmt_insert_adv->close();
                }
            }

            $mysqli->commit();

            if (!$is_edit_mode) {
                  $_SESSION['form_message'] = '<p class="alert alert-success text-white">เพิ่มกลุ่มเรียนใหม่สำเร็จแล้ว</p>';
             }
            header('Location: index.php?page=groups_list');
            exit;

        } catch (Exception $e) {
            $mysqli->rollback();
            $message = '<p class="alert alert-danger text-white">เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage() . '</p>';
            $selected_advisor_ids = $validated_advisor_ids;
            // ต้องดึงข้อมูล advisor ที่เลือกมาแสดงใหม่
             $selected_advisors_data = [];
             if (!empty($selected_advisor_ids)) {
                $placeholders = implode(',', array_fill(0, count($selected_advisor_ids), '?'));
                $types = str_repeat('i', count($selected_advisor_ids));
                $sql_refetch_advisors = "SELECT id as advisor_user_id, first_name, last_name FROM users WHERE id IN ($placeholders)";
                $stmt_refetch = $mysqli->prepare($sql_refetch_advisors);
                if ($stmt_refetch) {
                    $stmt_refetch->bind_param($types, ...$selected_advisor_ids);
                    $stmt_refetch->execute();
                    $result_refetch = $stmt_refetch->get_result();
                    while ($row_refetch = $result_refetch->fetch_assoc()) { $selected_advisors_data[] = $row_refetch; }
                    $stmt_refetch->close();
                }
            }
        }
    }
}

?>
<div class="container-fluid py-4">
  <div class="row">
    <div class="col-lg-8 col-md-10 mx-auto">
      <div class="card my-4">
        <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
          <div class="bg-gradient-primary shadow-primary border-radius-lg pt-4 pb-3">
            <h6 class="text-white text-capitalize ps-3"><?php echo $page_title; ?></h6>
          </div>
        </div>
        <div class="card-body">
          <?php if (!empty($message)) : ?>
          <div
            class="alert alert-dismissible text-white fade show <?php echo (strpos($message, 'success') !== false || strpos($message, 'สำเร็จ') !== false) ? 'alert-success bg-gradient-success' : 'alert-danger bg-gradient-danger'; ?>"
            role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close p-3" data-bs-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <?php endif; ?>

          <form role="form" class="text-start" action="<?php echo $form_action; ?>" method="post">
            <div class="input-group input-group-outline my-3 <?php echo !empty($group_code) ? 'is-filled' : ''; ?>">
              <label class="form-label">รหัสกลุ่ม</label>
              <input type="text" id="group_code" name="group_code" class="form-control"
                value="<?php echo htmlspecialchars($group_code); ?>" required maxlength="50">
            </div>
            <small class="d-block text-muted mb-2">รหัสตามที่วิทยาลัยกำหนด และต้องไม่ซ้ำกัน</small>

            <div class="input-group input-group-outline my-3 <?php echo !empty($group_name) ? 'is-filled' : ''; ?>">
              <label class="form-label">ชื่อกลุ่ม (เช่น สท.1/1)</label>
              <input type="text" id="group_name" name="group_name" class="form-control"
                value="<?php echo htmlspecialchars($group_name); ?>" required maxlength="100">
            </div>

            <div class="input-group input-group-static mb-4">
              <label for="level_id" class="ms-0">ระดับชั้นปี</label>
              <select class="form-control" id="level_id" name="level_id" required>
                <option value="">-- เลือกระดับชั้นปี --</option>
                <?php foreach ($levels as $level): ?>
                <option value="<?php echo $level['id']; ?>"
                  <?php echo ($level_id == $level['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($level['level_name']); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="input-group input-group-static mb-4">
              <label for="major_id" class="ms-0">สาขาวิชา</label>
              <select class="form-control" id="major_id" name="major_id" required>
                <option value="">-- เลือกสาขาวิชา --</option>
                <?php foreach ($majors as $major) : ?>
                <option value="<?php echo $major['id']; ?>"
                  <?php echo ($major_id == $major['id']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($major['name'] . ' (' . $major['major_code'] . ')'); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>

            <hr class="dark horizontal my-3">
            <p class="text-sm font-weight-bold">อาจารย์ที่ปรึกษา (เลือกได้มากกว่า 1 คน)</p>
            <div class="input-group input-group-outline mb-2">
              <label class="form-label">ค้นหาอาจารย์ (พิมพ์ชื่อ/นามสกุล)</label>
              <input type="text" id="advisor-search" class="form-control">
            </div>
            <div id="advisor-search-results" class="list-group mb-3"
              style="max-height: 150px; overflow-y: auto; border: 1px solid #d2d6da; border-radius: 0.375rem;">
            </div>
            <p class="text-sm font-weight-bold">อาจารย์ที่ปรึกษาที่เลือก:</p>
            <div id="selected-advisors" class="mb-4 d-flex flex-wrap gap-2">
              <?php foreach($selected_advisors_data as $advisor): ?>
              <span class="badge bg-gradient-secondary" data-advisor-id="<?php echo $advisor['advisor_user_id']; ?>">
                <?php echo htmlspecialchars($advisor['first_name'] . ' ' . $advisor['last_name']); ?>
                <i class="material-symbols-rounded text-sm cursor-pointer ms-1"
                  onclick="removeAdvisor(this, <?php echo $advisor['advisor_user_id']; ?>)">close</i>
                <input type="hidden" name="advisors[]" value="<?php echo $advisor['advisor_user_id']; ?>">
              </span>
              <?php endforeach; ?>
            </div>
            <div class="text-center">
              <button type="submit"
                class="btn bg-gradient-primary w-100 my-4 mb-2"><?php echo $is_edit_mode ? 'บันทึกการแก้ไข' : 'เพิ่มกลุ่มเรียน'; ?></button>
              <a href="index.php?page=groups_list" class="btn btn-outline-secondary w-100 mb-0">ยกเลิก</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const advisorSearchInput = document.getElementById('advisor-search');
const searchResultsContainer = document.getElementById('advisor-search-results');
const selectedAdvisorsContainer = document.getElementById('selected-advisors');
let searchTimeout;

// Function to add advisor
function addAdvisor(id, name) {
  // Check if already selected
  if (selectedAdvisorsContainer.querySelector(`input[value="${id}"]`)) {
    advisorSearchInput.value = ''; // Clear search
    searchResultsContainer.innerHTML = ''; // Clear results
    return; // Already selected
  }

  // Create badge for selected advisor
  const badge = document.createElement('span');
  badge.className = 'badge bg-gradient-secondary';
  badge.dataset.advisorId = id;
  badge.innerHTML = `
            ${name}
            <i class="material-symbols-rounded text-sm cursor-pointer ms-1" onclick="removeAdvisor(this, ${id})">close</i>
            <input type="hidden" name="advisors[]" value="${id}">
        `;
  selectedAdvisorsContainer.appendChild(badge);

  // Clear search input and results
  advisorSearchInput.value = '';
  searchResultsContainer.innerHTML = '';
}

// Function to remove advisor
function removeAdvisor(element, id) {
  const badge = element.closest('.badge');
  if (badge) {
    badge.remove();
  }
}

// Event listener for search input
advisorSearchInput.addEventListener('keyup', function() {
  clearTimeout(searchTimeout);
  const searchTerm = this.value.trim();

  if (searchTerm.length < 1) { // Minimum characters to search (adjust if needed)
    searchResultsContainer.innerHTML = '';
    return;
  }

  searchTimeout = setTimeout(() => {
    // --- ใช้ Path ที่ถูกต้องไปยัง search_advisors.php ---
    fetch(`search_advisors.php?term=${encodeURIComponent(searchTerm)}`)
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        searchResultsContainer.innerHTML = ''; // Clear previous results
        if (data.length > 0) {
          data.forEach(advisor => {
            // Don't show if already selected
            if (!selectedAdvisorsContainer.querySelector(`input[value="${advisor.id}"]`)) {
              const item = document.createElement('a');
              item.href = '#';
              item.className = 'list-group-item list-group-item-action py-2';
              item.textContent = advisor.label;
              item.onclick = function(e) {
                e.preventDefault();
                addAdvisor(advisor.id, advisor.label);
              };
              searchResultsContainer.appendChild(item);
            }
          });
        } else {
          searchResultsContainer.innerHTML =
            '<span class="list-group-item py-2 text-muted text-sm">ไม่พบข้อมูลอาจารย์</span>';
        }
      })
      .catch(error => {
        console.error('Error fetching advisors:', error);
        searchResultsContainer.innerHTML =
          '<span class="list-group-item py-2 text-danger text-sm">เกิดข้อผิดพลาดในการค้นหา</span>';
      });
  }, 300); // Delay before searching (milliseconds)
});

// Clear results if user clicks outside
document.addEventListener('click', function(event) {
  if (!advisorSearchInput.contains(event.target) && !searchResultsContainer.contains(event.target)) {
    searchResultsContainer.innerHTML = '';
  }
});
</script>