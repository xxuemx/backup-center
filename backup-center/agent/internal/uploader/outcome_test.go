package uploader

import (
	"github.com/gongpz/backup-center/agent/internal/client"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestUploadOutcome(t *testing.T) {
	for _, tc := range []struct {
		name    string
		exit    string
		ack     int
		targets []int64
		wantErr bool
	}{
		{"success", "0", 200, []int64{1}, false},
		{"rclone failure", "1", 200, []int64{1}, true},
		{"acknowledgement failure", "0", 500, []int64{1}, true},
		{"missing target", "0", 200, []int64{9}, true},
		{"no target", "0", 200, nil, true},
		{"partial failure", "0", 200, []int64{1, 9}, true},
	} {
		t.Run(tc.name, func(t *testing.T) {
			srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { w.WriteHeader(tc.ack); w.Write([]byte(`{"ok":true}`)) }))
			defer srv.Close()
			dir := t.TempDir()
			if err := os.WriteFile(filepath.Join(dir, "rclone"), []byte("#!/bin/sh\nexit "+tc.exit+"\n"), 0700); err != nil {
				t.Fatal(err)
			}
			t.Setenv("PATH", dir+":"+os.Getenv("PATH"))
			u := New(client.New(srv.URL, "test-only", "test"), filepath.Join(dir, "rclone.conf"))
			u.storages[1] = client.Storage{ID: 1, Name: "test", Type: "sftp"}
			err := u.ProcessBackup(1, filepath.Join(dir, "sample"), "test", time.Now(), tc.targets)
			if (err != nil) != tc.wantErr {
				t.Fatalf("error=%v wantErr=%v", err, tc.wantErr)
			}
		})
	}
}
