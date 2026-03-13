package main

import (
	"fmt"
	"net/http"
	"net/http/cgi"
	"html"
)

func handler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Content-Type", "text/html; charset=utf-8")

	sess := ""
	if c, err := r.Cookie("CGISESSID"); err == nil {
		sess = c.Value
	}

	fmt.Fprint(w, "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Go CGI Form</title></head><body>")
	fmt.Fprint(w, "<h1>Go CGI Form</h1>")
	if sess != "" {
		fmt.Fprintf(w, "<p><b>Current Session ID:</b> %s</p>", html.EscapeString(sess))
	} else {
		fmt.Fprint(w, "<p><b>Current Session ID:</b> (none yet)</p>")
	}

	fmt.Fprint(w, `
	<p>Enter a username and submit it to the Go session page.</p>
	<form action="/cgi-bin/hw2/go/state" method="get">
	  <label for="username">Username:</label>
	  <input id="username" name="username" type="text" />
	  <button type="submit">Save to Session</button>
	</form>
	<hr/>
	<ul>
	  <li><a href="/cgi-bin/hw2/go/state">Go Session</a></li>
	  <li><a href="/cgi-bin/hw2/go/state-clear">Destroy Session</a></li>
	</ul>
	</body></html>`)
}

func main() { cgi.Serve(http.HandlerFunc(handler)) }
