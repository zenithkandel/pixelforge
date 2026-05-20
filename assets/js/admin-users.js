(function() {
    const searchInput = document.getElementById('search-input');
    const modal = document.getElementById('action-modal');

    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                const url = new URL(window.location.href);
                url.searchParams.set('search', searchInput.value);
                url.searchParams.delete('page');
                window.location.href = url;
            }, 500);
        });
    }

    document.querySelectorAll('.edit-balance-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const row = e.target.closest('tr');
            const userId = row.dataset.userId;
            const username = row.querySelector('.player-cell a').textContent;
            const currentBalance = row.querySelector('td:nth-child(4)').textContent;

            showEditBalanceModal(userId, username, currentBalance);
        });
    });

    document.querySelectorAll('.toggle-role-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const userId = e.target.dataset.userId;
            const csrf = document.getElementById('csrf-token').value;

            if (!confirm('Change this user\'s role?')) return;

            try {
                const response = await fetch(APP_URL + '/api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=change_role&user_id=${userId}&csrf_token=${encodeURIComponent(csrf)}`
                });

                const data = await response.json();
                if (data.success) {
                    alert(`Role changed to ${data.new_role}`);
                    location.reload();
                } else {
                    alert(data.error);
                }
            } catch (e) {
                alert('Failed to change role');
            }
        });
    });

    document.querySelectorAll('.delete-user-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const userId = e.target.dataset.userId;

            if (!confirm('Delete this user? This will release their pixels.')) return;

            const csrf = document.getElementById('csrf-token').value;

            try {
                const response = await fetch(APP_URL + '/api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_user&user_id=${userId}&csrf_token=${encodeURIComponent(csrf)}`
                });

                const data = await response.json();
                if (data.success) {
                    alert('User deleted');
                    location.reload();
                } else {
                    alert(data.error);
                }
            } catch (e) {
                alert('Failed to delete user');
            }
        });
    });

    document.querySelectorAll('.reset-streak-btn').forEach(btn => {
        btn.addEventListener('click', async (e) => {
            const userId = e.target.dataset.userId;
            const csrf = document.getElementById('csrf-token').value;

            try {
                const response = await fetch(APP_URL + '/api/admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=reset_streak&user_id=${userId}&csrf_token=${encodeURIComponent(csrf)}`
                });

                const data = await response.json();
                if (data.success) {
                    alert('Streak reset');
                    location.reload();
                }
            } catch (e) {
                alert('Failed to reset streak');
            }
        });
    });

    document.querySelectorAll('.pixel-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const userId = e.target.dataset.user;
            window.location.href = APP_URL + '/admin/canvas.php?highlight=' + userId;
        });
    });

    function showEditBalanceModal(userId, username, currentBalance) {
        document.getElementById('modal-title').textContent = `Edit Balance: ${username}`;
        document.getElementById('modal-body').innerHTML = `
            <div style="margin-bottom: 1rem;">
                <label style="display:block;margin-bottom:0.5rem;color:#9ca3af;">New Balance (absolute)</label>
                <input type="number" id="new-balance-input" value="${currentBalance}" min="0" style="width:100%;padding:0.75rem;background:#0a0a0a;border:1px solid #222;border-radius:6px;color:#f5f5f5;">
            </div>
            <div>
                <label style="display:block;margin-bottom:0.5rem;color:#9ca3af;">Add/Subtract Delta</label>
                <input type="number" id="balance-delta-input" value="0" style="width:100%;padding:0.75rem;background:#0a0a0a;border:1px solid #222;border-radius:6px;color:#f5f5f5;">
            </div>
        `;

        modal.classList.remove('hidden');

        document.getElementById('modal-cancel').onclick = () => modal.classList.add('hidden');

        document.getElementById('modal-confirm').onclick = async () => {
            const newBalance = document.getElementById('new-balance-input').value;
            const delta = document.getElementById('balance-delta-input').value;
            const csrf = document.getElementById('csrf-token').value;

            try {
                if (delta !== '0') {
                    const response = await fetch(APP_URL + '/api/admin_action.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=add_balance&user_id=${userId}&delta=${delta}&csrf_token=${encodeURIComponent(csrf)}`
                    });
                } else {
                    const response = await fetch(APP_URL + '/api/admin_action.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `action=update_balance&user_id=${userId}&balance=${newBalance}&csrf_token=${encodeURIComponent(csrf)}`
                    });
                }

                modal.classList.add('hidden');
                alert('Balance updated');
                location.reload();
            } catch (e) {
                alert('Failed to update balance');
            }
        };
    }
})();