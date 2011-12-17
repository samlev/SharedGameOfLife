<?php
// include the database and connect
require_once('config.php');
$db = mysql_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS) or die('Cannot connect to database: '. mysql_error());
mysql_select_db(MYSQL_NAME, $db);

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
        // get the last generation
        $lastgen = latest_generation();
        $oldgrid = $lastgen['position'];
        
        // the new generation
        $grid = array();
        $changes = array();
        
        for ($i=0;$i<200;$i++) {
            $grid[$i] = array();
            for ($j=0;$j<200;$j++) {
                $grid[$i][$j] = false;
                $n = count_neighbours($oldgrid,$i,$j);
                
                if ($oldgrid[$i][$j]) {
                    // is the cell in the 'live' range?
                    if ($n < 2 || $n > 3) {
                        $grid[$i][$j] = true;
                    } else {
                        // mark the change
                        if (!isset($changes[$i])) {
                            $changes[$i] = array();
                        }
                        $changes[$i][$j] = false;
                    }
                } else {
                    // check if we're able to breed
                    if ($n == 3) {
                        $grid[$i][$j] = true;
                        
                        // mark the change
                        if (!isset($changes[$i])) {
                            $changes[$i] = array();
                        }
                        $changes[$i][$j] = true;
                    }
                }
            }
        }
        
        // now we've done that - let's add any waiting pieces to the board
        // first lock the table
        run_query("LOCK TABLES `waitinglife` WRITE");
        // now get everything currently in there
        $q = "SELECT `id`,`type`,`orientation`,`xpos`,`ypos`
              FROM `waitinglife`
              WHERE 1";
        $res = run_query($q);
        
        while ($row = mysql_fetch_assoc($res)) {
            $newlife = get_pos($row['type'],$row['orientation'],$row['xpos'],$row['ypos']);
            
            foreach ($newlife as $x=>$r) {
                foreach ($r as $y=>$dummy) {
                    // only update if there's a change
                    if (!$grid[$x][$y]) {
                        $grid[$x][$y] = true;
                        
                        // mark the change
                        if (!isset($changes[$x])) {
                            $changes[$x] = array();
                        }
                        // is a change already recorded here?
                        if (isset($changes[$x][$y])) {
                            // get rid of the change
                            unset($changes[$x][$y]);
                        } else {
                            // mark the change
                            $changes[$x][$y] = true;
                        }
                    }
                }
            }
        }
        // empty
        run_query("TRUNCATE TABLE `waitinglife`");
        
        // and release
        run_query('UNLOCK TABLES');
        
        // pick a random key for this new generation
        $key = substr(md5($lastgen['key'].time()),rand(0,20),6);
        // serialize our position and changes
        $s_pos = serialize($grid);
        $s_cng = serialize($changes);
        
        // add the new generation
        $q = "INSERT INTO `generations`
                (`key`,`generated`,`position`,`change`)
              VALUES ('$key',NOW(),'".mysql_real_escape_string($s_pos)."','".mysql_real_escape_string($s_cng)."')";
        run_query($q);
        
        // work is done now - release the generation lock
        release_gen_lock();
    }
}

function count_neighbours($grid,$x,$y) {
    // set the search bounds
    $min_x = ($x > 0 ? $x-1 : $x);
    $max_x = ($x < 199 ? $x+1: $x);
    $min_y = ($y > 0 ? $y-1 : $y);
    $max_y = ($y < 199 ? $y+1: $y);
    
    // initialise the number of neighbors
    $neighbours = 0;
    
    // now perform the search
    for ($i=$min_x;$i<=$max_x;$i++) {
        for ($j=$min_y;$j<=$max_y;$j++) {
            // ignore the item we're looking for neighbours for
            if (!($i==$x && $j==$y )) {
                if ($grid[$i][$j]) {
                    // count the neighbour
                    $neighbours ++ ;
                }
            }
        }
    }
    
    // and return what we've found
    return $neighbours;
}

// gets the latest generation
function latest_generation() {
    $query = "SELECT `id`,`key`,`generated`,`position`,`change`
              FROM `generations`
              WHERE 1
              ORDER BY `generated` DESC
              LIMIT 1";
    $res = run_query($query);
    
    $latest = array();
    
    // do we have a 'latest' generation?
    if ($row = mysql_fetch_assoc($res)) {
        // get the relevant fields
        $latest['id']=$row['id'];
        $latest['key']=$row['key'];
        $latest['ref']=$row['key'].$row['id'];
        $latest['generated']=$row['generated'];
        // the current position
        $latest['position']=unserialize($row['position']);
        // what has changed since the last generation
        $latest['change']=unserialize($row['change']);
    } else {
        // no we don't - must be time to start a new game!
        $latest['id']=0;
        $latest['key']='';
        $latest['ref']='';
        $latest['generated']='1970-01-01 00:00:00';
        
        // make a blank grid
        $postemp = array();
        for ($i = 0; $i < 200; $i++) {
            $postemp[$i] = array();
            for ($j = 0; $j < 200; $j++) {
                $postemp[$i][$j] = false;
            }
        }
        
        // the current position
        $latest['position']=$postemp;
        // nothing has changed
        $latest['change']=array();
    }
    
    return $latest;
}

function get_pos($type, $orientation, $xpos, $ypos) {
    // check for rotation (N is default)
    switch ($orientation) {
        case 'W':
            
            break;
        case 'S':
            
            break;
        case 'E':
            
            break;
        case 'N':
        default:
            
            break;
    }
}
?>