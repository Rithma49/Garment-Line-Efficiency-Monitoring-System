(function () {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');

    function isMobile() {
        return window.innerWidth < 992;
    }

    if (toggleBtn && sidebar) {
        if (!isMobile() && localStorage.getItem('glems_sidebar_collapsed') === '1') {
            sidebar.classList.add('collapsed');
        }

        toggleBtn.addEventListener('click', () => {
            if (isMobile()) {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
                localStorage.setItem(
                    'glems_sidebar_collapsed',
                    sidebar.classList.contains('collapsed') ? '1' : '0'
                );
            }
        });
    }

    const themeToggle = document.getElementById('themeToggle');
    const htmlEl = document.documentElement;
    const savedTheme = localStorage.getItem('glems_theme') || 'light';

    htmlEl.setAttribute('data-theme', savedTheme);
    updateThemeIcon(savedTheme);

    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            const next = htmlEl.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            htmlEl.setAttribute('data-theme', next);
            localStorage.setItem('glems_theme', next);
            updateThemeIcon(next);
        });
    }

    function updateThemeIcon(theme) {
        if (!themeToggle) return;
        themeToggle.innerHTML = theme === 'dark'
            ? '<i class="fa-solid fa-sun"></i>'
            : '<i class="fa-solid fa-moon"></i>';
    }

    window.showToast = function (type, message) {
        const iconMap = {
            success: 'circle-check',
            danger: 'circle-exclamation',
            warning: 'triangle-exclamation',
            info: 'circle-info',
        };
        const icon = iconMap[type] || 'circle-info';
        const id = 'toast' + Date.now();

        const html = `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert">
          <div class="d-flex">
            <div class="toast-body"><i class="fa-solid fa-${icon} me-2"></i>${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
          </div>
        </div>`;

        const container = document.getElementById('toastContainer');
        if (!container) return;

        container.insertAdjacentHTML('beforeend', html);
        const toastEl = document.getElementById(id);
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    };

    window.confirmDelete = function (message = 'Are you sure you want to delete this record? This action cannot be undone.') {
        return confirm(message);
    };

    window.ajaxSubmit = function (form, onSuccess) {
        const formData = new FormData(form);

        $.ajax({
            url: form.action,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
        }).done(function (res) {
            showToast(
                res.success ? 'success' : 'danger',
                res.message || (res.success ? 'Saved successfully.' : 'Something went wrong.')
            );
            if (res.success && typeof onSuccess === 'function') onSuccess(res);
        }).fail(function () {
            showToast('danger', 'Request failed. Please check your connection and try again.');
        });
    };

    window.fetchNotificationCount = function () {
        $.get(BASE_URL_JS + '/ajax/notification_count.php', function (res) {
            if (res && res.count !== undefined) {
                const dot = document.getElementById('notifDot');
                const badge = document.getElementById('notifBadge');

                if (res.count > 0) {
                    if (dot) dot.style.display = '';
                    if (badge) badge.textContent = res.count + ' new';
                } else {
                    if (dot) dot.style.display = 'none';
                    if (badge) badge.textContent = '0 new';
                }
            }
        }, 'json');
    };
})();