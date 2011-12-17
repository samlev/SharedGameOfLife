<?php
// pull in the required functions
require_once('functions.php');

// make a new generation!
new_generation();

// check if we want the next evolution, or the latest evolution
if (isset($_GET['last']) && strlen(trim($_GET['last']))>=7) {
    // split the 'last' into it's component parts = a 6 char key, and the rest is the ID
    $k = substr(trim($_GET['last']),0,6);
    $id = intval(substr(trim($_GET['last']),6));
    
    $lv = check_generation($k,$id);
    
    if ($lv) {
        $nv = next_generation($id);
        
        if ($nv) {
            // return the full position, and assume that it's blank
            $gen = array('key'=>$nv['ref'],
                         'change'=>map_to_array($nv['change']));
        } else {
            $gen = false;
        }
    } else {
        $error = "Generation doesn't exist";
    }
} else {
    // just get the latest generation
    $nv = latest_generation();
    
    // return the full position, and assume that it's blank
    $gen = array('key'=>$nv['ref'],
                 'change'=>map_to_array($nv['position'],true));
}

// check for an error
if (isset($error)) {
    $data = array("success"=>false,"info"=>$error);
} else {
    $data = array("success"=>true,"generation"=>$gen);
}

// return the response
header('Content-type: application/javascript');
echo json_encode ($data);
?>