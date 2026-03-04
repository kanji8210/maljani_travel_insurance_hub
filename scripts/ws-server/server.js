const express = require('express');
const bodyParser = require('body-parser');
const WebSocket = require('ws');

const HTTP_PORT = process.env.PORT || 8080;
const WS_PORT = HTTP_PORT; // same server

const app = express();
app.use(bodyParser.json());

// Simple in-memory clients set
const wss = new WebSocket.Server({ noServer: true });
let clients = new Set();

wss.on('connection', function connection(ws) {
  clients.add(ws);
  console.log('Client connected. Total:', clients.size);

  ws.on('close', () => {
    clients.delete(ws);
    console.log('Client disconnected. Total:', clients.size);
  });
});

app.post('/broadcast', (req, res) => {
  const payload = req.body || {};
  const data = JSON.stringify(payload);
  clients.forEach(ws => {
    if (ws.readyState === WebSocket.OPEN) {
      try { ws.send(data); } catch (e) { }
    }
  });
  res.json({ success: true, delivered: clients.size });
});

const server = app.listen(HTTP_PORT, () => {
  console.log(`Maljani WS server listening on port ${HTTP_PORT}`);
});

server.on('upgrade', function upgrade(request, socket, head) {
  wss.handleUpgrade(request, socket, head, function done(ws) {
    wss.emit('connection', ws, request);
  });
});

process.on('SIGINT', () => { console.log('Shutting down'); process.exit(); });
