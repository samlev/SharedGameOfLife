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
                    $('#cell_'+x+'_'+(y+2)).addClass('possible');
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $('#cell_'+(x+1)+'_'+(y+2)).addClass('possible');
                    $('#cell_'+(x+2)+'_'+(y+2)).addClass('possible');
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
                    $('#cell_'+x+'_'+(y+2)).addClass('possible');
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $('#cell_'+(x+2)+'_'+(y+1)).addClass('possible');
                    break;
                case 'E':
                    /* position like this (90 degree rotation off N):
                     * [*][ ][ ]
                     * [*][ ][*]
                     * [*][*][ ]
                     */
                    $('#cell_'+x+'_'+y).addClass('possible');
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $('#cell_'+(x+1)+'_'+(y+2)).addClass('possible');
                    $('#cell_'+(x+2)+'_'+y).addClass('possible');
                    $('#cell_'+(x+2)+'_'+(y+1)).addClass('possible');
                    break;
                case 'N':
                default:
                    /* position like this:
                     * [ ][*][ ]
                     * [ ][ ][*]
                     * [*][*][*]
                     */
                    $('#cell_'+x+'_'+(y+1)).addClass('possible');
                    $('#cell_'+(x+1)+'_'+(y+2)).addClass('possible');
                    $('#cell_'+(x+2)+'_'+y).addClass('possible');
                    $('#cell_'+(x+2)+'_'+(y+1)).addClass('possible');
                    $('#cell_'+(x+2)+'_'+(y+2)).addClass('possible');
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
                    $('#cell_'+x+'_'+(y+2)).addClass('possible');
                    $('#cell_'+x+'_'+(y+3)).addClass('possible');
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $('#cell_'+(x+1)+'_'+(y+3)).addClass('possible');
                    $('#cell_'+(x+2)+'_'+(y+3)).addClass('possible');
                    $('#cell_'+(x+3)+'_'+(y+3)).addClass('possible');
                    $('#cell_'+(x+4)+'_'+y).addClass('possible');
                    $('#cell_'+(x+4)+'_'+(y+2)).addClass('possible');
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
                    $('#cell_'+x+'_'+(y+2)).addClass('possible');
                    $('#cell_'+x+'_'+(y+3)).addClass('possible');
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $('#cell_'+(x+1)+'_'+(y+4)).addClass('possible');
                    $('#cell_'+(x+2)+'_'+y).addClass('possible');
                    $('#cell_'+(x+3)+'_'+(y+1)).addClass('possible');
                    $('#cell_'+(x+3)+'_'+(y+4)).addClass('possible');
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
                    $('#cell_'+x+'_'+(y+3)).addClass('possible');
                    $('#cell_'+(x+1)+'_'+y).addClass('possible');
                    $('#cell_'+(x+2)+'_'+y).addClass('possible');
                    $('#cell_'+(x+3)+'_'+(y+1)).addClass('possible');
                    $('#cell_'+(x+3)+'_'+(y+3)).addClass('possible');
                    $('#cell_'+(x+4)+'_'+y).addClass('possible');
                    $('#cell_'+(x+4)+'_'+(y+1)).addClass('possible');
                    $('#cell_'+(x+4)+'_'+(y+2)).addClass('possible');
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
                    $('#cell_'+x+'_'+(y+3)).addClass('possible');
                    $('#cell_'+(x+1)+'_'+(y+4)).addClass('possible');
                    $('#cell_'+(x+2)+'_'+y).addClass('possible');
                    $('#cell_'+(x+2)+'_'+(y+4)).addClass('possible');
                    $('#cell_'+(x+3)+'_'+(y+1)).addClass('possible');
                    $('#cell_'+(x+3)+'_'+(y+2)).addClass('possible');
                    $('#cell_'+(x+3)+'_'+(y+3)).addClass('possible');
                    $('#cell_'+(x+3)+'_'+(y+4)).addClass('possible');
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
            $('#cell_'+x+'_'+(y+2)).addClass('possible');
            $('#cell_'+x+'_'+(y+3)).addClass('possible');
            $('#cell_'+x+'_'+(y+4)).addClass('possible');
            $('#cell_'+x+'_'+(y+8)).addClass('possible');
            $('#cell_'+x+'_'+(y+9)).addClass('possible');
            $('#cell_'+x+'_'+(y+10)).addClass('possible');
            $('#cell_'+(x+2)+'_'+y).addClass('possible');
            $('#cell_'+(x+2)+'_'+(y+5)).addClass('possible');
            $('#cell_'+(x+2)+'_'+(y+6)).addClass('possible');
            $('#cell_'+(x+2)+'_'+(y+12)).addClass('possible');
            $('#cell_'+(x+3)+'_'+y).addClass('possible');
            $('#cell_'+(x+3)+'_'+(y+5)).addClass('possible');
            $('#cell_'+(x+3)+'_'+(y+6)).addClass('possible');
            $('#cell_'+(x+3)+'_'+(y+12)).addClass('possible');
            $('#cell_'+(x+4)+'_'+y).addClass('possible');
            $('#cell_'+(x+4)+'_'+(y+5)).addClass('possible');
            $('#cell_'+(x+4)+'_'+(y+6)).addClass('possible');
            $('#cell_'+(x+4)+'_'+(y+12)).addClass('possible');
            $('#cell_'+(x+5)+'_'+(y+2)).addClass('possible');
            $('#cell_'+(x+5)+'_'+(y+3)).addClass('possible');
            $('#cell_'+(x+5)+'_'+(y+4)).addClass('possible');
            $('#cell_'+(x+5)+'_'+(y+8)).addClass('possible');
            $('#cell_'+(x+5)+'_'+(y+9)).addClass('possible');
            $('#cell_'+(x+5)+'_'+(y+10)).addClass('possible');
            $('#cell_'+(x+7)+'_'+(y+2)).addClass('possible');
            $('#cell_'+(x+7)+'_'+(y+3)).addClass('possible');
            $('#cell_'+(x+7)+'_'+(y+4)).addClass('possible');
            $('#cell_'+(x+7)+'_'+(y+8)).addClass('possible');
            $('#cell_'+(x+7)+'_'+(y+9)).addClass('possible');
            $('#cell_'+(x+7)+'_'+(y+10)).addClass('possible');
            $('#cell_'+(x+8)+'_'+y).addClass('possible');
            $('#cell_'+(x+8)+'_'+(y+5)).addClass('possible');
            $('#cell_'+(x+8)+'_'+(y+6)).addClass('possible');
            $('#cell_'+(x+8)+'_'+(y+12)).addClass('possible');
            $('#cell_'+(x+9)+'_'+y).addClass('possible');
            $('#cell_'+(x+9)+'_'+(y+5)).addClass('possible');
            $('#cell_'+(x+9)+'_'+(y+6)).addClass('possible');
            $('#cell_'+(x+9)+'_'+(y+12)).addClass('possible');
            $('#cell_'+(x+10)+'_'+y).addClass('possible');
            $('#cell_'+(x+10)+'_'+(y+5)).addClass('possible');
            $('#cell_'+(x+10)+'_'+(y+6)).addClass('possible');
            $('#cell_'+(x+10)+'_'+(y+12)).addClass('possible');
            $('#cell_'+(x+12)+'_'+(y+2)).addClass('possible');
            $('#cell_'+(x+12)+'_'+(y+3)).addClass('possible');
            $('#cell_'+(x+12)+'_'+(y+4)).addClass('possible');
            $('#cell_'+(x+12)+'_'+(y+8)).addClass('possible');
            $('#cell_'+(x+12)+'_'+(y+9)).addClass('possible');
            $('#cell_'+(x+12)+'_'+(y+10)).addClass('possible');
            break;
    }
}

$(document).ready(function () {
    init();
});