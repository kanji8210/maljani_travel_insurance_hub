Maljani WebSocket Server

This is a minimal Node.js WebSocket + HTTP broadcast server used by the Maljani plugin for real-time admin updates.

Install:

```bash
cd scripts/ws-server
npm install
```

Run:

```bash
npm start
```

Default listening port: `8080`.

Endpoints:
- `POST /broadcast` — accepts JSON payload and broadcasts to all connected WebSocket clients.
- WebSocket endpoint: `ws://<host>:8080/` — clients connect with WebSocket and receive broadcasted JSON messages.

Usage from PHP plugin:
- Configure option `maljani_ws_server_http` (default http://127.0.0.1:8080/broadcast) if server is on a different host/port.
- Plugin will POST JSON payloads describing new messages; clients will receive payloads in real time.
