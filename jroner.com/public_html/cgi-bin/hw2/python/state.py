#!/usr/bin/env python3
import os
import uuid
import json
from http import cookies
from urllib.parse import parse_qs
from datetime import datetime

SESSION_DIR = "/tmp/hw2_sessions"
os.makedirs(SESSION_DIR, exist_ok=True)

# parse cookies
cookie_header = os.environ.get("HTTP_COOKIE", "")
cookie = cookies.SimpleCookie()
cookie.load(cookie_header)

if "CGISESSID" in cookie:
    session_id = cookie["CGISESSID"].value
else:
    session_id = str(uuid.uuid4())

session_file = os.path.join(SESSION_DIR, session_id + ".json")

# load existing session
if os.path.exists(session_file):
    with open(session_file, "r") as f:
        session_data = json.load(f)
else:
    session_data = {}

# get form input
method = os.environ.get("REQUEST_METHOD", "GET")
if method == "GET":
    params = parse_qs(os.environ.get("QUERY_STRING", ""))
else:
    length = int(os.environ.get("CONTENT_LENGTH", 0))
    body = os.environ.get("wsgi.input", "").read(length)
    params = parse_qs(body)

username = params.get("username", [session_data.get("username")])[0]

if username:
    session_data["username"] = username

# save session
with open(session_file, "w") as f:
    json.dump(session_data, f)

# output headers
print("Content-Type: text/html")
print(f"Set-Cookie: CGISESSID={session_id}; Path=/")
print()

# output page ---
print("<html><head><title>Python Sessions</title></head><body>")
print("<h1>Python Sessions Page</h1>")

if username:
    print(f"<p><b>Name:</b> {username}</p>")
else:
    print("<p><b>Name:</b> You do not have a name set</p>")

print('<br/><br/>')
print('<a href="./state-set.py">Python CGI Form</a><br/>')
print('<form style="margin-top:30px" action="/cgi-bin/hw2/python/state-clear.py" method="get">')
print('<button type="submit">Destroy Session</button>')
print('</form>')
print("</body></html>")
