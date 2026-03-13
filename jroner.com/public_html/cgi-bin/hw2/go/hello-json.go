package main

import (
	"encoding/json"
	"net/http"
	"net/http/cgi"
	"time"
)

func handler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	payload := map[string]any{
		"title":   "Hello, Go! From Jacob Roner",
		"heading": "Hello, Go! From Jacob Roner",
		"message": "This page was generated with the Go programming language from jroner.com",
		"time":    time.Now().Format("2006-01-02 15:04:05"),
		"ip":      r.RemoteAddr,
	}

	enc := json.NewEncoder(w)
	enc.SetIndent("", "  ")
	_ = enc.Encode(payload)
}

func main() { cgi.Serve(http.HandlerFunc(handler)) }
