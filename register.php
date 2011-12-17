<?php
// pull in the required functions
require_once('functions.php');

$se = array();

// do we already have a session?
if (isset($_COOKIE['session'])) {
    $session_key = $_COOKIE['session'];
    
    run_query('START TRANSACTION');
    
    // check if the session is still valid
    $q = "SELECT `id`,`key`,`expires`,`liferemaining`, NOW() as servertime
          FROM `sessions`
          WHERE `key` LIKE '".mysql_real_escape_string($session_key)."'
          AND `expires` > NOW()
          LOCK IN SHARE MODE";
    $res = run_query($q);
    
    if ($row  = mysql_fetch_assoc($res)) {
        // we have a session already!
        $se['key'] = $row['key'];
        $se['expires'] = strtotime($row['expires']) - strtotime($row['servertime']);
        $se['liferemaining'] = intval($row['liferemaining']);
    }
    
    // release the row-lock
    run_query('COMMIT');
}

// if we've resulted in no session, start a new one!
if (count($se) == 0) {
    // generate a random key
    $key = md5(time().$_SERVER['HTTP_USER_AGENT'].rand());
    
    // lock the table
    run_query("LOCK TABLES `sessions` WRITE");
    
    // ensure the key is unique
    $q = "SELECT `id` FROM `sessions` WHERE `key` LIKE '$key'";
    $res = run_query($q);
    // only allow 5 attempts
    $safety = 5;
    
    while (mysql_num_rows($res) && $safety) {
        // generate a new random key
        $key = md5(time().$_SERVER['HTTP_USER_AGENT'].rand());
        // check if it is unique
        $q = "SELECT `id` FROM `sessions` WHERE `key` LIKE '$key'";
        $res = run_query($q);
        // reduce the safety number
        $safety --;
    }
    
    // make sure that we've not hit the safety limit
    if ($safety) {
        // add the session to the database
        $q = "INSERT INTO `sessions`
                (`key`,`expires`,`liferemaining`)
              VALUES ('$key', DATE_ADD(NOW(), INTERVAL 30 MINUTE), 50)";
        run_query($q);
        
        // add the cookie
        setcookie('session',$key,1800);
        
        // and set the return value
        $se['key'] = $key;
        $se['expires'] = 1800;
        $se['liferemaining'] = 50;
    } else {
        $error = "Could not start a new session";
    }
    
    // release the lock
    run_query('UNLOCK TABLES');
}

// check for an error
if (isset($error)) {
    $data = array("success"=>false,"info"=>$error);
} else {
    $data = array("success"=>true,"session"=>$se);
}

// return the response
header('Content-type: application/javascript');
echo json_encode ($data);
?>