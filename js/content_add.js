document.addEventListener('DOMContentLoaded', function () {
    var targetSelect = document.getElementById('itemStation');
    var slotField = document.getElementById('slotField');
    var contentTypeSelect = document.getElementById('contentType');
    var contentFile = document.getElementById('contentFile');
    var fileHelp = document.getElementById('fileHelp');
    var imageHelp = fileHelp ? (fileHelp.dataset.imageHelp || '') : '';
    var videoHelp = fileHelp ? (fileHelp.dataset.videoHelp || '') : '';

    function updateTargetState() {
        if (!targetSelect || !slotField) {
            return;
        }
        slotField.hidden = targetSelect.value !== '0';
    }

    function updateFileAccept() {
        if (!contentTypeSelect || !contentFile || !fileHelp) {
            return;
        }
        if (contentTypeSelect.value === 'movie') {
            contentFile.setAttribute('accept', '.mp4,.webm,.ogv,.mov,.m4v,video/mp4,video/webm,video/ogg,video/quicktime');
            fileHelp.textContent = videoHelp;
        } else {
            contentFile.setAttribute('accept', '.jpg,.jpeg,.png,.gif,.webp,image/jpeg,image/png,image/gif,image/webp');
            fileHelp.textContent = imageHelp;
        }
        contentFile.value = '';
    }

    if (targetSelect) {
        targetSelect.addEventListener('change', updateTargetState);
    }
    if (contentTypeSelect) {
        contentTypeSelect.addEventListener('change', updateFileAccept);
    }

    updateTargetState();
    updateFileAccept();
});
