(function() {
    const multiSelectBtn = document.getElementById('multi-select-btn');
    const eraseSelectedBtn = document.getElementById('erase-selected-btn');
    const areaSelectBtn = document.getElementById('area-select-btn');
    const resetCanvasBtn = document.getElementById('reset-canvas-btn');
    const fillCanvasBtn = document.getElementById('fill-canvas-btn');

    let multiSelectMode = false;
    let areaSelectMode = false;
    let selectedPixels = [];
    let isSelecting = false;
    let selectionStart = null;

    if (multiSelectBtn) {
        multiSelectBtn.addEventListener('click', () => {
            multiSelectMode = !multiSelectMode;
            areaSelectMode = false;
            multiSelectBtn.classList.toggle('active', multiSelectMode);
            eraseSelectedBtn.classList.toggle('hidden', selectedPixels.length === 0);
            canvas.style.cursor = multiSelectMode ? 'crosshair' : 'default';
        });
    }

    if (areaSelectBtn) {
        areaSelectBtn.addEventListener('click', () => {
            areaSelectMode = !areaSelectMode;
            multiSelectMode = false;
            areaSelectBtn.classList.toggle('active', areaSelectMode);
            canvas.style.cursor = areaSelectMode ? 'crosshair' : 'default';
        });
    }

    canvas.addEventListener('click', (e) => {
        if (!IS_ADMIN) return;

        const rect = canvas.getBoundingClientRect();
        const scale = zoom;
        const x = Math.floor((e.clientX - rect.left - panX) / scale / CELL_SIZE);
        const y = Math.floor((e.clientY - rect.top - panY) / scale / CELL_SIZE);

        if (x < 0 || x >= GRID_SIZE || y < 0 || y >= GRID_SIZE) return;

        if (multiSelectMode) {
            const key = `${x},${y}`;
            if (selectedPixels.some(p => p.x === x && p.y === y)) {
                selectedPixels = selectedPixels.filter(p => !(p.x === x && p.y === y));
                removePixelHighlight(x, y);
            } else {
                selectedPixels.push({ x, y });
                addPixelHighlight(x, y);
            }
            eraseSelectedBtn.textContent = `Erase Selected (${selectedPixels.length})`;
            eraseSelectedBtn.classList.toggle('hidden', selectedPixels.length === 0);
        }
    });

    canvas.addEventListener('mousedown', (e) => {
        if (!areaSelectMode || !IS_ADMIN) return;

        const rect = canvas.getBoundingClientRect();
        const scale = zoom;
        selectionStart = {
            x: Math.floor((e.clientX - rect.left - panX) / scale / CELL_SIZE),
            y: Math.floor((e.clientY - rect.top - panY) / scale / CELL_SIZE)
        };
        isSelecting = true;
    });

    canvas.addEventListener('mousemove', (e) => {
        if (!isSelecting || !areaSelectMode) return;

        const rect = canvas.getBoundingClientRect();
        const scale = zoom;
        const x = Math.floor((e.clientX - rect.left - panX) / scale / CELL_SIZE);
        const y = Math.floor((e.clientY - rect.top - panY) / scale / CELL_SIZE);

        drawSelectionBox(selectionStart, { x, y });
    });

    canvas.addEventListener('mouseup', (e) => {
        if (!isSelecting || !areaSelectMode) return;

        const rect = canvas.getBoundingClientRect();
        const scale = zoom;
        const endX = Math.floor((e.clientX - rect.left - panX) / scale / CELL_SIZE);
        const endY = Math.floor((e.clientY - rect.top - panY) / scale / CELL_SIZE);

        const minX = Math.min(selectionStart.x, endX);
        const maxX = Math.max(selectionStart.x, endX);
        const minY = Math.min(selectionStart.y, endY);
        const maxY = Math.max(selectionStart.y, endY);

        for (let x = minX; x <= maxX; x++) {
            for (let y = minY; y <= maxY; y++) {
                if (!selectedPixels.some(p => p.x === x && p.y === y)) {
                    selectedPixels.push({ x, y });
                    addPixelHighlight(x, y);
                }
            }
        }

        eraseSelectedBtn.textContent = `Erase Selected (${selectedPixels.length})`;
        eraseSelectedBtn.classList.toggle('hidden', selectedPixels.length === 0);

        isSelecting = false;
        selectionStart = null;
    });

    function addPixelHighlight(x, y) {
        ctx.save();
        ctx.strokeStyle = '#ef4444';
        ctx.lineWidth = 2;
        ctx.strokeRect(x * CELL_SIZE, y * CELL_SIZE, CELL_SIZE, CELL_SIZE);
        ctx.restore();
    }

    function removePixelHighlight(x, y) {
        render();
    }

    function drawSelectionBox(start, end) {
        render();
        ctx.save();
        ctx.strokeStyle = '#ef4444';
        ctx.lineWidth = 2;
        ctx.setLineDash([5, 5]);

        const x = Math.min(start.x, end.x) * CELL_SIZE;
        const y = Math.min(start.y, end.y) * CELL_SIZE;
        const w = (Math.abs(end.x - start.x) + 1) * CELL_SIZE;
        const h = (Math.abs(end.y - start.y) + 1) * CELL_SIZE;

        ctx.strokeRect(x, y, w, h);
        ctx.restore();
    }

    if (eraseSelectedBtn) {
        eraseSelectedBtn.addEventListener('click', async () => {
            if (selectedPixels.length === 0) return;

            const csrf = document.getElementById('csrf-token').value;

            try {
                const response = await fetch(APP_URL + '/api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=erase_pixels&pixels=${encodeURIComponent(JSON.stringify(selectedPixels))}&csrf_token=${encodeURIComponent(csrf)}`
                });

                const data = await response.json();
                if (data.success) {
                    selectedPixels.forEach(p => {
                        delete pixels[`${p.x},${p.y}`];
                    });
                    selectedPixels = [];
                    render();
                    updateMinimap();
                    eraseSelectedBtn.classList.add('hidden');
                    multiSelectMode = false;
                    multiSelectBtn.classList.remove('active');
                }
            } catch (e) {
                alert('Failed to erase pixels');
            }
        });
    }

    if (resetCanvasBtn) {
        resetCanvasBtn.addEventListener('click', () => {
            showModal('Reset Canvas', 'Type RESET to confirm full canvas reset', 'RESET', async () => {
                const csrf = document.getElementById('csrf-token').value;
                const response = await fetch(APP_URL + '/api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=reset_canvas&csrf_token=${encodeURIComponent(csrf)}`
                });
                const data = await response.json();
                if (data.success) {
                    pixels = {};
                    render();
                    updateMinimap();
                    alert('Canvas reset complete');
                }
            });
        });
    }

    if (fillCanvasBtn) {
        fillCanvasBtn.addEventListener('click', () => {
            showModal('Fill Unclaimed', 'This will fill all unclaimed pixels. Continue?', null, async () => {
                const color = document.getElementById('modal-input')?.value || '#7c3aed';
                const csrf = document.getElementById('csrf-token').value;
                const response = await fetch(APP_URL + '/api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=fill_canvas&color=${encodeURIComponent(color)}&csrf_token=${encodeURIComponent(csrf)}`
                });
                const data = await response.json();
                if (data.success) {
                    alert(`Filled ${data.filled} pixels`);
                    loadPixels();
                }
            });
        });
    }

    function showModal(title, message, confirmText, onConfirm) {
        const modal = document.getElementById('confirm-modal');
        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-message').textContent = message;

        const inputContainer = document.getElementById('modal-input-container');
        const input = document.getElementById('modal-input');

        if (confirmText) {
            inputContainer.classList.remove('hidden');
            input.value = '';
            input.placeholder = `Type ${confirmText}`;
        } else {
            inputContainer.classList.add('hidden');
        }

        modal.classList.remove('hidden');

        document.getElementById('modal-cancel').onclick = () => modal.classList.add('hidden');
        document.getElementById('modal-confirm').onclick = () => {
            if (confirmText && input.value !== confirmText) {
                input.style.borderColor = '#ef4444';
                return;
            }
            modal.classList.add('hidden');
            onConfirm();
        };
    }
})();