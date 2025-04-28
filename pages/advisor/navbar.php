<?php
// --- ส่วนนี้ควรจะอยู่ด้านบนของไฟล์ Controller หรือไฟล์ Header ---
// --- เพื่อดึงข้อมูลผู้ใช้ที่ Login อยู่ และกำหนด $page_title ---
// session_start(); // ต้องมี session_start() ก่อนใช้งาน $_SESSION

// --- ดึงข้อมูล User ที่ Login ---
$loggedInUserId = $_SESSION['user_id'] ?? null;
$loggedInUserFirstName = $_SESSION['first_name'] ?? 'User'; // ควรมีค่าจากตอน Login
$loggedInUserLastName = $_SESSION['last_name'] ?? '';

// --- กำหนด Page Title (Controller ควรกำหนดค่านี้) ---
// ตัวอย่างการกำหนดค่าใน Controller หลัก (index.php) ก่อน include header:
/*
switch ($_GET['page'] ?? 'dashboard') {
    case 'dashboard':
        $page_title = "Dashboard";
        break;
    case 'groups_list':
        $page_title = "จัดการกลุ่มเรียน";
        break;
    // ... other cases ...
    default:
        $page_title = "ไม่พบหน้า";
}
*/
// กำหนดค่าเริ่มต้น ถ้ายังไม่มีการตั้งค่ามาจาก Controller
$page_title = $page_title ?? 'Page';
// --- กำหนด URL ---
$editProfileUrl = "index.php?page=edit_profile"; // หน้าที่แสดง edit_profile.php
$logoutUrl = "logout.php"; // สคริปต์สำหรับ Logout (ปรับ Path ตามโครงสร้างของคุณ)

?>

<nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl" id="navbarBlur" data-scroll="true">
  <div class="container-fluid py-1 px-3">

    <nav aria-label="breadcrumb">
      <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
        <li class="breadcrumb-item text-sm text-dark active" aria-current="page"><?php echo htmlspecialchars($page_title); ?></li>
      </ol>      
    </nav>
    <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
      <div class="ms-md-auto pe-md-3 d-flex align-items-center">
      </div>

      <ul class="navbar-nav justify-content-end">
        <li class="nav-item d-flex align-items-center px-2">
          <a href="javascript:;" class="nav-link text-body font-weight-bold px-0">
            <span class="d-sm-inline d-none"><?php echo htmlspecialchars($loggedInUserFirstName . ' ' . $loggedInUserLastName); ?></span>
          </a>
        </li>
        <li class="nav-item d-flex align-items-center">
          <a href="#" class="nav-link text-body p-0" id="editProfileButton" data-bs-toggle="modal" data-bs-target="#editProfileModal" aria-label="Edit Profile">
            <i class="material-symbols-rounded cursor-pointer">account_circle</i>
          </a>
        </li>
        <li class="nav-item d-flex align-items-center ps-2">
          <a href="<?php echo $logoutUrl; ?>" class="nav-link text-body p-0" aria-label="Logout" onclick="return confirm('คุณต้องการออกจากระบบใช่หรือไม่?');">
            <i class="material-symbols-rounded cursor-pointer">logout</i>
          </a>
        </li>
        <li class="nav-item d-xl-none ps-3 d-flex align-items-center">
          <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav">
            <div class="sidenav-toggler-inner">
              <i class="sidenav-toggler-line"></i>
              <i class="sidenav-toggler-line"></i>
              <i class="sidenav-toggler-line"></i>
            </div>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title font-weight-normal" id="editProfileModalLabel">แก้ไขข้อมูลส่วนตัว</h5>
        <button type="button" class="btn-close text-dark" data-bs-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-0">
        <iframe src="<?php echo $editProfileUrl; ?>" frameborder="0" style="width: 100%; min-height: 70vh;" title="Edit Profile Form"></iframe>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn bg-gradient-secondary" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>