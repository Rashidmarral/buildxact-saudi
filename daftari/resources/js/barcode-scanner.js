import { BrowserMultiFormatReader, NotFoundException } from '@zxing/library';

// A single reusable camera-barcode-scan modal, built once and reopened by
// every "Scan barcode" button in the app (item form, invoice/quotation/
// bill/PO line items) rather than each caller building its own <video> +
// permissions dance. Call window.DaftariBarcodeScanner.open(onDecode);
// onDecode receives the decoded barcode text once, after which the camera
// stream is stopped and the modal closes itself.

let modal, video, statusEl, closeBtn;
let reader;
let onDecodeCallback = null;

function ensureModal() {
    if (modal) return;

    modal = document.createElement('dialog');
    modal.className = 'rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40';
    modal.innerHTML = `
        <div class="p-6 space-y-4">
            <div class="flex items-start justify-between">
                <h3 class="text-lg font-bold text-slate-900" data-scan-title>Scan barcode</h3>
                <button type="button" data-scan-close class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <div class="relative overflow-hidden rounded-xl bg-slate-900 aspect-[4/3]">
                <video data-scan-video class="h-full w-full object-cover" playsinline muted></video>
            </div>
            <p data-scan-status class="text-sm text-slate-500 text-center">Point the camera at a barcode.</p>
        </div>
    `;
    document.body.appendChild(modal);

    video = modal.querySelector('[data-scan-video]');
    statusEl = modal.querySelector('[data-scan-status]');
    closeBtn = modal.querySelector('[data-scan-close]');

    closeBtn.addEventListener('click', () => close());
    modal.addEventListener('close', () => stopCamera());
}

function stopCamera() {
    if (reader) {
        try {
            reader.reset();
        } catch (e) {
            // ignore — reader may already be stopped
        }
    }
    if (video && video.srcObject) {
        video.srcObject.getTracks().forEach((track) => track.stop());
        video.srcObject = null;
    }
}

function close() {
    stopCamera();
    if (modal.open) modal.close();
}

async function open(onDecode, title, helpText) {
    ensureModal();
    onDecodeCallback = onDecode;
    modal.querySelector('[data-scan-title]').textContent = title || 'Scan barcode';
    statusEl.textContent = helpText || 'Point the camera at a barcode.';
    modal.showModal();

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        statusEl.textContent = 'Camera access is not supported in this browser.';
        return;
    }

    reader = new BrowserMultiFormatReader();

    try {
        await reader.decodeFromConstraints(
            { video: { facingMode: 'environment' } },
            video,
            (result, err) => {
                if (result && onDecodeCallback) {
                    const code = result.getText();
                    const cb = onDecodeCallback;
                    onDecodeCallback = null;
                    close();
                    cb(code);
                    return;
                }
                if (err && !(err instanceof NotFoundException)) {
                    statusEl.textContent = 'Scan error — try repositioning the barcode.';
                }
            }
        );
    } catch (e) {
        statusEl.textContent = 'Could not access the camera. Check permissions and try again.';
    }
}

window.DaftariBarcodeScanner = { open };
