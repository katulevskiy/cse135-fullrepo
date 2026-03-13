package main

import (
	"crypto/rand"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"html"
	"net/http"
	"net/http/cgi"
	"os"
	"path/filepath"
)

const sessionDir = "/tmp/hw2_sessions"

type sessionData struct {
	Username string `json:"username"`
}

func newSessionID() string {
	b := make([]byte, 16)
	_, _ = rand.Read(b)
	return hex.EncodeToString(b)
}

func loadSession(path string) (sessionData, error) {
	var s sessionData
	f, err := os.Open(path)
	if err != nil {
		return s, err
	}
	defer f.Close()
	err = json.NewDecoder(f).Decode(&s)
	return s, err
}

func saveSession(path string, s sessionData) error {
	tmp := path + ".tmp"
	f, err := os.Create(tmp)
	if err != nil {
		return err
	}
	if err := json.NewEncoder(f).Encode(&s); err != nil {
		f.Close()
		_ = os.Remove(tmp)
		return err
	}
	if err := f.Close(); err != nil {
		return err
	}
	return os.Rename(tmp, path)
}

func handler(w http.ResponseWriter, r *http.Request) {
	_ = os.MkdirAll(sessionDir, 0700)

	// Session ID from cookie (or make a new one)
	sessID := ""
	if c, err := r.Cookie("CGISESSID"); err == nil {
		sessID = c.Value
	}
	if sessID == "" {
		sessID = newSessionID()
		http.SetCookie(w, &http.Cookie{
			Name:  "CGISESSID",
			Value: sessID,
			Path:  "/",
		})
	}

	sessionPath := filepath.Join(sessionDir, sessID+".json")

	// Load existing session
	sess := sessionData{}
	if _, err := os.Stat(sessionPath); err == nil {
		if loaded, err := loadSession(sessionPath); err == nil {
			sess = loaded
		}
	}

	// If username provided, store it
	username := r.URL.Query().Get("username")
	if username != "" {
		sess.Username = username
		_ = saveSession(sessionPath, sess)
	}

	w.Header().Set("Cache-Control", "no-cache")
	w.Header().Set("Content-Type", "text/html; charset=utf-8")

	fmt.Fprint(w, "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Go Sessions</title></head><body>")
	fmt.Fprint(w, "<h1>Go Sessions Page</h1>")

	if sess.Username != "" {
		fmt.Fprintf(w, "<p><b>Name:</b> %s</p>", html.EscapeString(sess.Username))
	} else {
		fmt.Fprint(w, "<p><b>Name:</b> You do not have a name set</p>")
	}

	fmt.Fprint(w, `<br/><br/>
	<a href="/cgi-bin/hw2/go/state-set">Go CGI Form</a><br/>
	<form style="margin-top:30px" action="/cgi-bin/hw2/go/state-clear" method="get">
	  <button type="submit">Destroy Session</button>
	</form>
	</body></html>`)
}

func main() { cgi.Serve(http.HandlerFunc(handler)) }
