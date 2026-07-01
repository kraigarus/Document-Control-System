document.addEventListener('DOMContentLoaded', function () {
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            window.location.href = '/register/update';
        }
    });
});