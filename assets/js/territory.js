let territoryMode = false;
window.territoryMode = false;

function toggleTerritory() {
    territoryMode = !territoryMode;
    window.territoryMode = territoryMode;

    const btn = document.getElementById('territory-toggle');
    btn.textContent = territoryMode ? 'Art View' : 'Territory View';

    if (territoryMode) {
        showTerritoryLegend();
    } else {
        hideTerritoryLegend();
    }

    if (typeof render === 'function') {
        render();
    }
}

function showTerritoryLegend() {
    let legend = document.getElementById('territory-legend');
    if (!legend) {
        legend = document.createElement('div');
        legend.id = 'territory-legend';
        legend.style.cssText = `
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: rgba(17, 17, 17, 0.95);
            border: 1px solid #222;
            border-radius: 8px;
            padding: 1rem;
            z-index: 100;
        `;
        document.body.appendChild(legend);
    }

    const topOwners = getTopOwners();
    legend.innerHTML = '<strong style="color:#7c3aed;margin-bottom:0.5rem;display:block;">Top Pixel Owners</strong>';

    topOwners.forEach((owner, i) => {
        const item = document.createElement('div');
        item.style.cssText = 'display:flex;align-items:center;gap:0.5rem;margin:0.25rem 0;';
        item.innerHTML = `
            <span style="width:16px;height:16px;background:${owner.color};border-radius:3px;"></span>
            <span style="color:#f5f5f5;font-size:0.85rem;">${owner.username}</span>
            <span style="color:#9ca3af;font-size:0.75rem;">(${owner.count})</span>
        `;
        legend.appendChild(item);
    });
}

function hideTerritoryLegend() {
    const legend = document.getElementById('territory-legend');
    if (legend) legend.remove();
}

function getTopOwners() {
    const counts = {};
    Object.values(pixels).forEach(p => {
        if (p.owner_id) {
            const key = p.username || 'Unknown';
            if (!counts[key]) {
                counts[key] = { username: key, color: p.color, count: 0 };
            }
            counts[key].count++;
        }
    });

    return Object.values(counts)
        .sort((a, b) => b.count - a.count)
        .slice(0, 5);
}