(function ($) {
    'use strict';
    var english = document.documentElement.lang === 'en';
    $('[data-confirm]').on('submit', function () { return window.confirm(english ? 'Delete this record?' : 'Удалить эту запись?'); });
    $('input[type=file]').on('change', function () {
        var error = '', files = this.files || [];
        for (var i = 0; i < files.length; i++) if (files[i].size > 4194304 || !/^image\/(jpeg|png|webp)$/.test(files[i].type)) error = english ? 'Use JPG, PNG or WebP, up to 4 MB.' : 'Нужен JPG, PNG или WebP до 4 МБ.';
        this.setCustomValidity(error); if (error) this.reportValidity();
    });
    if (window.tinymce && $('.rich-editor').length) {
        tinymce.init({
            selector: '.rich-editor', license_key: 'gpl', height: 370, menubar: false,
            plugins: 'lists link image table code', toolbar: 'undo redo | blocks | bold italic | bullist numlist | link image table | code',
            branding: false, promotion: false, automatic_uploads: true, image_title: true,
            images_file_types: 'jpg,jpeg,png,webp', file_picker_types: 'image',
            images_upload_handler: function (blobInfo) {
                return new Promise(function (resolve, reject) {
                    var data = new FormData(); data.append('file', blobInfo.blob(), blobInfo.filename());
                    $.ajax({url: '/admin/upload?section=' + encodeURIComponent($('.editor-form').data('section')), method: 'POST', data: data, processData: false, contentType: false, headers: {'X-CSRF-Token': $('meta[name=csrf-token]').attr('content')}, dataType: 'json'})
                        .done(function (result) { if (result.location) resolve(result.location); else reject(result.error || 'Upload failed'); })
                        .fail(function (xhr) { reject((xhr.responseJSON || {}).error || 'Upload failed'); });
                });
            },
            setup: function (editor) { editor.on('change input', function () { editor.save(); }); }
        });
        $('.editor-form').on('submit', function (event) {
            var form = this;
            if ($(form).data('editor-ready')) return;
            event.preventDefault();
            Promise.all(tinymce.get().map(function (editor) { return editor.uploadImages(); })).then(function () {
                tinymce.triggerSave();
                var missing = false;
                $('.rich-editor').each(function () { if (!this.value.replace(/<[^>]*>/g, '').trim() && this.value.indexOf('<img') === -1) missing = true; });
                if (missing) { window.alert(english ? 'Fill in both language versions.' : 'Заполните текст на двух языках.'); return; }
                $(form).data('editor-ready', true);
                if (form.checkValidity()) HTMLFormElement.prototype.submit.call(form);
            }).catch(function () { window.alert(english ? 'Image upload failed.' : 'Не удалось загрузить изображение.'); });
        });
    }
}(jQuery));
