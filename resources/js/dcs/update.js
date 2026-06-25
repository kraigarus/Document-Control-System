// Auto-dismiss toasts
document.addEventListener('DOMContentLoaded', function () {
    const toast = document.getElementById('successToast') || document.getElementById('errorToast');
    if (toast) {
        setTimeout(() => {
            toast.style.animation = 'toastOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
});

function confirmDelete(id, title) {
    document.getElementById('deleteDocTitle').textContent = title;
    document.getElementById('deleteForm').action = '/register/' + id;
    document.getElementById('deleteModal').style.display = 'block';
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}