(function() {
    // Use the localized data passed from PHP
    if (typeof settagordEditorData === 'undefined') {
        console.error('[Set Tag Order] Editor data not localized.');
        return;
    }

    var ajaxurl = settagordEditorData.ajaxurl;
    var nonce = settagordEditorData.nonce;

    // Function to send the AJAX request
    function sendEditorMode(mode) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', ajaxurl);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('action=settagord_editor_mode&mode=' + mode + '&_wpnonce=' + nonce);
        console.log('[Set Tag Order] Sent editor mode: ' + mode);
    }

    // Most reliable way to detect Block Editor - check for wp.blocks
    var isBlockEditor = typeof wp !== 'undefined' && wp.blocks && wp.blocks.registerBlockType;

    // If wp.blocks exists, we're definitely in Block Editor
    if (isBlockEditor) {
        console.log('[Set Tag Order] Detected Block Editor via JavaScript API check');
        sendEditorMode('block');

        // When using Block Editor, make sure our debug logging knows
        wp.domReady(function() {
            console.log('[Set Tag Order] Block Editor fully loaded');
        });
    } else {
        // Check DOM for Classic Editor elements
        var isClassicEditor = document.getElementById('postdivrich') !== null ||
            document.getElementById('wp-content-editor-container') !== null;

        if (isClassicEditor) {
            console.log('[Set Tag Order] Detected Classic Editor via DOM check');
            sendEditorMode('classic');
        } else {
            console.log('[Set Tag Order] Could not detect editor type.');
            // Optionally send a default or 'unknown' status?
            // sendEditorMode('unknown');
        }
    }
})(); 