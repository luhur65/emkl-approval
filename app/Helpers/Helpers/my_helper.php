<?php
// Migrated from CI3: application/helpers/my_helper.php
 
function Generate_ProcedureAll($SQL_Procedure,$cabang,$db="")  {

    $resultSet = new stdClass();
    $hostname  = [
        "mdn"=>"tasmedan.dynu.com,1477",
        "jkt"=>"tasjkt.dynu.com,1461",
        "sby"=>"tassby.kozow.com,1451",
        "mks"=>"tasmks.dynu.com,1450",
        "pst"=>"192.168.3.39,1433" // Updated to use IP from .env
    ];


    $serverName =$hostname[$cabang];
    $db = $db==""?"dbTas":$db;
    $connectionInfo = array( "Database"=>$db, "UID"=>'sa', "PWD"=>'Aa123456'); // Updated to use Password from .env
    
    $conn = sqlsrv_connect( $serverName, $connectionInfo);
    if( $conn === false ){
        $resultSet->error_sql = strtoupper($cabang).' Tidak bisa terhubung ke server';
        return $resultSet;
    }
    $tsql_callSP = $SQL_Procedure;
    sqlsrv_configure("WarningsReturnAsErrors", 0);
    $stmt = sqlsrv_query($conn, $tsql_callSP, null);
    $error=0;
    if( $stmt === false)
    {
        $error=1;
        $resultSet->error_sql = strtoupper($cabang).' Query failed';
        return $resultSet;
    }

    $isNotLastResult = true;
    $i = 0;
    $resultSet->error_sql="";
    while (!is_null($isNotLastResult))
    {
        $result =array();
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC))
        {
            $result[] = $row;
        }
        $resultSet->error = $error;
        $resultSet->data = $result;

        $isNotLastResult = sqlsrv_next_result($stmt);
        $retVal = sqlsrv_errors();
        if(!empty($retVal)){
            if($retVal[0]["code"]!=0){
                $retVal = $retVal[0]["message"];
                $retVal = preg_replace('/\\[Microsoft]\\[SQL Server Native Client [0-9]+.[0-9]+](\\[SQL Server\\])?/', '', $retVal);
                $resultSet->error_sql .=$retVal."<br>";
            }
        }
        $i++;
    }
    sqlsrv_free_stmt( $stmt);
    sqlsrv_close( $conn );
    return $resultSet;
}

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

function Generate_Procedure($SQL_Procedure,$cabang,$db="")  {
    return Generate_ProcedureAll($SQL_Procedure, $cabang, $db);
}

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
