// set up
var gol_wrapper;
var hidden_div;
var gol_table;
var life_left = 50;
var sessionkey;

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
                table += '<td id="cell_'+i+'_'+j+'"></td>';
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

$(document).ready(function () {
    init();
});