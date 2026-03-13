#!/usr/bin/env python3
import os
import html
from http import cookies

cookie_header = os.environ.get("HTTP_COOKIE", "")
c = cookies.SimpleCookie()
c.load(cookie_header)
sess_id = c["CGISESSID"].value if "CGISESSID" in c else ""

print("Cache-Control: no-cache")
print("Content-Type: text/html; charset=utf-8")
print()

print("""<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Python CGI Form</title>
</head>
<body>
  <h1>Python CGI Form</h1>
""")

if sess_id:
    print(f"<p><b>Current Session ID:</b> {html.escape(sess_id)}</p>")
else:
    print("<p><b>Current Session ID:</b> (none yet)</p>")

print("""
  <p>Enter a username and submit it to the Python session page.</p>

  <form action="/cgi-bin/hw2/python/state.py" method="get">
    <label for="username">Username:</label>
    <input id="username" name="username" type="text" />
    <button type="submit">Save to Session</button>
  </form>

  <hr/>

  <p>Links:</p>
  <ul>
    <li><a href="/cgi-bin/hw2/python/state.py">Python Session</a></li>
    <li><a href="/cgi-bin/hw2/python/state-clear.py">Destroy Session</a></li>
  </ul>

</body>
</html>
""")
