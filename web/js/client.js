function $(id) {
  return document.getElementById(id);
}

var logging = true;

function log(text) {
  
    $('log').value += text + '\n';
    console.log(text);
  
}

function dlog(text) {
  if (logging) {
    $('log').value += text + '\n';
    console.log(text);
  }
}

var port = 80;
var interval;

document.addEventListener('DOMContentLoaded', function() {
  log('This is a test of an HTTP and WebSocket server. This application is ' +
      'sending a message, a repsonse from other client is expected.');
  // var loggerArea = document.getElementById('logging');
  // loggerArea.checked = logging;
  // loggerArea.addEventListener("change", function(e){
  //   logging = e.target.checked;
  // })
  var pingElement = document.getElementById('ping');
  pingElement.checked = true;
  pingElement.addEventListener("change", function(){
    clearInterval(interval);
  })
});


function connectClient(prevWS) {
  if (prevWS) { prevWS.close() }
  var address = 'ws://' + window.location.hostname + ':' + port + '/';
  console.log('Logger connecting');
  var ws = new WebSocket(address, 'logger');
  ws.addEventListener('open', function() {
    $('input').disabled = false;
    console.log('Connected');
    interval = setInterval(function () {
      doSendMessage(ws, {value: '{"type": "ping","destination": "all"}'});
      dlog("Sending ping");
    }, 3000);
  });
  ws.addEventListener('close', function() {
    log('Connection lost');
    $('input').disabled = true;
  });
  ws.addEventListener('message', function(e) {
    if (e.data.length > 100) {
      dlog("received long message");
    } else {
      dlog(e.data);
    }
  });
  $('input').addEventListener('keydown', function(e) {
    if (e.keyCode === 13) {
      doSendMessage(ws, this);
    }
  });
  $('return').addEventListener('click', function () {
    var element = $('input');
    doSendMessage(ws, element);
  })
  $('home').addEventListener('click', function () {
    start(ws);
  })
}

function handleConsoleCommand(message, ws) {
  if (message.startsWith('/')) {
    log("Command: "+message);
    var space = message.indexOf(" ");
    if (space < 0) {
      var command = message.slice(1);
      var data = null;
    } else {
      var command = message.slice(1,space);
      var data = message.slice(space + 1);
    }
    if (typeof window[command] == 'function') {
      window[command]({data: data});
      return true;
    }
  }

  return false;
}

function start(ws) {
  doSendMessage(ws, {value: '{"type": "requestSrc","src": "/"}'});
}


function doSendMessage(ws, element) {
  var command = handleConsoleCommand(element.value, ws);
  if (!command && ws && ws.readyState === 1) {
    ws.send(element.value);
  }
  element.value = '';
}

// FIXME: Wait for 1s so that HTTP Server socket is listening...
setTimeout(function() { connectClient(); }, 1e3);