var stompit = require('stompit');
var _ = require('lodash');

var connectOptions = {
    'host': 'localhost',
    'port': 61613,
    'connectHeaders':{
        'host': '/',
        'login': 'login',
        'passcode': 'passcode',
        'heart-beat': '5000,5000'
    }
};

stompit.connect(connectOptions, function(error, client) {

    if (error) {
        console.log('connect error ' + error.message);
        return;
    }

    var sendHeaders = {
        'destination': '/topic/matecat_sse_notifications',
        'content-type': 'text/plain'
    };

    var frame = client.send(sendHeaders);
    var response = {
        _type : 'comment',
        data : {
            id_job : '638',
            password : '3494ab77e7d7',
            payload: {
                _type: 'comment',
                id_segment : 377916,
                message : _.sample(['hello', 'hi', 'help', 'sorry'])
            }
        }
    } ;

    frame.write( JSON.stringify(response) );
    frame.end();
    client.disconnect();

});
