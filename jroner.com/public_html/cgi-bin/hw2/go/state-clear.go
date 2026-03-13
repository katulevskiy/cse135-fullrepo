package main

import (
	"fmt"
	"net/http"
	"net/http/cgi"
	"os"
	"path/filepath"
)

const sessionDir = "/tmp/hw2_sessions"

func handler(w http.ResponseWriter, r *http.Request) {
	// If cookie exists, delete session file
	if c, err := r.Cookie("CGISESSID"); err == nil {
		path := filepath.Join(sessionDir, c.Value+".json")
		_ = os.Remove(path)
	}

	// Clear cookie
	http.SetCookie(w, &http.Cookie{
		Name:   "CGISESSID",
		Value:  "deleted",
		Path:   "/",
		MaxAge: -1,
	})

	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Content-Type", "text/html; charset=utf-8")

	fmt.Fprint(w, "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Session Destroyed</title></head><body>")
	fmt.Fprint(w, "<h1>Session Destroyed</h1>")
	fmt.Fprint(w, `<a href="/cgi-bin/hw2/go/state-set">Start New Session</a>`)
	fmt.Fprint(w, "</body></html>")
}

func main() { cgi.Serve(http.HandlerFunc(handler)) }
