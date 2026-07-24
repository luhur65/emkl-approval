<style>
  .nav-link.hover {
    background-color: rgba(255, 255, 255, .1);
    color: #fff;
  }
  .selected-link {
    background-color: #007bff !important;
    color: #fff !important;
  }
</style>

<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <!-- Brand Logo -->
    <!-- <a href="<?= base_url('home') ?>" class="brand-link">
        <span class="brand-text font-weight-light pl-2">EMKL APPROVAL</span>
    </a> -->

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="<?= asset('libraries/adminlte/dist/img/user2-160x160.jpg') ?>" class="img-circle elevation-2" alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block"><?= strtoupper(session()->get('FNamaUser') ?? session()->get('FUserID')) ?></a>
            </div>
        </div>

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <ul class="nav nav-pills nav-sidebar flex-column nav-child-indent" data-widget="treeview" role="menu" data-accordion="false">
                
                <li class="nav-item">
                    <a href="<?= base_url('home') ?>" class="nav-link">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                <?php 
                $userId = session()->get('FUserID');
                if (checkMenu($userId)) { 
                ?>
                <li class="nav-item has-treeview">
                    <a href="#" class="nav-link">
                        <i class="nav-icon fas fa-check-square"></i>
                        <p>
                            Approval
                            <i class="right fas fa-angle-left"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <li class="nav-item">
                            <a href="<?= base_url('approvaltop') ?>" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>TOP Pre Orderan Job</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('approvalpo') ?>" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Permintaan Order</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('approvalpharga') ?>" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Penawaran Harga</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('approvalpharga/cetak') ?>" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Cetak Ulang PH</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('approvaltrip/approval') ?>" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Approval Trip</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('approvalpengajuan') ?>" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Approval Pengajuan</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('approvalabsensi') ?>" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Approval Absensi</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?= base_url('approvalextrasupir') ?>" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Approval Extra Supir</p>
                            </a>
                        </li>
                    </ul>
                </li>
                <?php } ?>

                <?php if (checkMenuMandor($userId, 'TRIP')) { ?>
                <li class="nav-item">
                    <a href="<?= base_url('approvaltrip') ?>" class="nav-link">
                        <i class="nav-icon fas fa-truck"></i>
                        <p>Trip</p>
                    </a>
                </li>
                <?php } ?>

                <?php if (checkMenuMandor($userId, 'PENGAJUANSUPIRSERAP')) { ?>
                <li class="nav-item">
                    <a href="<?= base_url('pengajuan') ?>" class="nav-link">
                        <i class="nav-icon fas fa-user-friends"></i>
                        <p>Pengajuan Supir Serap</p>
                    </a>
                </li>
                <?php } ?>
                
                <li class="nav-item">
                    <a href="<?= base_url('login/logout') ?>" class="nav-link">
                        <i class="nav-icon fas fa-sign-out-alt"></i>
                        <p>Logout</p>
                    </a>
                </li>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
