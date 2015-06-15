var SseChannel = require( 'sse-channel' );
var http       = require( 'http' );
var os         = require( 'os' );
var stompit    = require( 'stompit' );
var url        = require( 'url' );
var qs         = require( 'querystring' );
var _          = require( 'lodash' );
var winston    = require( 'winston' );

winston.add( winston.transports.File, { filename: 'server.log' } );
winston.remove( winston.transports.Console );
winston.level = 'debug';

var queueName = '/queue/matecat.sse';

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

var subscribeHeaders = {
    'destination': queueName,
    'ack': 'client-individual'
  };

var browserChannel = new SseChannel({
    retryTimeout: 250,
    historySize: 300, // XXX
    pingInterval: 15000,
    jsonEncode: true,
    cors: {
        origins: ['*'] // Defaults to []
    }
});

var generateUid = function (separator) {
    var delim = separator || "";

    function S4() {
        return (((1 + Math.random()) * 0x10000) | 0).toString(16).substring(1);
    }

    return (S4() + S4() + delim + S4() );
};

var browserLoopIntervalTime = 2000;

browserChannel.on('message', function(message) {
    // TODO: a message was sent to clients, nothing interesting to do here.
    console.log('browserChannel message', message);
});

browserChannel.on('disconnect', function(context, res) {
    // TODO: remove client from the jobid:
    console.log('browserChannel disconnect', res._clientId);
});

browserChannel.on('connect', function(context, req, res) {
    // TODO: a new client connected, update the jobids index
    console.log('browserChannel connect connectionId', res._clientId);
    console.log('browserChannel connect jobId', res._matecatJobId);

    browserChannel.send({
        data : {
            _type : 'ack',
            clientId : res._clientId
        }
    }, [ res ]);
});

http.createServer(function(req, res) {
  // find job id from requested path
  var parsedUrl = url.parse( req.url ) ;
  var path = parsedUrl.path  ;

  if (path.indexOf('/channel/comments') === 0 ) {
    var query = qs.parse( parsedUrl.query ) ;

    res._clientId = generateUid();
    res._matecatJobId = query.jid ;
    res._matecatPw = query.pw ;

    browserChannel.addClient(req, res);
  } else {
    res.writeHead(404);
    res.end();
  }

}).listen(7788, '0.0.0.0', function() {
  console.log('Listening on http://127.0.0.1:7788/');
});

var stompMessageReceived = function(body) {
  var message = JSON.parse(body);

  var dest = _.filter(browserChannel.connections, function(ele) {
    return ele._matecatJobId == message.data.id_job && ele._matecatPw == message.data.password;
  });

  console.log('browser channel connections', dest.length);

  browserChannel.send({ data: message.data.payload }, dest);

  // find channel, SSE can serve multiple purposes
  // find subs by job_id, and skip those where password does not match
  console.log('stompMessageReceived id', message.data.id_job);
}

var startStompConnection = function()   {
  stompit.connect(connectOptions, function(error, client) {
    client.subscribe(subscribeHeaders, function(error, message) {
      console.log('** event received in client subscription');

      if (error) {
        console.log('!! subscribe error ' + error.message);

        client.disconnect();
        startStompConnection();

        return;
      }

      message.readString('utf-8', function(error, body) {

        if (error) {
          console.log('!! read message error ' + error.message);
          return;
        }
        else {
          console.log('message received');
          stompMessageReceived(body);
          message.ack();
        }
      });
    });
  });
}

startStompConnection();
