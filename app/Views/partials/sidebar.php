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

                // Daftar submenu Approval. Ubah isi $menuAktif untuk menampilkan/menyembunyikan.
                $menuApproval = [
                    'approvaltop'          => 'TOP Pre Orderan Job',
                    'approvalpo'           => 'Permintaan Order',
                    'approvalpharga'       => 'Penawaran Harga',
                    'approvalpharga/cetak' => 'Cetak Ulang PH',
                    'approvaltrip/approval'=> 'Approval Trip',
                    'approvalpengajuan'    => 'Approval Pengajuan',
                    'approvalabsensi'      => 'Approval Absensi',
                    'approvalextrasupir'   => 'Approval Extra Supir',
                ];
                $menuAktif = ['approvaltop', 'approvalpo'];

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
                        <?php foreach ($menuApproval as $route => $label): ?>
                            <?php if (! in_array($route, $menuAktif, true)) continue; ?>
                        <li class="nav-item">
                            <a href="<?= base_url($route) ?>" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p><?= esc($label) ?></p>
                            </a>
                        </li>
                        <?php endforeach; ?>
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
