<?php
// --- ดึงข้อมูล User ที่ Login ---
$loggedInUserId = $_SESSION['user_id'] ?? null;
$loggedInUserFirstName = $_SESSION['first_name'] ?? 'User'; // ควรมีค่าจากตอน Login
$loggedInUserLastName = $_SESSION['last_name'] ?? '';
?>
<nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl" id="navbarBlur" data-scroll="true">
  <div class="container-fluid py-1 px-3">

    <div class="d-flex align-items-center">
        <ul class="navbar-nav">
            <li class="nav-item d-xl-none ps-0 pe-3 d-flex align-items-center">
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
    <div class="ms-auto d-flex align-items-center">
        <!-- <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarUserInfo" aria-controls="navbarUserInfo" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button> -->

        <div class="collapse navbar-collapse" id="navbarUserInfo"> <ul class="navbar-nav align-items-center">
                <li class="nav-item px-2">
                    <a href="javascript:;" class="nav-link text-body font-weight-bold px-0">
                        <span class="d-sm-inline d-none"><?php echo htmlspecialchars($loggedInUserFirstName . ' ' . $loggedInUserLastName); ?></span>
                        <span class="d-inline d-sm-none"><?php echo htmlspecialchars($loggedInUserFirstName); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=edit_profile" class="nav-link text-body font-weight-bold px-0">
                        <i class="material-symbols-rounded">account_circle</i>
                    </a>
                </li>
                </ul>
        </div>
    </div>
    </div>
</nav>