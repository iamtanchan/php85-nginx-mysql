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
        id: '',
        targetId: '',
        position: 'after'
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
        selectedList.querySelectorAll('.content-selected-card').forEach(function (card) {
            card.classList.remove('is-drop-before');
            card.classList.remove('is-drop-after');
            card.classList.remove('is-dragging');
            card.classList.remove('ring-2', 'ring-blue-300', 'opacity-60', 'scale-[0.98]');
        });
        document.querySelectorAll('.js-setting-item').forEach(function (card) {
            card.classList.remove('is-dragging');
            card.classList.remove('opacity-60', 'scale-[0.98]');
        });
    }

    function resetDragState() {
        dragState.source = '';
        dragState.id = '';
        dragState.targetId = '';
        dragState.position = 'after';
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

    function moveSelected(id, targetId, position) {
        var currentIndex = selected.indexOf(id);
        if (currentIndex === -1) {
            return;
        }

        selected.splice(currentIndex, 1);

        if (!targetId) {
            selected.push(id);
            renderSelected();
            return;
        }

        var targetIndex = selected.indexOf(targetId);
        if (targetIndex === -1) {
            selected.push(id);
            renderSelected();
            return;
        }

        selected.splice(position === 'before' ? targetIndex : targetIndex + 1, 0, id);
        renderSelected();
    }

    function insertSelected(id, targetId, position) {
        if (!items[id]) {
            return;
        }

        if (selected.indexOf(id) !== -1) {
            moveSelected(id, targetId, position);
            return;
        }

        if (selected.length >= limit) {
            window.alert('最大' + limit + '件まで選択できます。');
            return;
        }

        if (!targetId) {
            selected.push(id);
            renderSelected();
            return;
        }

        var targetIndex = selected.indexOf(targetId);
        if (targetIndex === -1) {
            selected.push(id);
        } else {
            selected.splice(position === 'before' ? targetIndex : targetIndex + 1, 0, id);
        }
        renderSelected();
    }

    function cardDropPosition(card, event) {
        var rect = card.getBoundingClientRect();
        var singleColumn = selectedList.clientWidth <= rect.width + 24;

        if (singleColumn) {
            return event.clientY < rect.top + rect.height / 2 ? 'before' : 'after';
        }

        return event.clientX < rect.left + rect.width / 2 ? 'before' : 'after';
    }

    function updateDropIndicator(card, position) {
        clearDropIndicators();
        dropzone.classList.add('is-dragover');
        dropzone.classList.add('border-blue-300', 'bg-blue-50');
        card.classList.add(position === 'before' ? 'is-drop-before' : 'is-drop-after');
        card.classList.add('ring-2', 'ring-blue-300');
    }

    function commitDrop(targetId, position) {
        if (!dragState.id) {
            return;
        }

        if (dragState.source === 'selected' && dragState.id === targetId) {
            resetDragState();
            return;
        }

        if (dragState.source === 'selected') {
            moveSelected(dragState.id, targetId, position);
        } else {
            insertSelected(dragState.id, targetId, position);
        }

        resetDragState();
    }

    function buildSelectedCard(id, index, item) {
        var card = document.createElement('div');
        var thumbHtml = item.type === 'movie'
            ? '<div class="content-selected-thumb js-content-preview relative aspect-[16/10] overflow-hidden rounded-[22px] bg-slate-100" data-preview-type="movie" data-preview-src="' + escapeHtml(item.value) + '" data-preview-title="' + escapeHtml(item.title || 'content') + '"><video class="h-full w-full object-cover" src="' + escapeHtml(item.value) + '" muted playsinline preload="metadata"></video><span class="content-card-play absolute inset-x-0 bottom-3 mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-white/90 text-slate-950 shadow-lg">&#9654;</span></div>'
            : '<div class="content-selected-thumb relative aspect-[16/10] overflow-hidden rounded-[22px] bg-slate-100"><img class="h-full w-full object-cover" src="' + escapeHtml(item.value) + '" alt="' + escapeHtml(item.title || 'content') + '"></div>';

        card.className = 'content-selected-card relative grid gap-4 rounded-[26px] border border-slate-200 bg-white px-4 py-4 shadow-[0_12px_30px_rgba(15,23,42,0.06)] transition';
        card.setAttribute('data-id', id);
        card.setAttribute('draggable', 'true');
        card.innerHTML =
            '<div class="content-selected-handle text-xs font-semibold uppercase tracking-[0.18em] text-slate-400" aria-hidden="true">drag</div>' +
            '<div class="content-selected-order inline-flex w-fit items-center rounded-full bg-slate-950 px-3 py-1 text-xs font-semibold tracking-[0.16em] text-white">' + (index + 1) + '</div>' +
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

        card.addEventListener('dragover', function (event) {
            if (!dragState.id) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            dragState.targetId = id;
            dragState.position = cardDropPosition(card, event);
            updateDropIndicator(card, dragState.position);
        });

        card.addEventListener('drop', function (event) {
            event.preventDefault();
            event.stopPropagation();
            commitDrop(id, cardDropPosition(card, event));
        });

        return card;
    }

    function renderSelected() {
        selectedList.innerHTML = '';
        publishInputs.innerHTML = '';
        dropzoneEmpty.hidden = selected.length > 0;

        if (selectedCount) {
            selectedCount.textContent = selected.length + ' / ' + limit;
        }

        selected.forEach(function (id, index) {
            var item = items[id];
            if (!item) {
                return;
            }

            selectedList.appendChild(buildSelectedCard(id, index, item));

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
        if (event.target && event.target.closest('.content-selected-card')) {
            return;
        }
        if (dragState.source === 'selected') {
            moveSelected(dragState.id, '', 'after');
        } else if (event.dataTransfer) {
            insertSelected(event.dataTransfer.getData('text/plain'), '', 'after');
        }
        resetDragState();
    });

    selectedList.addEventListener('dragover', function (event) {
        event.preventDefault();
        if (!event.target || event.target.closest('.content-selected-card')) {
            return;
        }
        clearDropIndicators();
        dropzone.classList.add('is-dragover');
        dropzone.classList.add('border-blue-300', 'bg-blue-50');
    });

    selectedList.addEventListener('drop', function (event) {
        if (event.target && event.target.closest('.content-selected-card')) {
            return;
        }
        event.preventDefault();
        if (dragState.source === 'selected') {
            moveSelected(dragState.id, '', 'after');
        } else if (event.dataTransfer) {
            insertSelected(event.dataTransfer.getData('text/plain'), '', 'after');
        }
        resetDragState();
    });

    renderSelected();
});
