// set up
var gol_wrapper;
var hidden_div;
var gol_table;
var life_left = 50;
var sessionkey;
var selectedtype = "none";
var orientation = 'N';

function init() {
    if (gol_wrapper == undefined) {
        // get the game of life wrapper
        gol_wrapper = $('#gameoflife');
        gol_wrapper.html('Please wait... Initializing grid...');
        
        hidden_div = $('#hidden');
        
        var table = '<table>';
        var i;
        var j;
        
        // add the cells
        for (i = 0; i < 200; i++) {
            table += '<tr>';
            for (j = 0; j < 200; j++) {
                table += '<td id="cell_'+i+'_'+j+'" x="'+i+'" y="'+j+'"></td>';
            }
            table += '</tr>';
        }
        table += '</table>';
        
        // add to the hidden div
        hidden_div.html(table);
        // and get a jquery reference
        gol_table = $('#hidden > table');
        
        // update the user
        gol_wrapper.html(gol_wrapper.html()+'<br />Done!<br /><br />Registering...');
        
        // get user registration
        $.getJSON('register.php', function (data) {
            if (data.success) {
                // set the session key
                sessionkey = data.session.key
                // set the 'life left' value
                life_left = data.session.liferemaining;
                $('#amountleft').html(life_left);
                
                // and get set up to re-register after the session expires
                setTimeout('reregister',(data.session.expires*1000)+500);
                
                gol_wrapper.html(gol_wrapper.html()+'<br />Done!<br /><br />Starting game...<br />');
                
                $.getJSON('getnext.php', function (data) {
                    if (data.success && data.generation) {
                        changes = data.generation.change;
                        
                        var i;
                        for (i = 0; i < changes.length; i++) {
                            if (changes[i].alive) {
                                $('#cell_'+changes[i].x+'_'+changes[i].y).addClass('alive');
                            }
                        }
                        
                        // now place the grid
                        gol_wrapper.html(gol_table);
                        hidden_div.html('');
                        
                        // add a listener to the cells
                        $('[id^="cell_"]').mouseover(function() {
                            displaycurrent($(this).attr('x'),$(this).attr('y'));
                        });
                        $('#gameoflife').mouseout(function() {
                            $('.possible').removeClass('possible');
                        });
                        
                        $('[id^="add_"]').click(function() {
                            selecttype($(this).attr('add'));
                        });
                        $('[id^="show_"]').click(function() {
                            selecttype($(this).attr('add'));
                        });
                    }
                });
            } else {
                gol_wrapper.html(gol_wrapper.html()+'<br />Failed!<br /><br />Please try again.<br />');
            }
        });
    }
}

function reregister() {
    $.getJSON('register.php', function (data) {
        if (data.success) {
            // set the session key
            sessionkey = data.session.key
            
            // set the 'life left' value
            life_left = data.session.liferemaining;
            $('#amountleft').html(life_left);
            
            // and get set up to re-register after the session expires
            setTimeout('reregister',(data.session.expires*1000)+500);
        } else {
            // try again in half a second
            setTimeout('reregister',500);
        }
    });
}

function selecttype(type) {
    $('.selected').removeClass('selected');
    orientation = 'N';
    
    switch (type) {
        case "single":
            if (life_left >= 1) {
                $('add_single').addClass('selected');
                selectedtype = 'single';
            } else {
                selecttype('none');
            }
            break;
        case "block":
            if (life_left >= 4) {
                $('add_block').addClass('selected');
                selectedtype = 'block';
            } else {
                selecttype('none');
            }
            break;
        case "glider":
            if (life_left >= 5) {
                $('add_glider').addClass('selected');
                selectedtype = 'glider';
            } else {
                selecttype('none');
            }
            break;
        case "lwss":
            if (life_left >= 9) {
                $('add_lwss').addClass('selected');
                selectedtype = 'lwss';
            } else {
                selecttype('none');
            }
            break;
        case "pulsar":
            if (life_left >= 48) {
                $('add_pulsar').addClass('selected');
                selectedtype = 'pulsar';
            } else {
                selecttype('none');
            }
            break;
        case "none":
        default:
            selectedtype = 'none';
            $('add_none').addClass('selected');
            break;
    }
}

