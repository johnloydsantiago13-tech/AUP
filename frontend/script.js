function showForm(formName) {
    document.querySelectorAll('.form-box').forEach(function (form) {
        form.classList.toggle('active', form.id === formName + 'Box');
    });
}

document.querySelectorAll('[data-form]').forEach(function (link) {
    link.addEventListener('click', function (event) {
        event.preventDefault();
        showForm(link.dataset.form);
    });
});

function editField(field) {
    document.getElementById(field + '-view').style.display = 'none';
    document.getElementById(field + '-edit').style.display = 'block';
}