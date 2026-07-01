window.confirmDelete = function (id, title, revNo) {
    document.getElementById('deleteDocTitle').textContent = title;
    document.getElementById('deleteRevInfo').textContent = '(Rev ' + (revNo || 0) + ')';
    document.getElementById('deleteForm').action = '/register/' + id;
    document.getElementById('deleteModal').style.display = 'flex';
};

window.closeDeleteModal = function () {
    document.getElementById('deleteModal').style.display = 'none';
};

window.submitDelete = function () {
    const btn = document.querySelector('.upd-modal-confirm');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Deleting...';
        btn.style.opacity = '0.7';
        btn.style.cursor = 'not-allowed';
    }
    document.getElementById('deleteForm').submit();
};

document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) closeDeleteModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDeleteModal();
    });

    const toast = document.getElementById('successToast') || document.getElementById('errorToast');
    if (toast) {
        setTimeout(() => {
            toast.style.animation = 'toastOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
});