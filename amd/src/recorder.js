// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * RecordRTC recorder for the recording assignment submission plugin.
 *
 * Renders the recorder UI into the placeholder div added by get_form_elements(),
 * handles audio/video capture and upload, and writes the resulting embed HTML
 * into the hidden assignsubmission_recording_text field for the form to submit.
 *
 * @module     assignsubmission_recording/recorder
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {renderForPromise, appendNodeContents} from 'core/templates';
import {getString} from 'core/str';

const SELECTORS = {
    placeholder: '[data-region="assignsubmission-recording-recorder"]',
    recorder: '[data-region="recording-recorder"]',
    status: '[data-region="status"]',
    limitInfo: '[data-region="limit-info"]',
    countdown: '[data-region="countdown"]',
    previewWrapper: '[data-region="preview-wrapper"]',
    preview: '[data-region="preview"]',
    playback: '[data-region="playback"]',
    recordAudio: '[data-action="record-audio"]',
    recordVideo: '[data-action="record-video"]',
    startRecording: '[data-action="start-recording"]',
    switchCamera: '[data-action="switch-camera"]',
    cancelPreview: '[data-action="cancel-preview"]',
    stop: '[data-action="stop"]',
    rerecord: '[data-action="rerecord"]',
    textField: 'input[name="assignsubmission_recording_text"]',
    itemField: 'input[name="assignsubmission_recording_itemid"]',
};

/**
 * Pick a sensible file extension for the recorded MIME type.
 *
 * @param {String} mime The MIME type reported by the recorder.
 * @returns {String} A file extension without the leading dot.
 */
const extensionForMime = (mime) => {
    if (!mime) {
        return 'webm';
    }
    if (mime.indexOf('mp4') !== -1) {
        return 'mp4';
    }
    if (mime.indexOf('ogg') !== -1) {
        return 'ogg';
    }
    return 'webm';
};

/**
 * Format a number of seconds as a "minutes:seconds" string for display.
 *
 * @param {Number} totalSeconds The duration in seconds.
 * @returns {String} The duration formatted as M:SS.
 */
const formatDuration = (totalSeconds) => {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = Math.floor(totalSeconds % 60);
    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
};

/**
 * Controls a single recorder instance.
 */
class Recorder {
    /**
     * @param {HTMLElement} recorder The rendered recorder container.
     * @param {HTMLInputElement} textField The hidden text field for the embed HTML.
     * @param {HTMLFormElement} form The owning form.
     * @param {Object} config The init configuration.
     */
    constructor(recorder, textField, form, config) {
        this.recorder = recorder;
        this.textField = textField;
        this.form = form;
        this.config = config;
        this.mediaRecorder = null;
        this.stream = null;
        this.chunks = [];
        this.mediaType = null;
        this.embed = textField.value || '';
        this.videoDevices = [];
        this.deviceIndex = -1;
        this.autoStopTimer = null;
        this.autoStopped = false;
        this.countdownTimer = null;
        this.countdownLabel = '';
        this.recordingEndsAt = 0;

        this.statusEl = recorder.querySelector(SELECTORS.status);
        this.limitInfo = recorder.querySelector(SELECTORS.limitInfo);
        this.countdown = recorder.querySelector(SELECTORS.countdown);
        this.previewWrapper = recorder.querySelector(SELECTORS.previewWrapper);
        this.preview = recorder.querySelector(SELECTORS.preview);
        this.playback = recorder.querySelector(SELECTORS.playback);
        this.audioBtn = recorder.querySelector(SELECTORS.recordAudio);
        this.videoBtn = recorder.querySelector(SELECTORS.recordVideo);
        this.startRecordingBtn = recorder.querySelector(SELECTORS.startRecording);
        this.switchCameraBtn = recorder.querySelector(SELECTORS.switchCamera);
        this.cancelPreviewBtn = recorder.querySelector(SELECTORS.cancelPreview);
        this.stopBtn = recorder.querySelector(SELECTORS.stop);
        this.rerecordBtn = recorder.querySelector(SELECTORS.rerecord);

        this.registerListeners();
        this.showLimitInfo();

        // If there is already an existing recording, show it as playback.
        if (this.embed) {
            this.showExistingPlayback(this.embed);
            this.setButtons('recorded');
        }
    }

    /**
     * Display the configured maximum recording length, and preload the countdown label.
     */
    async showLimitInfo() {
        if (!this.config.maxduration || !this.limitInfo) {
            return;
        }

        const [limitText, countdownLabel] = await Promise.all([
            getString('maxlength', 'assignsubmission_recording', formatDuration(this.config.maxduration)),
            getString('timeremaininglabel', 'assignsubmission_recording'),
        ]);

        this.limitInfo.textContent = limitText;
        this.limitInfo.classList.remove('d-none');
        this.countdownLabel = countdownLabel;
    }

