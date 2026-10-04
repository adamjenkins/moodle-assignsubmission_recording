# Changelog

All notable changes to the Recording submission plugin (`assignsubmission_recording`) are documented in this file.

## [Unreleased]

### Fixed

- The per-assignment maximum recording length was enforced only by the browser recorder's auto-stop, so a direct request to `upload.php` could store a recording of any length. The server now rejects an upload larger than the maximum length allows at the site's configured bitrates (with headroom for encoder overshoot), and `save()` re-checks the files in the draft area, which can also be filled through core's repository upload. The limit is a file-size bound derived from the length, not a measured duration (MediaRecorder WebM files usually carry none), so a recording encoded at a lower bitrate can still run longer. `save()` picks the audio or video size limit from the submitted embed, so in an "audio or video" assignment an audio recording is not held only to the much larger video limit.
- The audio-only / video-only setting was checked only against the client-reported media type. `save()` now rejects a `<video>` embed in an audio-only assignment and an `<audio>` embed in a video-only one, and `upload.php` rejects media types other than `audio` or `video`.

### Changed

- `$plugin->requires` raised from Moodle 4.5 (2024100700) to Moodle 5.0 (2025041400), the lowest branch in `$plugin->supported`.
- The upload rules and the file-serving access rules moved into `\assignsubmission_recording\local\recording_upload` and `\assignsubmission_recording\local\file_access` so they can be tested. A request that itself asks for a download (`?forcedownload=1`) is now honoured.

### Added

- PHPUnit tests covering the upload endpoint's rules (capability, content-type allowlist, size and length limits, recording mode), file serving (access per user and assignment, forced download for non-media files), saving and purified rendering, and the privacy provider (export and all three delete paths).

## [0.1.3] - 2026-10-03

### Changed

- Declare Moodle 5.3 support.

## [0.1.2] - 2026-08-04

### Added

- The full GPL-3.0 licence text is now included as `LICENSE` in the repository
  root. The plugin's licence is unchanged (GPL-3.0-or-later, as declared in
  `composer.json`); the file was simply missing.

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
