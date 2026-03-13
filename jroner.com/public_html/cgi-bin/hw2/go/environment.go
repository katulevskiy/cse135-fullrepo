package main

import (
	"fmt"
	"html"
	"net/http"
	"net/http/cgi"
	"os"
	"sort"
)

func handler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Content-Type", "text/html; charset=utf-8")

	fmt.Fprint(w, "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Go Environment</title></head><body>")
	fmt.Fprint(w, "<h1>Go Environment</h1>")

	fmt.Fprint(w, "<h2>Request</h2><ul>")
	fmt.Fprintf(w, "<li><b>Method:</b> %s</li>", html.EscapeString(r.Method))
	fmt.Fprintf(w, "<li><b>URI:</b> %s</li>", html.EscapeString(r.RequestURI))
	fmt.Fprintf(w, "<li><b>RemoteAddr:</b> %s</li>", html.EscapeString(r.RemoteAddr))
	fmt.Fprint(w, "</ul>")

	fmt.Fprint(w, "<h2>Headers</h2><pre>")
	// stable order
	var keys []string
	for k := range r.Header { keys = append(keys, k) }
	sort.Strings(keys)
	for _, k := range keys {
		for _, v := range r.Header[k] {
			fmt.Fprintf(w, "%s: %s\n", html.EscapeString(k), html.EscapeString(v))
		}
	}
	fmt.Fprint(w, "</pre>")

	fmt.Fprint(w, "<h2>Environment Variables</h2><pre>")
	env := os.Environ()
	sort.Strings(env)
	for _, kv := range env {
		fmt.Fprintln(w, html.EscapeString(kv))
	}
	fmt.Fprint(w, "</pre></body></html>")
}

func main() { cgi.Serve(http.HandlerFunc(handler)) }
