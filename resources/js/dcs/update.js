// ═══ DELETE MODAL ═══
window.confirmDelete = function (id, title) {
    document.getElementById('deleteDocTitle').textContent = title;
    document.getElementById('deleteForm').action = '/register/' + id;
    document.getElementById('deleteModal').style.display = 'flex';
};

window.closeDeleteModal = function () {
    document.getElementById('deleteModal').style.display = 'none';
};

window.submitDelete = function () {
    document.getElementById('deleteForm').submit();
};

document.addEventListener('DOMContentLoaded', function () {
    // Close modal on overlay click
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) closeDeleteModal();
        });
    }

    // Close modal on Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDeleteModal();
    });

    // Auto-dismiss toasts
    const toast = document.getElementById('successToast') || document.getElementById('errorToast');
    if (toast) {
        setTimeout(() => {
            toast.style.animation = 'toastOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
});