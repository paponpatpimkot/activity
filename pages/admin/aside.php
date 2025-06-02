<?php
// aside.php - Sidebar for Admin
// ควรจะมีการกำหนด $page ตัวแปรปัจจุบัน เพื่อใช้กำหนด class 'active' ให้เมนูที่ถูกเลือก
// $currentPage = $_GET['page'] ?? 'dashboard'; // ตัวอย่างการดึงหน้าปัจจุบัน
// ในโค้ดด้านล่าง จะยังคงใช้ active ที่ Dashboard เป็นตัวอย่าง
?>

<aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
  <div class="sidenav-header">
    <i class="fas fa-times p-3 cursor-pointer text-dark opacity-5 position-absolute end-0 top-0 d-none d-xl-none" aria-hidden="true" id="iconSidenav"></i>
    <a class="navbar-brand px-4 py-3 m-0" href="index.php?page=dashboard">
      <img src="../../assets/img/tatc_logo.gif" class="navbar-brand-img" width="26" height="26" alt="main_logo">
      <span class="ms-1 text-sm text-dark">ระบบบันทึกชั่วโมงกิจกรรม</span>
    </a>
  </div>
  <hr class="horizontal dark mt-0 mb-2">
  <div class="collapse navbar-collapse w-auto " id="sidenav-collapse-main">
    <ul class="navbar-nav">
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? 'dashboard') === 'dashboard') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=dashboard">
          <i class="material-symbols-rounded opacity-10 me-2">dashboard</i>
          <span class="nav-link-text ms-1">Dashboard</span>
        </a>
      </li>
      <h6 class="ps-3 pe-2 py-2 ms-2 me-2 text-uppercase text-xs text-white font-weight-bolder opacity-8 bg-gradient-info border-radius-md">
            <i class="material-symbols-rounded opacity-10 me-1" style="vertical-align: text-bottom;">file_present</i>
            นำเข้าข้อมูล
      </h6>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'import_data') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=import_data">
          <i class="material-symbols-rounded opacity-10 me-2">upload_file</i>
          <span class="nav-link-text ms-1">นำเข้าข้อมูล (csv)</span>
        </a>
      </li>
       <li class="nav-item mt-3">
        <h6 class="ps-3 pe-2 py-2 ms-2 me-2 text-uppercase text-xs text-white font-weight-bolder opacity-8 bg-gradient-info border-radius-md">จัดการข้อมูลพื้นฐาน</h6>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'majors_list') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=majors_list">
          <i class="material-symbols-rounded opacity-10 me-2">school</i>
          <span class="nav-link-text ms-1">จัดการสาขาวิชา</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'groups_list') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=groups_list">
          <i class="material-symbols-rounded opacity-10 me-2">groups</i>
          <span class="nav-link-text ms-1">จัดการกลุ่มเรียน</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'units_list') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=units_list">
          <i class="material-symbols-rounded opacity-10 me-2">corporate_fare</i>
          <span class="nav-link-text ms-1">จัดการหน่วยงานกิจกรรม</span>
        </a>
      </li>
       <li class="nav-item mt-3">
        <h6 class="ps-3 pe-2 py-2 ms-2 me-2 text-uppercase text-xs text-white font-weight-bolder opacity-8 bg-gradient-info border-radius-md">จัดการผู้ใช้งาน & กิจกรรม</h6>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'users_list') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=users_list">
          <i class="material-symbols-rounded opacity-10 me-2">manage_accounts</i>
          <span class="nav-link-text ms-1">จัดการข้อมูลผู้ใช้</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'students_list') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=students_list">
          <i class="material-symbols-rounded opacity-10 me-2">badge</i>
          <span class="nav-link-text ms-1">จัดการข้อมูลนักศึกษา</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'advisors_list') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=advisors_list">
          <i class="material-symbols-rounded opacity-10 me-2">supervisor_account</i>
          <span class="nav-link-text ms-1">จัดการข้อมูลครูที่ปรึกษา</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'staff_list') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=staff_list">
           <i class="material-symbols-rounded opacity-10 me-2">support_agent</i>
          <span class="nav-link-text ms-1">จัดการข้อมูลเจ้าหน้าที่</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'activities_list') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=activities_list">
           <i class="material-symbols-rounded opacity-10 me-2">local_activity</i>
          <span class="nav-link-text ms-1">จัดการกิจกรรม</span>
        </a>
      </li>
       <li class="nav-item mt-3">
        <h6 class="ps-3 pe-2 py-2 ms-2 me-2 text-uppercase text-xs text-white font-weight-bolder opacity-8 bg-gradient-info border-radius-md">การเข้าร่วมกิจกรรม</h6>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'attendance_select_activity') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=attendance_select_activity">
           <i class="material-symbols-rounded opacity-10 me-2">fact_check</i>
          <span class="nav-link-text ms-1">เช็คชื่อเข้าร่วมกิจกรรม</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'report') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=report">
           <i class="material-symbols-rounded opacity-10 me-2">assessment</i>
          <span class="nav-link-text ms-1">ดูรายงาน</span>
        </a>
      </li>
       <li class="nav-item mt-3">
        <h6 class="ps-3 pe-2 py-2 ms-2 me-2 text-uppercase text-xs text-white font-weight-bolder opacity-8 bg-gradient-info border-radius-md">บัญชี</h6>
      </li>
       <li class="nav-item">
        <a class="nav-link text-dark <?php echo (($page ?? '') === 'edit_profile') ? 'active bg-gradient-primary text-white' : ''; ?>" href="index.php?page=edit_profile">
          <i class="material-symbols-rounded opacity-10 me-2">person</i>
          <span class="nav-link-text ms-1">แก้ไขข้อมูลส่วนตัว</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link text-dark" href="logout.php">
          <i class="material-symbols-rounded opacity-10 me-2">logout</i>
          <span class="nav-link-text ms-1">ออกจากระบบ</span>
        </a>
      </li>
    </ul>
  </div>
</aside>
