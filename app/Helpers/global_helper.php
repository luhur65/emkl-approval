<?php
// Migrated from CI3: application/helpers/global_helper.php
 

function ip() {
    $ipaddress = '';
    // if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
    //     $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    // else if(isset($_SERVER['HTTP_X_FORWARDED']))
    //     $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    // else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
    //     $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    // else if(isset($_SERVER['HTTP_FORWARDED']))
    //     $ipaddress = $_SERVER['HTTP_FORWARDED'];
    // else if(isset($_SERVER['REMOTE_ADDR']))
    //     $ipaddress = $_SERVER['REMOTE_ADDR'];
    // else
    //     $ipaddress = 'UNKNOWN';
    // return $ipaddress;
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
       $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}
//get_ip--------------------------------------------------------------

//get_detect--------------------------------------------------------------
function detect() {
    $agent = service('request')->getUserAgent();
    
    if ($agent->isBrowser()) {
        $user_agent = $agent->getBrowser() . ' ' . $agent->getVersion();
    } elseif ($agent->isRobot()) {
        $user_agent = $agent->getRobot();
    } elseif ($agent->isMobile()) {
        $user_agent = $agent->getMobile();
    } else {
        $user_agent = 'Unidentified User Agent';
    }
    
    $platform = $agent->getPlatform();
    $full_agent = $agent->getAgentString();
    
    return $platform
        ? $user_agent . ' on ' . $platform
        : $user_agent;
}
?>
