# Changes

## v0.1.1

Security fix.

- `upload.php` now verifies the real content type of every uploaded recording (not just the client-supplied filename/extension) and rejects anything that isn't audio or video, closing a stored XSS where a non-media file could be uploaded and later served inline to a grader.
- The submission file server now forces download for any stored file that isn't audio/video, as defense in depth.
- The submission file server now validates the requested file area.
- The upload endpoint now enforces the course/site maximum upload size server-side.

## v0.1.0

First public release.

- Students record audio or video for an assignment directly in the browser (MediaRecorder API); the recording is uploaded to Moodle and stored as a regular submission file.
- Teachers choose the allowed recording type (audio, video, or either) and a maximum recording length per assignment.
- Site administrators control recording quality (audio/video bitrates) and whether students may switch cameras during the video preview.
- Course backup, restore and reset are fully supported.
- Privacy API (GDPR) implemented: recordings are exported with, and deleted from, user data on request.
- Continuous integration with moodle-plugin-ci on Moodle 5.0, 5.1 and 5.2 (PHP 8.2–8.4, PostgreSQL 16 and MariaDB 10.11).
