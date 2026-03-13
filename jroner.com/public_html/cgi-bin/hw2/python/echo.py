#!/usr/bin/env python3
import os
import sys
import json
from urllib.parse import parse_qs
from datetime import datetime
import socket

print("Cache-Control: no-cache")
print("Content-Type: application/json; charset=utf-8")
print()

method = os.environ.get("REQUEST_METHOD", "")
content_type = os.environ.get("CONTENT_TYPE", "")
query_string = os.environ.get("QUERY_STRING", "")
user_agent = os.environ.get("HTTP_USER_AGENT", "")
client_ip = os.environ.get("REMOTE_ADDR", "")
hostname = socket.gethostname()
timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

data = {}

# Handle GET parameters
if method == "GET":
    data = {k: v for k, v in parse_qs(query_string).items()}

# Handle POST/PUT/DELETE body
else:
    body = sys.stdin.read()
    if "application/json" in content_type:
        try:
            data = json.loads(body)
        except:
            data = {"raw_body": body}
    else:
        data = {k: v for k, v in parse_qs(body).items()}

response = {
    "method": method,
    "content_type": content_type,
    "data_received": data,
    "server_hostname": hostname,
    "server_time": timestamp,
    "user_agent": user_agent,
    "client_ip": client_ip
}

print(json.dumps(response, indent=2))
