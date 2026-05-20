function openUserModal(id, username, balance, role) {
    document.getElementById('modal-user-id').value = id;
    document.getElementById('modal-username').textContent = username;
    document.getElementById('modal-balance').value = balance;
    document.getElementById('modal-role').value = role;
    document.getElementById('user-modal').style.display = 'flex';
}

function setAction(action) {
    document.getElementById('modal-action').value = action;
    document.getElementById('modal-form').submit();
}