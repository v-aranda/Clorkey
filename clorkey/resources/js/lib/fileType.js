const PDF_MIME_ALIASES = new Set([
    'application/pdf',
    'application/x-pdf',
    'application/acrobat',
    'applications/vnd.pdf',
    'text/pdf',
    'text/x-pdf',
]);

function getMime(file) {
    return String(file?.mime_type || '').toLowerCase();
}

function getName(file) {
    return String(file?.name || '').toLowerCase();
}

export function isPdfFile(file) {
    const mime = getMime(file);
    const name = getName(file);

    return PDF_MIME_ALIASES.has(mime) || mime.includes('/pdf') || name.endsWith('.pdf');
}

export function isImageFile(file) {
    return getMime(file).startsWith('image/');
}

export function isVideoFile(file) {
    return getMime(file).startsWith('video/');
}
