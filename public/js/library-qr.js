/* QR-code helpers. QR generation stays local; inventory barcodes are never sent to an external service. */
window.LibraryQr = (() => {
    function dataUrl(text) {
        if (typeof QRCode === 'undefined') throw new Error('QR-library niet geladen.');
        const container = document.createElement('div');
        container.style.position = 'absolute';
        container.style.left = '-10000px';
        document.body.appendChild(container);
        try {
            new QRCode(container, {
                text: String(text),
                width: 280,
                height: 280,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
            const canvas = container.querySelector('canvas');
            if (!canvas) throw new Error('QR-canvas kon niet worden gemaakt.');
            return canvas.toDataURL('image/png');
        } finally {
            container.remove();
        }
    }
    function download(href, filename) {
        const link = document.createElement('a');
        link.href = href;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
    }
    function safeName(barcode) {
        return String(barcode).replace(/[^a-z0-9_-]/gi, '_').slice(0, 70);
    }
    function createLabel(item) {
        const label = document.createElement('div');
        label.className = 'library-qr-label';
        const heading = document.createElement('strong');
        heading.textContent = item.barcode;
        const picture = document.createElement('img');
        picture.src = dataUrl(item.barcode);
        picture.alt = 'QR-code voor ' + item.barcode;
        const title = document.createElement('small');
        title.textContent = item.title || '';
        label.append(heading, picture, title);
        return label;
    }
    async function downloadZip(items, filename) {
        if (typeof JSZip === 'undefined') throw new Error('ZIP-library niet geladen.');
        const zip = new JSZip();
        for (const item of items) {
            zip.file('QR-' + safeName(item.barcode) + '.png', dataUrl(item.barcode).split(',')[1], {base64:true});
        }
        const blob = await zip.generateAsync({type:'blob'});
        const url = URL.createObjectURL(blob);
        try { download(url, filename); }
        finally { setTimeout(() => URL.revokeObjectURL(url), 60000); }
    }
    return {dataUrl, download, safeName, createLabel, downloadZip};
})();
