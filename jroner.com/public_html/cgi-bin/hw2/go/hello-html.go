package main

import (
	"fmt"
	"net/http"
	"net/http/cgi"
	"time"
	"html"
)

func handler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Content-Type", "text/html; charset=utf-8")

	ip := r.RemoteAddr
	// If behind proxy and you want the real IP, you can also read X-Forwarded-For.
	now := time.Now().Format("2006-01-02 15:04:05")

	fmt.Fprint(w, "<!DOCTYPE html><html><head><meta charset='utf-8'>")
	fmt.Fprint(w, "<title>Hello CGI World - Jacob Roner</title></head><body>")
	fmt.Fprint(w, "<h1 align='center'>Hello HTML World - Jacob Roner</h1><hr/>")
	fmt.Fprint(w, "<p>Hello World, from Jacob Roner</p>")
	fmt.Fprint(w, "<p>This page was generated with the Go programming language from jroner.com</p>")
	fmt.Fprintf(w, "<p>This program was generated at: %s</p>", html.EscapeString(now))
	fmt.Fprintf(w, "<p>Your current IP Address is: %s</p>", html.EscapeString(ip))
	fmt.Fprint(w, "</body></html>")
}

func main() { cgi.Serve(http.HandlerFunc(handler)) }
