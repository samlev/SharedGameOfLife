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
        $q = "SELECT `lockdate` FROM `lock` WHERE `lockdate` >= DATE_SUB(NOW(), INTERVAL 30 SECOND) LOCK IN SHARE MODE";
        $res = run_query($q);
        
        // is there a current lock?
        if (mysql_num_rows($res)==0) {
            // check if we should be making a new generation
            $q = "SELECT `id`
                  FROM `generations`
                  WHERE `generated` > DATE_SUB(NOW(), INTERVAL ".intval(GENERATION_LIMIT)." SECOND)";
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
        run_query("DELETE FROM `lock` WHERE 1");
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
        run_query("DELETE FROM `waitinglife` WHERE 1");
        
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

// simple check to see if a generation exists
function check_generation($key, $id) {
    $q = "SELECT `generated`
          FROM `generations`
          WHERE `id`=".intval($id)."
          AND `key` LIKE '".mysql_real_escape_string($key)."'";
    $res = run_query($q);
    
    if (mysql_num_rows($res)) {
        return true;
    }
    
    return false;
}

// gets the next generation after the ID
function next_generation($id) {
    $q = "SELECT `id`,`key`,`generated`,`position`,`change`
          FROM `generations`
          WHERE `id`=".(intval($id)+1);
    $res = run_query($q);
    
    if ($row = mysql_fetch_assoc($res)) {
        $next = array();
        // get the relevant fields
        $next['id']=$row['id'];
        $next['key']=$row['key'];
        $next['ref']=$row['key'].$row['id'];
        $next['generated']=$row['generated'];
        // the current position
        $next['position']=unserialize($row['position']);
        // what has changed since the last generation
        $next['change']=unserialize($row['change']);
        
        return $next;
    }
    
    return false;
}

function get_pos($type, $orientation, $xpos, $ypos) {
    $item = array();
    
    switch ($type) {
        case 'single':
            /* simple (no rotation) - position like this:
             * [*]
             */
            $item[$xpos] = array();
            $item[$xpos][$ypos] = true;
            break;
        case 'block':
            /* simple (no rotation) - position like this:
             * [*][*]
             * [*][*]
             */
            $item[$xpos] = array();
            $item[$xpos][$ypos] = true;
            $item[$xpos][$ypos+1] = true;
            $item[$xpos+1] = array();
            $item[$xpos+1][$ypos] = true;
            $item[$xpos+1][$ypos+1] = true;
            break;
        case 'glider':
            // check for rotation (N is default)
            switch ($orientation) {
                case 'W':
                    /* position like this (270 degree rotation off N):
                     * [ ][*][*]
                     * [*][ ][*]
                     * [ ][ ][*]
                     */
                    $item[$xpos] = array();
                    $item[$xpos][$ypos+1] = true;
                    $item[$xpos][$ypos+2] = true;
                    $item[$xpos+1] = array();
                    $item[$xpos+1][$ypos] = true;
                    $item[$xpos+1][$ypos+2] = true;
                    $item[$xpos+2] = array();
                    $item[$xpos+2][$ypos+2] = true;
                    break;
                case 'S':
                    /* position like this (180 degree rotation off N):
                     * [*][*][*]
                     * [*][ ][ ]
                     * [ ][*][ ]
                     */
                    $item[$xpos] = array();
                    $item[$xpos][$ypos] = true;
                    $item[$xpos][$ypos+1] = true;
                    $item[$xpos][$ypos+2] = true;
                    $item[$xpos+1] = array();
                    $item[$xpos+1][$ypos] = true;
                    $item[$xpos+2] = array();
                    $item[$xpos+2][$ypos+1] = true;
                    break;
                case 'E':
                    /* position like this (90 degree rotation off N):
                     * [*][ ][ ]
                     * [*][ ][*]
                     * [*][*][ ]
                     */
                    $item[$xpos] = array();
                    $item[$xpos][$ypos] = true;
                    $item[$xpos+1] = array();
                    $item[$xpos+1][$ypos] = true;
                    $item[$xpos+1][$ypos+2] = true;
                    $item[$xpos+2] = array();
                    $item[$xpos+2][$ypos] = true;
                    $item[$xpos+2][$ypos+1] = true;
                    break;
                case 'N':
                default:
                    /* position like this:
                     * [ ][*][ ]
                     * [ ][ ][*]
                     * [*][*][*]
                     */
                    $item[$xpos] = array();
                    $item[$xpos][$ypos+1] = true;
                    $item[$xpos+1] = array();
                    $item[$xpos+1][$ypos+2] = true;
                    $item[$xpos+2] = array();
                    $item[$xpos+2][$ypos] = true;
                    $item[$xpos+2][$ypos+1] = true;
                    $item[$xpos+2][$ypos+2] = true;
                    break;
            }
            break;
        case 'lwss':
            // check for rotation (N is default)
            switch ($orientation) {
                case 'W':
                    /* position like this (270 degree rotation off N):
                     * [ ][*][*][*]
                     * [*][ ][ ][*]
                     * [ ][ ][ ][*]
                     * [ ][ ][ ][*]
                     * [*][ ][*][ ]
                     */
                    $item[$xpos] = array();
                    $item[$xpos][$ypos+1] = true;
                    $item[$xpos][$ypos+2] = true;
                    $item[$xpos][$ypos+3] = true;
                    $item[$xpos+1] = array();
                    $item[$xpos+1][$ypos] = true;
                    $item[$xpos+1][$ypos+3] = true;
                    $item[$xpos+2] = array();
                    $item[$xpos+2][$ypos+3] = true;
                    $item[$xpos+3] = array();
                    $item[$xpos+3][$ypos+3] = true;
                    $item[$xpos+4] = array();
                    $item[$xpos+4][$ypos] = true;
                    $item[$xpos+4][$ypos+2] = true;
                    break;
                case 'S':
                    /* position like this (180 degree rotation off N):
                     * [*][*][*][*][ ]
                     * [*][ ][ ][ ][*]
                     * [*][ ][ ][ ][ ]
                     * [ ][*][ ][ ][*]
                     */
                    $item[$xpos] = array();
                    $item[$xpos][$ypos] = true;
                    $item[$xpos][$ypos+1] = true;
                    $item[$xpos][$ypos+2] = true;
                    $item[$xpos][$ypos+3] = true;
                    $item[$xpos+1] = array();
                    $item[$xpos+1][$ypos] = true;
                    $item[$xpos+1][$ypos+4] = true;
                    $item[$xpos+2] = array();
                    $item[$xpos+2][$ypos] = true;
                    $item[$xpos+3] = array();
                    $item[$xpos+3][$ypos+1] = true;
                    $item[$xpos+3][$ypos+4] = true;
                    break;
                case 'E':
                    /* position like this (90 degree rotation off N):
                     * [ ][*][ ][*]
                     * [*][ ][ ][ ]
                     * [*][ ][ ][ ]
                     * [*][ ][ ][*]
                     * [*][*][*][ ]
                     */
                    $item[$xpos] = array();
                    $item[$xpos][$ypos+1] = true;
                    $item[$xpos][$ypos+3] = true;
                    $item[$xpos+1] = array();
                    $item[$xpos+1][$ypos] = true;
                    $item[$xpos+2] = array();
                    $item[$xpos+2][$ypos] = true;
                    $item[$xpos+3] = array();
                    $item[$xpos+3][$ypos+1] = true;
                    $item[$xpos+3][$ypos+3] = true;
                    $item[$xpos+4] = array();
                    $item[$xpos+4][$ypos] = true;
                    $item[$xpos+4][$ypos+1] = true;
                    $item[$xpos+4][$ypos+2] = true;
                    break;
                case 'N':
                default:
                    /* position like this:
                     * [*][ ][ ][*][ ]
                     * [ ][ ][ ][ ][*]
                     * [*][ ][ ][ ][*]
                     * [ ][*][*][*][*]
                     */
                    $item[$xpos] = array();
                    $item[$xpos][$ypos] = true;
                    $item[$xpos][$ypos+3] = true;
                    $item[$xpos+1] = array();
                    $item[$xpos+1][$ypos+4] = true;
                    $item[$xpos+2] = array();
                    $item[$xpos+2][$ypos] = true;
                    $item[$xpos+2][$ypos+4] = true;
                    $item[$xpos+3] = array();
                    $item[$xpos+3][$ypos+1] = true;
                    $item[$xpos+3][$ypos+2] = true;
                    $item[$xpos+3][$ypos+3] = true;
                    $item[$xpos+3][$ypos+4] = true;
                    break;
            }
            break;
        case 'pulsar':
            /* simple (no rotation) - position like this:
             * [ ][ ][*][*][*][ ][ ][ ][*][*][*][ ][ ]
             * [ ][ ][ ][ ][ ][ ][ ][ ][ ][ ][ ][ ][ ]
             * [*][ ][ ][ ][ ][*][ ][*][ ][ ][ ][ ][*]
             * [*][ ][ ][ ][ ][*][ ][*][ ][ ][ ][ ][*]
             * [*][ ][ ][ ][ ][*][ ][*][ ][ ][ ][ ][*]
             * [ ][ ][*][*][*][ ][ ][ ][*][*][*][ ][ ]
             * [ ][ ][ ][ ][ ][ ][ ][ ][ ][ ][ ][ ][ ]
             * [ ][ ][*][*][*][ ][ ][ ][*][*][*][ ][ ]
             * [*][ ][ ][ ][ ][*][ ][*][ ][ ][ ][ ][*]
             * [*][ ][ ][ ][ ][*][ ][*][ ][ ][ ][ ][*]
             * [*][ ][ ][ ][ ][*][ ][*][ ][ ][ ][ ][*]
             * [ ][ ][ ][ ][ ][ ][ ][ ][ ][ ][ ][ ][ ]
             * [ ][ ][*][*][*][ ][ ][ ][*][*][*][ ][ ]
             */
            $item[$xpos] = array();
            $item[$xpos][$ypos+2] = true;
            $item[$xpos][$ypos+3] = true;
            $item[$xpos][$ypos+4] = true;
            $item[$xpos][$ypos+8] = true;
            $item[$xpos][$ypos+9] = true;
            $item[$xpos][$ypos+10] = true;
            $item[$xpos+2] = array();
            $item[$xpos+2][$ypos] = true;
            $item[$xpos+2][$ypos+5] = true;
            $item[$xpos+2][$ypos+6] = true;
            $item[$xpos+2][$ypos+12] = true;
            $item[$xpos+3] = array();
            $item[$xpos+3][$ypos] = true;
            $item[$xpos+3][$ypos+5] = true;
            $item[$xpos+3][$ypos+6] = true;
            $item[$xpos+3][$ypos+12] = true;
            $item[$xpos+4] = array();
            $item[$xpos+4][$ypos] = true;
            $item[$xpos+4][$ypos+5] = true;
            $item[$xpos+4][$ypos+6] = true;
            $item[$xpos+4][$ypos+12] = true;
            $item[$xpos+5] = array();
            $item[$xpos+5][$ypos+2] = true;
            $item[$xpos+5][$ypos+3] = true;
            $item[$xpos+5][$ypos+4] = true;
            $item[$xpos+5][$ypos+8] = true;
            $item[$xpos+5][$ypos+9] = true;
            $item[$xpos+5][$ypos+10] = true;
            $item[$xpos+7] = array();
            $item[$xpos+7][$ypos+2] = true;
            $item[$xpos+7][$ypos+3] = true;
            $item[$xpos+7][$ypos+4] = true;
            $item[$xpos+7][$ypos+8] = true;
            $item[$xpos+7][$ypos+9] = true;
            $item[$xpos+7][$ypos+10] = true;
            $item[$xpos+8] = array();
            $item[$xpos+8][$ypos] = true;
            $item[$xpos+8][$ypos+5] = true;
            $item[$xpos+8][$ypos+6] = true;
            $item[$xpos+8][$ypos+12] = true;
            $item[$xpos+9] = array();
            $item[$xpos+9][$ypos] = true;
            $item[$xpos+9][$ypos+5] = true;
            $item[$xpos+9][$ypos+6] = true;
            $item[$xpos+9][$ypos+12] = true;
            $item[$xpos+10] = array();
            $item[$xpos+10][$ypos] = true;
            $item[$xpos+10][$ypos+5] = true;
            $item[$xpos+10][$ypos+6] = true;
            $item[$xpos+10][$ypos+12] = true;
            $item[$xpos+12] = array();
            $item[$xpos+12][$ypos+2] = true;
            $item[$xpos+12][$ypos+3] = true;
            $item[$xpos+12][$ypos+4] = true;
            $item[$xpos+12][$ypos+8] = true;
            $item[$xpos+12][$ypos+9] = true;
            $item[$xpos+12][$ypos+10] = true;
            break;
    }
    
    // cleanup - ensure that nothing is out of bounds
    foreach ($item as $x=>$r) {
        // is $x out of bounds?
        if ($x < 0 || $x >= 200) {
            // dump the row
            unset($item[$x]);
        } else {
            foreach ($r as $y=>$dummy) {
                // is $y out of bounds?
                if ($y < 0 || $y >= 200) {
                    // dump the row
                    unset($item[$x][$y]);
                }
            }
        }
    }
    
    // and give back the nice, clean item
    return $item;
}

/** Turns a 2D map into a 1D array of coordinates
 * @param array $map The 2D map
 * @param bool $liveonly Only return the 'live' items
 */
function map_to_array($map,$liveonly=false) {
    $flat = array();
    
    // go through the map
    foreach ($map as $x=>$r) {
        // is $x out of bounds?
        foreach ($r as $y=>$val) {
            // if live only, cherry-pick the live ones
            if ($liveonly) {
                if ($val) {
                    $flat[] = array('x'=>$x,'y'=>$y,'alive'=>true);
                }
            } else {
                // if not live only, take everything
                $flat[] = array('x'=>$x,'y'=>$y,'alive'=>$val);
            }
        }
    }
    
    return $flat;
}
?>