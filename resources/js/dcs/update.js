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

document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('tableBody');
    const emptyState = document.getElementById('emptyState');
    const docCount = document.getElementById('docCount');
    const searchInput = document.getElementById('updSearch');
    const typeFilter = document.getElementById('updTypeFilter');
    const pageInfo = document.getElementById('pageInfo');
    const pageBtns = document.getElementById('pageBtns');

    let currentPage = 1;
    const perPage = 15;

    // ═══ SEARCH ═══
    let searchTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentPage = 1; loadData(); }, 400);
    });

    // ═══ TYPE FILTER ═══
    typeFilter.addEventListener('change', () => {
        currentPage = 1;
        loadData();
    });

    // ═══ RESET ═══
    document.getElementById('resetSearchBtn').addEventListener('click', () => {
        searchInput.value = '';
        typeFilter.value = 'all';
        currentPage = 1;
        loadData();
    });

    // ═══ DELETE MODAL ═══
    const modal = document.getElementById('deleteModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) closeDeleteModal();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeDeleteModal();
    });

    window.confirmDelete = function (requestId, title, revNo) {
        document.getElementById('deleteDocTitle').textContent = title;
        document.getElementById('deleteRevInfo').textContent = '(Rev ' + revNo + ')';
        document.getElementById('deleteForm').action = '/register/' + requestId;
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

    // ═══ BUILD PARAMS ═══
    function buildParams() {
        const p = { page: currentPage, per_page: perPage };
        const search = searchInput.value.trim();
        const typeId = typeFilter.value;
        if (search) p.search = search;
        if (typeId && typeId !== 'all') p.doc_type_id = typeId;
        return p;
    }

    // ═══ LOAD DATA ═══
    async function loadData() {
        tableBody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:40px;color:#94a3b8;">Loading documents...</td></tr>';
        emptyState.style.display = 'none';
        document.querySelector('.upd-table-scroll').style.display = '';

        try {
            const params = buildParams();
            const qs = new URLSearchParams(params).toString();
            // This URL now hits the separate data endpoint
            const res = await fetch('/register/update/data?' + qs);

            if (!res.ok) {
                console.error('Server error:', res.status);
                tableBody.innerHTML = '';
                showEmpty('Server error (' + res.status + ')', true);
                return;
            }

            const json = await res.json();

            docCount.textContent = json.total + ' Document' + (json.total !== 1 ? 's' : '');

            if (!json.data || json.data.length === 0) {
                showEmpty('No documents found.', false);
                renderPagination(json);
                return;
            }

            document.querySelector('.upd-table-scroll').style.display = '';
            emptyState.style.display = 'none';
            renderRows(json.data);
            renderPagination(json);

        } catch (e) {
            console.error('Load error:', e);
            tableBody.innerHTML = '';
            showEmpty('Failed to load data.', true);
        }
    }

    function showEmpty(message, isError) {
        document.querySelector('.upd-table-scroll').style.display = 'none';
        emptyState.style.display = '';
        emptyState.innerHTML =
            '<i class="fa-solid ' + (isError ? 'fa-triangle-exclamation' : 'fa-folder-open') + '"></i>' +
            '<p>' + esc(message) + '</p>';
    }

    // ═══ RENDER ROWS ═══
    function renderRows(data) {
        const offset = (currentPage - 1) * perPage;

        tableBody.innerHTML = data.map((doc, i) => {
            const num = offset + i + 1;
            const checklistTags = doc.checklists.map(cl =>
                '<span class="upd-checklist-tag">' + esc(cl) + '</span>'
            ).join('');

            const historyBtn = doc.history_url
                ? '<a href="' + esc(doc.history_url) + '" class="upd-btn-icon" title="Revision History"><i class="fa-solid fa-clock-rotate-left"></i></a>'
                : '';

            const safeTitle = esc(doc.title).replace(/'/g, "\\'");

            return '<tr>' +
                '<td class="upd-id">#' + doc.request_id + '</td>' +
                '<td><span class="upd-type-badge">' + esc(doc.doc_type) + '</span></td>' +
                '<td class="upd-doc-title" title="' + esc(doc.title) + '">' + esc(doc.title) + '</td>' +
                '<td class="upd-doc-no">' + esc(doc.doc_no) + '</td>' +
                '<td><span class="upd-rev-badge">' + doc.rev_no + '</span></td>' +
                '<td><div class="upd-status-checklists">' + checklistTags + '</div></td>' +
                '<td><div class="upd-actions">' +
                    historyBtn +
                    '<a href="' + esc(doc.edit_url) + '" class="upd-btn-icon" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>' +
                    '<button type="button" class="upd-btn-icon danger" title="Delete" onclick="confirmDelete(' + doc.request_id + ', \'' + safeTitle + '\', \'' + doc.rev_no + '\')"><i class="fa-solid fa-trash-can"></i></button>' +
                '</div></td>' +
            '</tr>';
        }).join('');
    }

    // ═══ PAGINATION ═══
    function renderPagination(json) {
        const total = json.total || 0;
        const lastPage = json.last_page || 1;
        const from = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
        const to = Math.min(currentPage * perPage, total);

        pageInfo.innerHTML = 'Showing <strong>' + from + '–' + to + '</strong> of <strong>' + total + '</strong> documents';

        let btns = '<button class="upd-pg" ' + (currentPage <= 1 ? 'disabled' : '') + ' data-page="' + (currentPage - 1) + '">&laquo;</button>';

        const start = Math.max(1, currentPage - 2);
        const end = Math.min(lastPage, currentPage + 2);

        if (start > 1) {
            btns += '<button class="upd-pg" data-page="1">1</button>';
            if (start > 2) btns += '<span class="upd-pg-dots">...</span>';
        }
        for (let p = start; p <= end; p++) {
            btns += '<button class="upd-pg ' + (p === currentPage ? 'upd-pg-active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        if (end < lastPage) {
            if (end < lastPage - 1) btns += '<span class="upd-pg-dots">...</span>';
            btns += '<button class="upd-pg" data-page="' + lastPage + '">' + lastPage + '</button>';
        }
        btns += '<button class="upd-pg" ' + (currentPage >= lastPage ? 'disabled' : '') + ' data-page="' + (currentPage + 1) + '">&raquo;</button>';

        pageBtns.innerHTML = btns;
        pageBtns.querySelectorAll('.upd-pg[data-page]').forEach(b => {
            b.addEventListener('click', () => {
                const p = parseInt(b.dataset.page);
                if (p >= 1 && p <= lastPage) {
                    currentPage = p;
                    loadData();
                    document.querySelector('.upd-table-scroll').scrollTop = 0;
                }
            });
        });
    }

    // ═══ HELPERS ═══
    function esc(str) {
        if (str === null || str === undefined) return '';
        const d = document.createElement('div');
        d.textContent = String(str);
        return d.innerHTML;
    }

    // ═══ TOAST ═══
    (function () {
        const toast = document.getElementById('successToast') || document.getElementById('errorToast');
        if (toast) {
            setTimeout(() => {
                toast.style.animation = 'toastOut 0.3s ease forwards';
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        }
    })();

    // ═══ INIT ═══
    loadData();
});