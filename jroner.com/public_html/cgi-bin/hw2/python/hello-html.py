#!/usr/bin/env python3

import os
from datetime import datetime

print("Cache-Control: no-cache")
print("Content-Type: text/html")
print()

print("<!DOCTYPE html>")
print("<html>")
print("<head>")
print("<title>Hello CGI World - Jacob Roner</title>")
print("</head>")
print("<body>")

print("<h1 align=center>Hello HTML World - Jacob Roner</h1><hr/>")
print("<p>Hello World, from Jacob Roner</p>")
print("<p>This page was generated with the Python programming language from jroner.com</p>")

date = datetime.now().strftime("%Y-%m-%d %H:%M:%S %Z")
print(f"<p>This program was generated at: {date}</p>")

# IP Address is an environment variable when using CGI
address = os.environ.get("REMOTE_ADDR", "unknown")
print(f"<p>Your current IP Address is: {address}</p>")
print("</body>")
print("</html>")
