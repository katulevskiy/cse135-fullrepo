#!/usr/bin/env python3
import json
import datetime
import os

print("Cache-Control: no-cache")
print("Content-type: application/json")
print()

date = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
address = os.environ.get('REMOTE_ADDR', '')

my_message = {'title': 'Hello, Python! From Jacob Roner', 'heading': 'Hello, Python! From Jacob Roner', 'message': 'This page was generated with the Python programming language from jroner.com', 'time': date, 'IP': address}

my_json = json.dumps(my_message)
print(my_json)