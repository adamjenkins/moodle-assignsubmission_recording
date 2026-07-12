# Changelog

All notable changes to the Recording submission plugin (`assignsubmission_recording`) are documented in this file.

## [0.1.1] - 2026-07-12

### Security

- Fixed a stored XSS: `upload.php` accepted any file type and stored it with the client-supplied filename/extension, and `assignsubmission_recording_pluginfile()` served submission files inline regardless of type. A student could upload an HTML file disguised as a recording that would execute in a grader's session if opened. Uploads are now validated against their real (content-sniffed) MIME type and rejected unless audio/video; stored filenames are generated server-side; and the file server forces download for any non-media file as defense in depth.

### Fixed

- `assignsubmission_recording_pluginfile()` now validates the requested file area instead of accepting any value.
- `upload.php` now enforces the course/site maximum upload size server-side; previously only the client-side recorder's timer bounded recording length.

## [0.1.0] - 2026-06-15

### Added

- Initial release of the recording submission plugin for Moodle assignments.
- In-browser audio and video recording of assignment submissions using the MediaRecorder API — no third-party service required.
- Per-assignment settings for the allowed recording type (audio, video, or either) and the maximum recording length with live countdown and auto-stop.
- Site-wide settings for audio/video bitrate and optional camera switching during video preview.
- Playback of the existing recording when re-submitting.
- Full course backup and restore support for submitted recordings.
- Privacy (GDPR) provider: recordings are included in user data exports and deletions.
- GitHub Actions CI using moodle-plugin-ci (Moodle 5.0, 5.1 and 5.2; PHP 8.2–8.4; PostgreSQL and MariaDB).
