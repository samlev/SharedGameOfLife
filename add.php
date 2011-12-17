<?php
// pull in the required functions
require_once('functions.php');

// do we already have a session?
if (isset($_GET['session'])) {
    $session_key = $_GET['session'];
    
    run_query('START TRANSACTION');
    
    // check if the session is still valid
    $q = "SELECT `id`,`key`,`expires`,`liferemaining`, NOW() as servertime
          FROM `sessions`
          WHERE `key` LIKE '".mysql_real_escape_string($session_key)."'
          AND `expires` > NOW()
          FOR UPDATE";
    $res = run_query($q);
    
    if ($row  = mysql_fetch_assoc($res)) {
        $se = array();
        // we have a session already!
        $se['id'] = $row['id'];
        $se['key'] = $row['key'];
        $se['expires'] = strtotime($row['expires']) - strtotime($row['servertime']);
        $se['liferemaining'] = intval($row['liferemaining']);
        
        $type = $_GET["type"];
        
        // get the block type
        if (in_array($type,array('single','block','glider','lwss','pulsar'))) {
            // get the orientation
            $orientation = (in_array($_GET['orientation'],array('N','E','S','W'))?$_GET['orientation']:'N');
            
            $types = array('single'=>1,'block'=>4,'glider'=>5,'lwss'=>9,'pulsar'=>48);
            
            if ($se['liferemaining'] >= $types[$type]) {
                $x = intval($_GET['xpos']);
                $y = intval($_GET['ypos']);
                
                if ($x >= 0 && $x < 200 && $y >= 0 && $y < 200) {
                    $sid = intval($se['id']);
                    
                    $q = "INSERT INTO `waitinglife`
                            (`session`,`type`,`orientation`,`xpos`,`ypos`)
                          VALUES ($sid,'$type','$orientation',$x,$y)";
                    run_query($q);
                    
                    $remaining = $se['liferemaining'] - $types[$type]; 
                    
                    // now update the session appropriately
                    $q = "UPDATE `sessions` SET `liferemaining`=$remaining
                          WHERE `id`=$sid";
                    run_query($q);
                } else {
                    $error = "Out of bounds - cannot place there.";
                }
            } else {
                $error = "Not enough life for that. Your life will refill in ".$se['expires']." seconds.";
            }
        } else {
            $error = "Life type not valid";
        }
    } else {
        $error = "Session not valid";
    }
    
    // release the row-lock
    run_query('COMMIT');
}

// check for an error
if (isset($error)) {
    $data = array("success"=>false,"info"=>$error);
} else {
    $data = array("success"=>true);
}

// return the response
header('Content-type: application/javascript');
echo json_encode ($data);
?>