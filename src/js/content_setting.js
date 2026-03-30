document.addEventListener('DOMContentLoaded', function () {
    var intervalInput = document.getElementById('swapIntervalSeconds');
    var dataElement = document.getElementById('content-setting-data');
    var selectedList = document.getElementById('selectedList');
    var selectedCount = document.getElementById('selectedCount');
    var publishInputs = document.getElementById('publishInputs');
    var dropzone = document.getElementById('contentDropzone');
    var dropzoneEmpty = document.getElementById('dropzoneEmpty');

    function normalizeIntervalValue(value) {
        if (!intervalInput) {
            return 0;
        }

        var min = parseInt(intervalInput.getAttribute('min') || '0', 10);
        var max = parseInt(intervalInput.getAttribute('max') || '0', 10);
        var next = parseInt(value, 10);

        if (isNaN(next)) {
            next = min;
        }
        if (!isNaN(min) && next < min) {
            next = min;
        }
        if (!isNaN(max) && max > 0 && next > max) {
            next = max;
        }

        return next;
    }

    if (intervalInput) {
        document.querySelectorAll('[data-interval-step]').forEach(function (button) {
            button.addEventListener('click', function () {
                var step = parseInt(button.getAttribute('data-interval-step') || '0', 10);
                var current = parseInt(intervalInput.value || '0', 10) || 0;
                intervalInput.value = String(normalizeIntervalValue(current + step));
                intervalInput.focus();
                intervalInput.select();
            });
        });

        intervalInput.addEventListener('change', function () {
            intervalInput.value = String(normalizeIntervalValue(intervalInput.value));
        });

        intervalInput.addEventListener('blur', function () {
            intervalInput.value = String(normalizeIntervalValue(intervalInput.value));
        });
    }

    if (!dataElement || !selectedList || !publishInputs || !dropzone || !dropzoneEmpty) {
        return;
    }

    var data = { items: [], selected: [], limit: 3 };
    try {
        data = JSON.parse(dataElement.textContent || '{}');
    } catch (error) {
        data = { items: [], selected: [], limit: 3 };
    }

    var items = {};
    (data.items || []).forEach(function (item) {
        items[String(item.id)] = item;
    });

    var selected = (data.selected || []).map(function (id) {
        return String(id);
    }).filter(function (id) {
        return !!items[id];
    });

    var limit = parseInt(data.limit || 3, 10) || 3;
    var dragState = {
        source: '',
        id: ''
    };

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function clearDropIndicators() {
        dropzone.classList.remove('is-dragover');
        dropzone.classList.remove('border-blue-300', 'bg-blue-50');
        selectedList.querySelectorAll('.content-selected-slot').forEach(function (slot) {
            slot.classList.remove('ring-2', 'ring-blue-300', 'border-blue-300', 'bg-blue-50');
        });
        selectedList.querySelectorAll('.content-selected-card').forEach(function (card) {
            card.classList.remove('is-dragging');
            card.classList.remove('opacity-60', 'scale-[0.98]');
        });
        document.querySelectorAll('.js-setting-item').forEach(function (card) {
            card.classList.remove('is-dragging');
            card.classList.remove('opacity-60', 'scale-[0.98]');
        });
    }

    function resetDragState() {
        dragState.source = '';
        dragState.id = '';
        clearDropIndicators();
    }

    function syncGallery() {
        document.querySelectorAll('.js-setting-item').forEach(function (card) {
            var id = card.getAttribute('data-id') || '';
            var isSelected = selected.indexOf(id) !== -1;
            card.classList.toggle('is-selected', isSelected);
            card.classList.toggle('ring-2', isSelected);
            card.classList.toggle('ring-blue-300', isSelected);
            card.classList.toggle('bg-blue-50/70', isSelected);
        });
    }

    function removeSelected(id) {
        selected = selected.filter(function (selectedId) {
            return selectedId !== id;
        });
        renderSelected();
    }

    function moveSelected(id, targetIndex) {
        var currentIndex = selected.indexOf(id);
        if (currentIndex === -1) {
            return;
        }

        if (currentIndex === targetIndex) {
            renderSelected();
            return;
        }

        selected.splice(currentIndex, 1);
        if (targetIndex < 0) {
            targetIndex = 0;
        }
        if (targetIndex > selected.length) {
            targetIndex = selected.length;
        }
        selected.splice(targetIndex, 0, id);
        renderSelected();
    }

    function insertSelected(id, targetIndex) {
        if (!items[id]) {
            return;
        }

        if (selected.indexOf(id) !== -1) {
            moveSelected(id, targetIndex);
            return;
        }

        if (selected.length >= limit) {
            window.alert('最大' + limit + '件まで選択できます。');
            return;
        }

        if (targetIndex < 0) {
            targetIndex = 0;
        }
        if (targetIndex > selected.length) {
            targetIndex = selected.length;
        }
        selected.splice(targetIndex, 0, id);
        renderSelected();
    }

    function updateDropIndicator(slot) {
        clearDropIndicators();
        dropzone.classList.add('is-dragover');
        dropzone.classList.add('border-blue-300', 'bg-blue-50');
        slot.classList.add('ring-2', 'ring-blue-300', 'border-blue-300', 'bg-blue-50');
    }

    function buildSelectedSlot(index) {
        var id = selected[index] || '';
        var item = id ? items[id] : null;
        var slot = document.createElement('div');
        slot.className = 'content-selected-slot rounded-[26px] border border-dashed border-slate-300 bg-white/80 p-3 transition';
        slot.setAttribute('data-slot-index', String(index));

        var slotLabel = document.createElement('div');
        slotLabel.className = 'mb-3 inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600';
        slotLabel.textContent = '表示枠 ' + (index + 1);
        slot.appendChild(slotLabel);

        slot.addEventListener('dragover', function (event) {
            if (!dragState.id) {
                return;
            }
            event.preventDefault();
            event.stopPropagation();
            updateDropIndicator(slot);
        });

        slot.addEventListener('dragleave', function (event) {
            if (event.relatedTarget && slot.contains(event.relatedTarget)) {
                return;
            }
            clearDropIndicators();
        });

        slot.addEventListener('drop', function (event) {
            var droppedId = dragState.id;
            event.preventDefault();
            event.stopPropagation();

            if (!droppedId && event.dataTransfer) {
                droppedId = event.dataTransfer.getData('text/plain');
            }
            if (!droppedId) {
                resetDragState();
                return;
            }

            if (dragState.source === 'selected') {
                moveSelected(droppedId, index);
            } else {
                insertSelected(droppedId, index);
            }
            resetDragState();
        });

        if (!item) {
            var empty = document.createElement('div');
            empty.className = 'flex aspect-[16/10] items-center justify-center rounded-[22px] border border-dashed border-slate-200 bg-slate-50 px-5 text-center text-sm font-medium text-slate-400';
            empty.textContent = 'ここにドロップ';
            slot.appendChild(empty);
            return slot;
        }

        var card = document.createElement('div');
        var thumbHtml = item.type === 'movie'
            ? '<div class="content-selected-thumb js-content-preview relative aspect-[16/10] overflow-hidden rounded-[22px] bg-slate-100" data-preview-type="movie" data-preview-src="' + escapeHtml(item.value) + '" data-preview-title="' + escapeHtml(item.title || 'content') + '"><video class="h-full w-full object-cover" src="' + escapeHtml(item.value) + '" muted playsinline preload="metadata"></video><span class="content-card-play absolute inset-x-0 bottom-3 mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-slate-950 shadow-lg">&#9654;</span></div>'
            : '<div class="content-selected-thumb relative aspect-[16/10] overflow-hidden rounded-[22px] bg-slate-100"><img class="h-full w-full object-cover" src="' + escapeHtml(item.value) + '" alt="' + escapeHtml(item.title || 'content') + '"></div>';

        card.className = 'content-selected-card relative grid gap-4 rounded-[22px] border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_30px_rgba(15,23,42,0.06)] transition';
        card.setAttribute('data-id', id);
        card.setAttribute('draggable', 'true');
        card.innerHTML =
            '<div class="content-selected-handle text-xs font-semibold uppercase tracking-[0.18em] text-slate-400" aria-hidden="true">drag</div>' +
            thumbHtml +
            '<div class="content-selected-meta space-y-3"><span class="content-card-tag inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-slate-600">' + escapeHtml(item.station) + '</span><strong class="block text-base font-semibold leading-7 text-slate-900">' + escapeHtml(item.title || 'content') + '</strong></div>' +
            '<button type="button" class="content-selected-remove absolute right-4 top-4 inline-flex h-9 w-9 items-center justify-center rounded-full bg-rose-50 text-sm font-bold text-rose-700 transition hover:bg-rose-100" data-remove="' + escapeHtml(id) + '" aria-label="remove">×</button>';

        var removeButton = card.querySelector('.content-selected-remove');
        if (removeButton) {
            removeButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                removeSelected(id);
            });
        }

        card.addEventListener('dragstart', function (event) {
            dragState.source = 'selected';
            dragState.id = id;
            card.classList.add('is-dragging');
            card.classList.add('opacity-60', 'scale-[0.98]');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', 'selected:' + id);
            }
        });

        card.addEventListener('dragend', function () {
            resetDragState();
        });

        slot.appendChild(card);
        return slot;
    }

    function renderSelected() {
        selectedList.innerHTML = '';
        publishInputs.innerHTML = '';
        dropzoneEmpty.hidden = false;

        if (selectedCount) {
            selectedCount.textContent = selected.length + ' / ' + limit;
        }

        for (var index = 0; index < limit; index += 1) {
            selectedList.appendChild(buildSelectedSlot(index));
        }

        selected.forEach(function (id) {
            var item = items[id];
            if (!item) {
                return;
            }

            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'publish_ids[]';
            input.value = id;
            publishInputs.appendChild(input);
        });

        syncGallery();
    }

    function addSelected(id) {
        if (!items[id] || selected.indexOf(id) !== -1) {
            return;
        }
        if (selected.length >= limit) {
            window.alert('最大' + limit + '件まで選択できます。');
            return;
        }
        selected.push(id);
        renderSelected();
    }

    document.querySelectorAll('.js-setting-item').forEach(function (card) {
        card.addEventListener('click', function () {
            var id = card.getAttribute('data-id') || '';
            if (selected.indexOf(id) !== -1) {
                removeSelected(id);
            } else {
                addSelected(id);
            }
        });

        card.addEventListener('dragstart', function (event) {
            dragState.source = 'gallery';
            dragState.id = card.getAttribute('data-id') || '';
            card.classList.add('is-dragging');
            card.classList.add('opacity-60', 'scale-[0.98]');
            if (event.dataTransfer) {
                event.dataTransfer.effectAllowed = 'copyMove';
                event.dataTransfer.setData('text/plain', dragState.id);
            }
        });

        card.addEventListener('dragend', function () {
            resetDragState();
        });
    });

    dropzone.addEventListener('dragover', function (event) {
        event.preventDefault();
        dropzone.classList.add('is-dragover');
        dropzone.classList.add('border-blue-300', 'bg-blue-50');
    });

    dropzone.addEventListener('dragleave', function () {
        dropzone.classList.remove('is-dragover');
        dropzone.classList.remove('border-blue-300', 'bg-blue-50');
    });

    dropzone.addEventListener('drop', function (event) {
        event.preventDefault();
        if (event.target && event.target.closest('.content-selected-slot')) {
            return;
        }
        if (dragState.source === 'selected') {
            moveSelected(dragState.id, selected.length - 1);
        } else if (event.dataTransfer) {
            insertSelected(event.dataTransfer.getData('text/plain'), selected.length);
        }
        resetDragState();
    });

    renderSelected();
});
