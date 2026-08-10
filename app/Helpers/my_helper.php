<?php
// Migrated from CI3: application/helpers/my_helper.php
//
// CATATAN: Generate_ProcedureAll() / Generate_Procedure() warisan CI3 sudah
// DIHAPUS dari sini. Isinya peta hostname SQL Server per cabang (mdn/jkt/sby/
// mks/pst) plus user & password `sa` yang ditulis langsung di kode -- artinya
// setiap kali ada cabang baru file ini harus diubah, dan kredensial produksi
// ikut masuk ke repository. Tidak ada satu pun pemanggilnya di aplikasi CI4
// ini: seluruh model sudah memakai koneksi CodeIgniter (`default` / `dbtruck2`)
// yang dikonfigurasi lewat .env per server. Kalau nanti perlu memanggil Stored
// Procedure lintas cabang, tambahkan grup koneksi baru di Config\Database +
// .env, jangan hidupkan kembali fungsi ini.

function string_sanitize($str) {
    $str = str_replace(array('\'', '"'), '', $str);
    return $str;
}

function print_recursive_list($data)
{
    $str = "";
    if (empty($data)) return $str;
    
    foreach($data as $list)
    {
        $menuexe = $list['menuexe']=="0"?"#":base_url().$list['menuexe'];
        $menuexe = $list['link']!=''?$list['link']:$menuexe;
        $subchild = print_recursive_list($list['child']);
            
        if ($subchild == ''){
            $str .= "<li class='dropdown ".$list['menuicon']."'><a href='".$menuexe."'>".$list['menuname']."</a>";
        }else{
            $str .= "<li class='dropdown ".$list['menuicon']."'><a class='havesub'>".$list['menuname']."</a>";
        }

        if($subchild != ''){
            $str .= "<ul class='sub-menu'>".$subchild."</ul>";
        }
        $str .= "</li>";
    }
    return $str;
}

function print_sidebar_menu($data)
{
    $str = "";

    if (empty($data)) {
        return $str;
    }

    foreach ($data as $list) {

        $menuexe = ($list['menuexe'] == "0")
            ? "javascript:void(0)"
            : base_url($list['menuexe']);

        $menuexe = ($list['link'] != '')
            ? $list['link']
            : $menuexe;

        $subchild = print_sidebar_menu($list['child']);
        $hasChild = ($subchild != '');

        $str .= '<li class="nav-item">';

        $str .= '<a 
                    id="link-' . strtolower($list['menuname']) . '" 
                    href="' . $menuexe . '" 
                    class="nav-link">';

        // DENGAN ICON
        $iconClass = !empty($list['menuicon']) ? $list['menuicon'] : 'far fa-circle';
        $str .= '<i class="nav-icon ' . $iconClass . '"></i>';
        $str .= '<p>';
        $str .= strtoupper($list['menuname']);

        // icon panah submenu
        if ($hasChild) {
            $str .= '<i class="right fas fa-angle-left"></i>';
        }

        $str .= '</p>';
        $str .= '</a>';

        // submenu
        if ($hasChild) {
            $str .= '<ul class="ml-4 nav nav-treeview">';
            $str .= $subchild;
            $str .= '</ul>';
        }

        $str .= '</li>';
    }

    return $str;
}

// function print_sidebar_menu($data)
// {
//     $str = "";
//     if (empty($data)) return $str;

//     foreach ($data as $list) {
//         $menuexe = $list['menuexe'] == "0" ? "javascript:void(0)" : base_url($list['menuexe']);
//         $menuexe = $list['link'] != '' ? $list['link'] : $menuexe;
//         $subchild = print_sidebar_menu($list['child']);
//         $hasChild = ($subchild != '');

//         $str .= '<li class="nav-item ' . ($hasChild ? 'has-treeview' : '') . '">';
//         $str .= '<a href="' . $menuexe . '" class="nav-link">';
//         $str .= '<i class="nav-icon ' . ($list['menuicon'] ?: 'fas fa-circle') . '"></i>';
//         $str .= '<p>' . $list['menuname'];
//         if ($hasChild) {
//             $str .= '<i class="right fas fa-angle-left"></i>';
//         }
//         $str .= '</p></a>';

//         if ($hasChild) {
//             $str .= '<ul class="nav nav-treeview">' . $subchild . '</ul>';
//         }
//         $str .= '</li>';
//     }
//     return $str;
// }

//custom function
if(!function_exists('hasPermission')){
    function hasPermission($class,$method){
        static $auth = null;
        if ($auth === null) {
            $auth = new \App\Libraries\MyAuth([
                'isLogin' => session()->get(SESSION_NAME.'logged_in') ? 1 : 0,
                'userPK'  => session()->get(SESSION_NAME.'userpk') ?: 0,
                'baseUrl' => base_url()
            ]);
        }
        return $auth->hasPermission($class,$method);
    }
}

function getTableWhere($table,$where,$isOne=0){
    $db = \Config\Database::connect();
    $builder = $db->table($table);
    $builder->where($where);
    $sql = $builder->get();
    return $isOne==0?$sql->getResult():$sql->getRow();
}

function getComboParameter($tipe){
    $data="{value:'',text:'All'},";
    $sql = getParameter($tipe);
    foreach ($sql as $key) {
        $data .="{value:'".$key->parameter_key."',text:'".$key->parametertext."'},";
    }
    $data=trim($data, ',');
    return $data;
}

function getParameterKey($key){
    $db = \Config\Database::connect();
    $builder = $db->table('tblparameter');
    $builder->where('parameter_key',$key);
    $sql = $builder->get();
    return $sql->getRow();
}

function getParameter($tipe){
    $db = \Config\Database::connect();
    $builder = $db->table('tblparameter');
    $builder->where('parametertype',$tipe);
    $sql = $builder->get();
    return $sql->getResult();
}

function escapeString($val){
    return $val;
}

function format_rupiah($angka){
  $rupiah=number_format($angka,0,',','.');
  return $rupiah;
}

// ==========================================
// RESTORASI HELPER MENU EMKL APPROVAL LAMA
// ==========================================
if(!function_exists('checkMenu')){
    function checkMenu($pUser = '', $menu = '')
    {
        $db = \Config\Database::connect();
        $builder = $db->table('fusermenu');
        $builder->where('FMenuShowOrder', '191014');
        $builder->where('FMenuId', $pUser);
        $query = $builder->get();
        return $query->getNumRows() > 0 ? true : false;
    }
}

if(!function_exists('checkMenuMandor')){
    function checkMenuMandor($pUser = '', $menu = '')
    {
        $db = \Config\Database::connect();
        $builder = $db->table('FUserListParameter');
        $builder->where('fuserid', $pUser);
        $builder->where('FNamaParam', $menu);
        $query = $builder->get();
        return $query->getNumRows() > 0 ? true : false;
    }
}

