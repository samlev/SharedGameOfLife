<?php
// include the database and connect
require_once('config.php');
$db = mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS) or die('Cannot connect to database: '. mysql_error());
mysql_select_db(MYSQL_NAME, $db);

// Try to make a new generation
new_generation();

/** Runs a basic query */
function run_query($query, $link=null) {
    if ($link !== null) {
        $result = mysql_query($query, $link);
    } else {
        $result = mysql_query($query);
    }
    
    // check for an error
    if (!$result) {
        $error = "<p><b>MySQL Error:</b>".mysql_error();
        $error .= "<pre>Query:";
        $error .= "\n------------------------------------------------------------\n";
        $error .= $query;
        $error .= "\n------------------------------------------------------------\n";
        $error .= "Backtrace:";
        $error .= "\n------------------------------------------------------------\n";
        ob_start();
        debug_print_backtrace();
        $error .= ob_get_clean();
        $error .= "\n------------------------------------------------------------\n";
        $error .= "<pre></p>";
        
        // display and exit
        die($error);
    }
    
    return $result;
}

// Gets a generation lock
function get_gen_lock() {
    // check if we've already asked for the generation lock
    if (!defined('GEN_LOCK')) {
        // lock the tables required
        run_query("LOCK TABLES `lock` WRITE, `generations` READ");
        // see if there was a lock within the last 30 seconds
        // (normally, locks should be released, but if the locking script fails, assume that after 30 seconds, it's timed out)
        $q = "SELECT `lockdate` FROM `lock` WHERE `lockdate` >= DATE_SUB(NOW(), INTERVAL 30 SECONDS) LOCK IN SHARE MODE";
        $res = run_query($q);
        
        // is there a current lock?
        if (mysql_num_rows($res)==0) {
            // check if we should be making a new generation
            $q = "SELECT `id`
                  FROM `generations`
                  WHERE `generated` > DATE_SUB(NOW(), INTERVAL ".intval(GENERATION_LIMIT)." SECONDS)";
            $res = run_query($q);
            
            if (mysql_num_rows($res)==0) {
                // no generations yet this period - get the lock!
                run_query('INSERT INTO `lock` (`lockdate`) VALUES (NOW())');
                // unlock, and inform the caller that we've got the lock!
                run_query('UNLOCK TABLES');
                define('GEN_LOCK', true);
            } else {
                // last generation was too recent - unlock and slink away
                run_query('UNLOCK TABLES');
                define('GEN_LOCK', false);
            }
        } else {
            // there's a current lock - unlock and slink away
            run_query('UNLOCK TABLES');
            define('GEN_LOCK', false);
        }
    }
    return GEN_LOCK;
}

// releases the generation lock
function release_gen_lock() {
    // only release if we actually HAVE the generation lock
    if (get_gen_lock()) {
        // lock, empty, and unlock the table
        run_query("LOCK TABLES `lock` WRITE");
        run_query("TRUNCATE TABLE `lock`");
        run_query('UNLOCK TABLES');
    }
}

// makes a new generation
function new_generation() {
    // try to get the generation lock
    if (get_gen_lock()) {
        
        // work is done now - release the generation lock
        release_gen_lock();
    }
}

// gets the latest generation
function latest_generation() {
    $query = "SELECT `id`,`key`,`generated`,`position`,`change`
              FROM `generations`
              WHERE 1
              ORDER BY `generated` DESC
              LIMIT 1";
    $res = run_query($query);
    
    
}
?>