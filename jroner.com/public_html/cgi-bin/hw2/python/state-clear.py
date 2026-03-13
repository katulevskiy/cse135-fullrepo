#!/usr/bin/env python3
import os
from http import cookies

SESSION_DIR = "/tmp/hw2_sessions"

cookie_header = os.environ.get("HTTP_COOKIE", "")
cookie = cookies.SimpleCookie()
cookie.load(cookie_header)

if "CGISESSID" in cookie:
    session_id = cookie["CGISESSID"].value
    session_file = os.path.join(SESSION_DIR, session_id + ".json")
    if os.path.exists(session_file):
        os.remove(session_file)

print("Content-Type: text/html")
print("Set-Cookie: CGISESSID=deleted; Path=/; Max-Age=0")
print()
print("<html><body>")
print("<h1>Session Destroyed</h1>")
print('<a href="/cgi-bin/hw2/python/state-set.py">Start New Session</a>')
print("</body></html>")