    /**
     * Wire up the control buttons.
     */
    registerListeners() {
        if (this.audioBtn) {
            this.audioBtn.addEventListener('click', () => this.start('audio'));
        }
        if (this.videoBtn) {
            this.videoBtn.addEventListener('click', () => this.start('video'));
        }
        if (this.startRecordingBtn) {
            this.startRecordingBtn.addEventListener('click', () => this.beginRecording());
        }
        if (this.switchCameraBtn) {
            this.switchCameraBtn.addEventListener('click', () => this.switchCamera());
        }
        if (this.cancelPreviewBtn) {
            this.cancelPreviewBtn.addEventListener('click', () => this.cancelPreview());
        }
        this.stopBtn.addEventListener('click', () => this.stop());
        this.rerecordBtn.addEventListener('click', () => this.reset());

        // Write the embed value into the text field just before submission.
        this.form.addEventListener('submit', () => {
            if (this.embed) {
                this.textField.value = this.embed;
            }
        }, true);
    }

    /**
     * Show a status message.
     *
     * @param {String} key The language string key.
     */
    async setStatus(key) {
        this.statusEl.textContent = await getString(key, 'assignsubmission_recording');
    }

    /**
     * Toggle which buttons are visible.
     *
     * @param {String} state One of 'idle', 'previewing', 'recording' or 'recorded'.
     */
    setButtons(state) {
        const showRecord = state === 'idle';
        if (this.audioBtn) {
            this.audioBtn.classList.toggle('d-none', !showRecord);
        }
        if (this.videoBtn) {
            this.videoBtn.classList.toggle('d-none', !showRecord);
        }
        if (this.startRecordingBtn) {
            this.startRecordingBtn.classList.toggle('d-none', state !== 'previewing');
        }
        if (this.switchCameraBtn) {
            this.switchCameraBtn.classList.toggle('d-none',
                !this.config.allowswitchcamera || state !== 'previewing' || this.videoDevices.length < 2);
        }
        if (this.cancelPreviewBtn) {
            this.cancelPreviewBtn.classList.toggle('d-none', state !== 'previewing');
        }
        this.stopBtn.classList.toggle('d-none', state !== 'recording');
        this.rerecordBtn.classList.toggle('d-none', state !== 'recorded');
    }

    /**
     * Parse an existing embed tag and show it as a playback element.
     *
     * @param {String} embedHtml The stored embed HTML.
     */
    showExistingPlayback(embedHtml) {
        this.clearPlayback();
        const isVideo = /<\s*video/i.test(embedHtml);
        const type = isVideo ? 'video' : 'audio';

        // Extract the src URL from the first <source> or src attribute.
        const srcMatch = embedHtml.match(/src="([^"]+)"/);
        if (!srcMatch) {
            return;
        }

