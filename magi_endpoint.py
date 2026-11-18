#!/usr/bin/env python3
"""MAGI status endpoint: serves the latest status JSON and accepts pushes from the collector."""
import http.server, os, sys

TOKEN = "__TOKEN__"
STORE = "/var/lib/magi-www/status.json"
PORT = 80

class Handler(http.server.BaseHTTPRequestHandler):
    def log_message(self, fmt, *args):
        sys.stderr.write("%s - %s\n" % (self.address_string(), fmt % args))

    def do_GET(self):
        if self.path == "/status-%s.json" % TOKEN:
            try:
                with open(STORE, "rb") as f:
                    body = f.read()
                self.send_response(200)
                self.send_header("Content-Type", "application/json")
                self.send_header("Content-Length", str(len(body)))
                self.send_header("Cache-Control", "no-store")
                self.end_headers()
                self.wfile.write(body)
            except FileNotFoundError:
                self.send_error(404, "no data yet")
        else:
            self.send_error(404, "not found")

    def do_POST(self):
        if self.path == "/push-%s" % TOKEN:
            length = int(self.headers.get("Content-Length", "0"))
            if length <= 0 or length > 65536:
                self.send_error(400, "bad length")
                return
            body = self.rfile.read(length)
            os.makedirs(os.path.dirname(STORE), exist_ok=True)
            tmp = STORE + ".tmp"
            with open(tmp, "wb") as f:
                f.write(body)
            os.replace(tmp, STORE)
            self.send_response(200)
            self.send_header("Content-Length", "2")
            self.end_headers()
            self.wfile.write(b"ok")
        else:
            self.send_error(404, "not found")

if __name__ == "__main__":
    http.server.ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()
