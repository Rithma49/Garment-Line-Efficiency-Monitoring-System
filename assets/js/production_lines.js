const lineModal = new bootstrap.Modal(document.getElementById('lineModal'));

function resetLineForm() {
    document.getElementById('lineForm').reset();
    document.getElementById('line_id').value = '';
    document.getElementById('lineModalTitle').textContent = 'Add Production Line';
}

function editLine(line) {
    document.getElementById('lineModalTitle').textContent = 'Edit Production Line';
    document.getElementById('line_id').value = line.line_id;
    document.getElementById('line_number').value = line.line_number || '';
    document.getElementById('floor').value = line.floor || '';
    document.getElementById('department').value = line.department || '';
    document.getElementById('buyer_id').value = line.buyer_id || '';
    document.getElementById('style_number').value = line.style_number || '';
    document.getElementById('product_name').value = line.product_name || '';
    document.getElementById('supervisor_id').value = line.supervisor_id || '';
    document.getElementById('working_hours').value = line.working_hours || 8;
    document.getElementById('operator_count').value = line.operator_count || 0;
    document.getElementById('machine_count').value = line.machine_count || 0;
    document.getElementById('status').value = line.status || 'idle';
    lineModal.show();
}

document.getElementById('lineForm').addEventListener('submit', function (e) {
    e.preventDefault();
    ajaxSubmit(this, () => {
        lineModal.hide();
        setTimeout(() => location.reload(), 600);
    });
});

function deleteLine(id) {
    if (!confirmDelete('Delete this production line? Related targets and records will also be affected.')) return;

    $.post(
        BASE_URL_JS + '/ajax/production_lines_delete.php',
        { line_id: id, csrf_token: CSRF_TOKEN },
        function (res) {
            showToast(res.success ? 'success' : 'danger', res.message);
            if (res.success) setTimeout(() => location.reload(), 600);
        },
        'json'
    );
}