        const player = document.createElement(type);
        player.controls = true;
        player.src = srcMatch[1];
        if (isVideo) {
            player.classList.add('w-100', 'rounded');
        }
        this.playback.appendChild(player);
        this.playback.classList.remove('d-none');
    }

    /**
     * Start capturing media of the given type.
     *
     * @param {String} type Either 'audio' or 'video'.
     */
    async start(type) {
        this.mediaType = type;
        this.chunks = [];
        this.clearPlayback();

        const constraints = type === 'video' ? {audio: true, video: true} : {audio: true};

        try {
            this.stream = await navigator.mediaDevices.getUserMedia(constraints);
        } catch (e) {
            this.setStatus('errornopermission');
            return;
        }

        if (type === 'video') {
            if (this.config.allowswitchcamera) {
                await this.detectCameras();
            }
            this.preview.srcObject = this.stream;
            this.previewWrapper.classList.remove('d-none');
            this.preview.play().catch(() => {
                return;
            });
            this.setButtons('previewing');
            this.setStatus('previewready');
            return;
        }

        this.beginRecording();
    }

    /**
     * Enumerate available video input devices once permission is granted.
     */
    async detectCameras() {
        this.videoDevices = [];
        this.deviceIndex = -1;

        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            return;
        }

        try {
            const devices = await navigator.mediaDevices.enumerateDevices();
            this.videoDevices = devices.filter((device) => device.kind === 'videoinput');
        } catch (e) {
            this.videoDevices = [];
            return;
        }

        const track = this.stream && this.stream.getVideoTracks()[0];
        const currentId = track && track.getSettings && track.getSettings().deviceId;
        const matchedIndex = currentId
            ? this.videoDevices.findIndex((device) => device.deviceId === currentId)
            : -1;
        this.deviceIndex = matchedIndex !== -1 ? matchedIndex : 0;
    }

    /**
     * Switch the camera preview to the next available video input device.
     */
    async switchCamera() {
        if (this.videoDevices.length < 2) {
            return;
        }

        const nextIndex = (this.deviceIndex + 1) % this.videoDevices.length;
        const deviceId = this.videoDevices[nextIndex].deviceId;

        let newStream;
        try {
            newStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: {deviceId: {exact: deviceId}},
            });
        } catch (e) {
            this.setStatus('errornopermission');
            return;
        }

        const newTrack = newStream.getVideoTracks()[0];
        const oldTrack = this.stream.getVideoTracks()[0];
        if (oldTrack) {
            this.stream.removeTrack(oldTrack);
            oldTrack.stop();
        }
        this.stream.addTrack(newTrack);

        this.preview.srcObject = this.stream;
        this.preview.play().catch(() => {
            return;
        });

        this.deviceIndex = nextIndex;
    }

    /**
     * Start recording from the already-acquired media stream.
     */
    beginRecording() {
        const options = {};
        if (this.config.audiobitrate) {
            options.audioBitsPerSecond = this.config.audiobitrate;
        }
        if (this.mediaType === 'video' && this.config.videobitrate) {
            options.videoBitsPerSecond = this.config.videobitrate;
        }

        try {
            this.mediaRecorder = new MediaRecorder(this.stream, options);
        } catch (e) {
            this.setStatus('errorunsupported');
            this.cancelPreview();
            return;
        }

        this.mediaRecorder.addEventListener('dataavailable', (e) => {
            if (e.data && e.data.size > 0) {
                this.chunks.push(e.data);
            }
        });
        this.mediaRecorder.addEventListener('stop', () => this.onStop());
        this.mediaRecorder.start();

        this.scheduleAutoStop();
        this.startCountdown();

        this.setButtons('recording');
        this.setStatus('recording');
    }

    /**
     * Abandon a camera preview without recording.
     */
    cancelPreview() {
        this.stopStream();
        this.previewWrapper.classList.add('d-none');
        this.preview.srcObject = null;
        this.mediaType = null;
        this.videoDevices = [];
        this.deviceIndex = -1;
        this.setButtons('idle');
        this.setStatus('recorderintro');
    }

    /**
     * Schedule an automatic stop when the maximum recording duration is reached.
     */
    scheduleAutoStop() {
        if (!this.config.maxduration) {
            return;
        }
        this.autoStopTimer = setTimeout(() => {
            this.autoStopped = true;
            this.stop();
        }, this.config.maxduration * 1000);
    }

    /**
     * Cancel a pending auto-stop.
     */
    clearAutoStop() {
        if (this.autoStopTimer) {
            clearTimeout(this.autoStopTimer);
            this.autoStopTimer = null;
        }
    }

    /**
     * Start the live countdown display.
     */
    startCountdown() {
        if (!this.config.maxduration || !this.countdown) {
            return;
        }

        this.recordingEndsAt = Date.now() + (this.config.maxduration * 1000);
        if (this.limitInfo) {
            this.limitInfo.classList.add('d-none');
        }
        this.countdown.classList.remove('d-none');
        this.updateCountdown();
        this.countdownTimer = setInterval(() => this.updateCountdown(), 250);
    }

    /**
     * Refresh the countdown display.
     */
    updateCountdown() {
        const remaining = Math.max(0, (this.recordingEndsAt - Date.now()) / 1000);
        this.countdown.textContent = `${this.countdownLabel}: ${formatDuration(remaining)}`;
    }

    /**
     * Stop the countdown and restore the static duration notice.
     */
    stopCountdown() {
        if (this.countdownTimer) {
            clearInterval(this.countdownTimer);
            this.countdownTimer = null;
        }
        if (!this.countdown) {
            return;
        }
        this.countdown.classList.add('d-none');
        if (this.config.maxduration && this.limitInfo) {
            this.limitInfo.classList.remove('d-none');
        }
    }

    /**
     * Stop the active recording.
     */
    stop() {
        this.clearAutoStop();
        this.stopCountdown();
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }
        this.stopStream();
    }

    /**
     * Stop and release all media tracks.
     */
    stopStream() {
        if (this.stream) {
            this.stream.getTracks().forEach((track) => track.stop());
            this.stream = null;
        }
    }

    /**
     * Handle the end of a recording: preview it locally, then upload and embed it.
     */
    async onStop() {
        const type = this.mediaType;
        const mime = (this.chunks[0] && this.chunks[0].type)
            || (this.mediaRecorder && this.mediaRecorder.mimeType)
            || (type === 'video' ? 'video/webm' : 'audio/webm');
        const blob = new Blob(this.chunks, {type: mime});

        this.showPlayback(blob, type);
        this.previewWrapper.classList.add('d-none');
        this.preview.srcObject = null;
        this.setButtons('recorded');

        if (this.autoStopped) {
            this.autoStopped = false;
            await this.setStatus('recordingstopped');
        }

        try {
            await this.setStatus('uploading');
            const url = await this.upload(blob, type, mime);
            this.setEmbed(url, type);
            await this.setStatus('recorded');
        } catch (e) {
            this.setStatus('erroruploadfailed');
        }
    }

    /**
     * Show a local playback of the recording.
     *
     * @param {Blob} blob The recorded media.
     * @param {String} type Either 'audio' or 'video'.
     */
    showPlayback(blob, type) {
        this.clearPlayback();
        const player = document.createElement(type === 'video' ? 'video' : 'audio');
        player.controls = true;
        player.src = URL.createObjectURL(blob);
        if (type === 'video') {
            player.classList.add('w-100', 'rounded');
        }
        this.playback.appendChild(player);
        this.playback.classList.remove('d-none');
    }

    /**
     * Remove any existing playback element.
     */
    clearPlayback() {
        this.playback.innerHTML = '';
        this.playback.classList.add('d-none');
    }

    /**
     * Upload the recorded blob to the draft file area.
     *
     * @param {Blob} blob The recorded media.
     * @param {String} type Either 'audio' or 'video'.
     * @param {String} mime The MIME type of the recording.
     * @returns {Promise<String>} The draftfile URL of the stored recording.
     */
    async upload(blob, type, mime) {
        const itemField = this.form.querySelector(SELECTORS.itemField);
        const itemid = itemField ? itemField.value : 0;
        const filename = `${type}-${Date.now()}.${extensionForMime(mime)}`;

        const formData = new FormData();
        formData.append('sesskey', M.cfg.sesskey);
        formData.append('itemid', itemid);
        formData.append('contextid', this.config.contextid);
        formData.append('mediatype', type);
        formData.append('recording', blob, filename);

        const response = await fetch(`${M.cfg.wwwroot}/mod/assign/submission/recording/upload.php`, {
            method: 'POST',
            body: formData,
        });
        const data = await response.json();

        if (!data || !data.url) {
            throw new Error('upload failed');
        }

        return data.url;
    }

    /**
     * Write the embed markup into the hidden text field.
     *
     * @param {String} url The draftfile URL of the recording.
     * @param {String} type Either 'audio' or 'video'.
     */
    setEmbed(url, type) {
        const tag = type === 'video' ? 'video' : 'audio';
        this.embed = `<${tag} controls="true"><source src="${url}"></${tag}>`;
        this.textField.value = this.embed;
        this.textField.dispatchEvent(new Event('change', {bubbles: true}));
    }

    /**
     * Reset the recorder so the user can record again.
     */
    reset() {
        this.clearAutoStop();
        this.stopCountdown();
        this.autoStopped = false;
        this.stopStream();
        this.chunks = [];
        this.embed = '';
        this.mediaType = null;
        this.videoDevices = [];
        this.deviceIndex = -1;
        this.textField.value = '';
        this.textField.dispatchEvent(new Event('change', {bubbles: true}));
        this.clearPlayback();
        this.previewWrapper.classList.add('d-none');
        this.preview.srcObject = null;
        this.setButtons('idle');
        this.setStatus('recorderintro');
    }
}

/**
 * Entry point called by PHP via $PAGE->requires->js_call_amd().
 *
 * @param {Object} config Configuration: contextid, submissionid, mode, maxduration,
 *   audiobitrate, videobitrate, allowswitchcamera, existingtext.
 */
export const init = (config) => {
    const placeholder = document.querySelector(SELECTORS.placeholder);
    if (!placeholder) {
        return;
    }

    const form = placeholder.closest('form');
    if (!form) {
        return;
    }

    const textField = form.querySelector(SELECTORS.textField);
    if (!textField) {
        return;
    }

    const allowAudio = config.mode === 'audio' || config.mode === 'both';
    const allowVideo = config.mode === 'video' || config.mode === 'both';

    renderForPromise('assignsubmission_recording/recorder', {
        allowaudio: allowAudio,
        allowvideo: allowVideo,
    }).then(({html, js}) => {
        appendNodeContents(placeholder, html, js);
        const recorderEl = placeholder.querySelector(SELECTORS.recorder);
        new Recorder(recorderEl, textField, form, config);
        return;
    }).catch(() => {
        return;
    });
};
