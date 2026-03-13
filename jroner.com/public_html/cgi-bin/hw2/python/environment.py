#!/usr/bin/env python3
import os

print("Cache-Control: no-cache")
print("Content-type: text/html")
print()

# print HTML file top

print("<!DOCTYPE html>")
print("<html><head><title>Environment Variables</title>")
print("</head><body><h1 align=\"center\">Environment Variables</h1>")
print("<hr>")

# Loop over the environment variables and print each variable and its value
for variable, value in sorted(os.environ.items()):
  print(f"<b>{variable}:</b> {value}<br />")

# Print the HTML file bottom
print("</body></html>")