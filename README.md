# Recording submission plugin for Moodle assignments

**Component:** `assignsubmission_recording`  
**Requires:** Moodle 4.5+ (version 2024100700)  
**Maturity:** Alpha

Replaces the text editor in assignment submissions with a browser-based audio/video recorder. Students record directly in their browser — no third-party service or app required.

## Features

- Record **audio**, **video**, or offer the student a choice of either
- Configurable **maximum recording length** per assignment (auto-stops when reached, with live countdown)
- **Camera switching** during video preview (optional, site-wide setting)
- Re-submission shows the existing recording for playback before recording again
- Site-wide **bitrate settings** for audio and video quality
- Full **backup/restore** support — recordings survive course backup/restore cycles
- **Privacy API** implemented — recordings are exported and deleted with GDPR requests

## Installation

Copy or symlink the plugin directory to your Moodle installation:

```
{moodle_root}/mod/assign/submission/recording/
```

Then run the Moodle upgrade:

```bash
php admin/cli/upgrade.php --non-interactive
```

### JavaScript build

The AMD module must be compiled before use. From the Moodle root:

```bash
grunt amd --root=mod/assign/submission/recording
```

For development without building, add to `config.php`:

```php
$CFG->cachejs = false;
$CFG->jsrev = -1;
```

Note: even in dev mode, `amd/build/recorder.min.js` must exist. Run grunt at least once.

## Configuration

### Site-wide settings

*Site administration → Plugins → Assignment → Submission plugins → Recording submission*

| Setting | Default | Description |
|---------|---------|-------------|
| Enabled by default | Off | Whether new assignments have this plugin enabled |
| Audio bitrate | 128 kb/s | Quality of audio track in all recordings |
| Video bitrate | 2500 kb/s | Quality of video track in video recordings |
| Allow switching cameras | Off | Show a "Switch camera" button during video preview |

### Per-assignment settings

When creating or editing an assignment, under **Submission types**:

| Setting | Default | Description |
|---------|---------|-------------|
| Allowed recording type | Audio or video | Restrict students to audio-only, video-only, or either |
| Maximum recording length | 2 minutes | Recording stops automatically at this length; 0 = no limit |

## How it works

1. Student opens the submission form — a recorder UI is rendered by the AMD module into the form.
2. Student clicks **Record audio** or **Record video** and grants browser permission.
3. For video, a live camera preview is shown; student clicks **Start recording** when ready.
4. On stopping, the recording is immediately uploaded to a Moodle draft file area via `upload.php`.
5. The resulting `draftfile.php` URL is embedded as `<audio>` or `<video>` HTML in a hidden form field.
6. On form submission, Moodle moves the file to the permanent submission file area and rewrites the URL to `@@PLUGINFILE@@` for portable storage.
7. On view/grade, the stored URL is rewritten back to a `pluginfile.php` URL and Moodle's media filter wraps it in a VideoJS player.

## File areas

| File area | Description |
|-----------|-------------|
| `submissions_recording` | Permanent storage of submitted recordings, itemid = submission id |

## Browser support

Uses the [MediaRecorder API](https://developer.mozilla.org/en-US/docs/Web/API/MediaRecorder). Supported in all modern browsers (Chrome, Firefox, Edge, Safari 14.1+). Not supported in Internet Explorer.

Recorded format is `webm` (Chromium/Firefox) or `mp4` (Safari). Moodle's media filter handles both.

## License

GNU GPL v3 or later — see [COPYING.txt](https://www.gnu.org/licenses/gpl-3.0.txt).

Copyright 2026 Adam Jenkins <adam@wisecat.net>
