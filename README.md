# resize-swoole-server
A WebSocket server implementation with [Swoole](https://www.swoole.co.uk/)

Run the server with 
```sh
php main.php
```
it will listen on ```0.0.0.0:9999```

Opening multiple browser windows at http://localhost:9999/test.html you should see a ping message send from each client to others
