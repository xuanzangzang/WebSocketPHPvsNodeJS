
var WebSocketServer = require('ws').Server  
, wss = new WebSocketServer({host:'localhost',port:9998}); 

wss.on('connection', function(ws) {
	console.log('running......');
	ws.on('message', function(message) {  
		console.log('received:%s', message);  
	});
	var startTime = Date.now();
	for (var i=0;i<100000;i++) {
		ws.send("test"+i);
	}
	var endTime = Date.now();
	console.log('Send One hundred thousand Socket Time:'+((endTime-startTime)/1000)+"s\n");
}); 

	