function displaycurrent(x,y) {
    // clear anything current
    $('.possible').removeClass('possible');
    
    switch (selectedtype) {
        case 'single':
            /* simple (no rotation) - position like this:
             * [*]
             */
            $('#cell_'+x+'_'+y).addClass('possible');
            break;
        case 'block':
            /* simple (no rotation) - position like this:
             * [*][*]
             * [*][*]
             */
            $('#cell_'+x+'_'+y).addClass('possible');
            $('#cell_'+x+'_'+(y+1)).addClass('possible');
            $('#cell_'+(x+1)+'_'+y).addClass('possible');
            $('#cell_'+(x+1)+'_'+(y+1)).addClass('possible');
            break;
        case 'glider':
            // check for rotation (N is default)
            switch (orientation) {
                case 'W':
                    /* position like this (270 degree rotation off N):
                     * [ ][*][*]
                     * [*][ ][*]
                     * [ ][ ][*]
                     */
                    $('#cell_'+x+'_'+(y+1)).addClass('possible');
                    $item[$xpos][$ypos+2] = true;
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $item[$xpos+1][$ypos+2] = true;
                    $item[$xpos+2][$ypos+2] = true;
                    break;
                    break;
                case 'S':
                    /* position like this (180 degree rotation off N):
                     * [*][*][*]
                     * [*][ ][ ]
                     * [ ][*][ ]
                     */
                    $('#cell_'+x+'_'+y).addClass('possible');
                    $('#cell_'+x+'_'+(y+1)).addClass('possible');
                    $item[$xpos][$ypos+2] = true;
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $item[$xpos+2][$ypos+1] = true;
                    break;
                case 'E':
                    /* position like this (90 degree rotation off N):
                     * [*][ ][ ]
                     * [*][ ][*]
                     * [*][*][ ]
                     */
                    $('#cell_'+x+'_'+y).addClass('possible');
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $item[$xpos+1][$ypos+2] = true;
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
                    $('#cell_'+x+'_'+(y+1)).addClass('possible');
                    $item[$xpos+1][$ypos+2] = true;
                    $item[$xpos+2][$ypos] = true;
                    $item[$xpos+2][$ypos+1] = true;
                    $item[$xpos+2][$ypos+2] = true;
                    break;
            }
            break;
        case 'lwss':
            // check for rotation (N is default)
            switch (orientation) {
                case 'W':
                    /* position like this (270 degree rotation off N):
                     * [ ][*][*][*]
                     * [*][ ][ ][*]
                     * [ ][ ][ ][*]
                     * [ ][ ][ ][*]
                     * [*][ ][*][ ]
                     */
                    $('#cell_'+x+'_'+(y+1)).addClass('possible');
                    $item[$xpos][$ypos+2] = true;
                    $item[$xpos][$ypos+3] = true;
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $item[$xpos+1][$ypos+3] = true;
                    $item[$xpos+2][$ypos+3] = true;
                    $item[$xpos+3][$ypos+3] = true;
                    $item[$xpos+4][$ypos] = true;
                    $item[$xpos+4][$ypos+2] = true;
                    break;
                    break;
                case 'S':
                    /* position like this (180 degree rotation off N):
                     * [*][*][*][*][ ]
                     * [*][ ][ ][ ][*]
                     * [*][ ][ ][ ][ ]
                     * [ ][*][ ][ ][*]
                     */
                    $('#cell_'+x+'_'+y).addClass('possible');
                    $('#cell_'+x+'_'+(y+1)).addClass('possible');
                    $item[$xpos][$ypos+2] = true;
                    $item[$xpos][$ypos+3] = true;
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $item[$xpos+1][$ypos+4] = true;
                    $item[$xpos+2][$ypos] = true;
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
                    $('#cell_'+x+'_'+(y+1)).addClass('possible');
                    $item[$xpos][$ypos+3] = true;
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $item[$xpos+2][$ypos] = true;
                    $item[$xpos+3][$ypos+1] = true;
                    $item[$xpos+3][$ypos+3] = true;
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
                    $('#cell_'+x+'_'+y).addClass('possible');
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
}

$(document).ready(function () {
    init();
});