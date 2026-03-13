package main

import (
	"encoding/json"
	"io"
	"net/http"
	"net/http/cgi"
	"net/url"
	"os"
	"time"
)

func handler(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	method := r.Method
	contentType := r.Header.Get("Content-Type")
	userAgent := r.Header.Get("User-Agent")
	ip := r.RemoteAddr
	host, _ := os.Hostname()
	now := time.Now().Format("2006-01-02 15:04:05")

	var data any = map[string]any{}
	rawBody := ""

	if method == "GET" {
		// Echo query params
		m := map[string]any{}
		for k, v := range r.URL.Query() {
			m[k] = v
		}
		data = m
	} else {
		// Read entire body
		b, _ := io.ReadAll(r.Body)
		rawBody = string(b)

		if contentType != "" && (contains(contentType, "application/json")) {
			var obj any
			if err := json.Unmarshal(b, &obj); err == nil {
				data = obj
			} else {
				data = map[string]any{"raw_body": rawBody}
			}
		} else {
			// urlencoded (or unknown): parse as query
			parsed, err := url.ParseQuery(rawBody)
			if err != nil {
				data = map[string]any{"raw_body": rawBody}
			} else {
				m := map[string]any{}
				for k, v := range parsed {
					m[k] = v
				}
				// Also include r.Form if ParseForm handled it
				_ = r.ParseForm()
				for k, v := range r.Form {
					m[k] = v
				}
				data = m
			}
		}
	}

	resp := map[string]any{
		"method":          method,
		"content_type":    contentType,
		"data_received":   data,
		"raw_body":        rawBody,
		"server_hostname": host,
		"server_time":     now,
		"user_agent":      userAgent,
		"client_ip":       ip,
	}

	enc := json.NewEncoder(w)
	enc.SetIndent("", "  ")
	_ = enc.Encode(resp)
}

// tiny helper (no strings package needed if you want minimal imports)
func contains(s, sub string) bool {
	return len(sub) == 0 || (len(s) >= len(sub) && (indexOf(s, sub) >= 0))
}
func indexOf(s, sub string) int {
	// simple search
	for i := 0; i+len(sub) <= len(s); i++ {
		if s[i:i+len(sub)] == sub {
			return i
		}
	}
	return -1
}

func main() { cgi.Serve(http.HandlerFunc(handler)) }
