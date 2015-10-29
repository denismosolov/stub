<?php

// The idea comes from http://php.net/manual/en/function.stream-socket-server.php
$socket = stream_socket_server('tcp://0.0.0.0:8000', $errno, $errstr);
if ($socket) {
  while ($conn = stream_socket_accept($socket)) {
    fwrite($conn,     "HTTP/1.1 200 OK \r\n"
                    . "Content-Type: text/html; charset=utf-8r\n"
                    . "\r\n"
                    . "<h1>Stub Web Server</h1><p>Happy learning!</p>");
    fclose($conn);
  }
  fclose($socket);
} else {
  echo "$errstr ($errno)";
}