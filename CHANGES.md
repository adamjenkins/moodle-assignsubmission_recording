# Changes

## v0.1.4

- Maturity raised from Alpha to Beta.
- The maximum recording length is now also bounded on the server: an upload (or a file in
  the submission's draft area) larger than that length allows at the site's recording
  bitrates is rejected. This is a file-size limit derived from the length, not a measured
  duration; in an "audio or video" assignment an audio recording is held to the audio
  limit. Previously only the browser's auto-stop applied.
- The audio-only / video-only setting is now enforced when the submission is saved: an
  audio-only assignment rejects a video embed, and a video-only one an audio embed. The
  upload endpoint also rejects any media type other than audio or video.
- Requires Moodle 5.0 or later (`$plugin->requires` now matches the declared supported range).
- Added PHPUnit tests for the upload rules, file serving access and download handling,
  saving and rendering, and the privacy provider.
- Continuous integration now tests against the Moodle 5.3 stable branch (MOODLE_503_STABLE)
  instead of Moodle's development branch.
- `composer.json` now uses a caret constraint for `moodle/moodle`, so newer Moodle 5.x
  releases are not excluded.
- Tagged releases are now also published to the camp plugin registry.
