(function ($) {
    'use strict';
    var english = document.documentElement.lang === 'en';
    $('[data-slideshow]').each(function () {
        var root = $(this), slides = root.find('.slide'), index = 0, timer, paused = false;
        function show(next) {
            index = (next + slides.length) % slides.length;
            slides.prop('hidden', true).removeClass('active');
            slides.eq(index).prop('hidden', false).addClass('active');
            root.find('[data-slide-count]').text(('0' + (index + 1)).slice(-2) + ' / ' + ('0' + slides.length).slice(-2));
        }
        function stop() { clearInterval(timer); }
        function start() { stop(); if (!paused && slides.length > 1 && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) timer = setInterval(function () { show(index + 1); }, 5500); }
        root.find('[data-next]').on('click', function () { show(index + 1); start(); });
        root.find('[data-prev]').on('click', function () { show(index - 1); start(); });
        root.find('[data-pause]').on('click', function () { paused = !paused; $(this).text(paused ? '▶' : 'Ⅱ').attr('aria-label', paused ? (english ? 'Play slideshow' : 'Продолжить слайдшоу') : (english ? 'Pause slideshow' : 'Остановить слайдшоу')); start(); });
        root.on('mouseenter focusin', stop).on('mouseleave focusout', start); start();
    });
    var form = $('[data-order-form]'), request, debounce;
    if (form.length) {
        function quote() {
            form.find('[data-address]').prop('required', form.find('[data-delivery]').prop('checked'));
            var selected = false;
            form.find('input[type=number]').each(function () { if (Number(this.value) > 0) selected = true; });
            if (request) request.abort();
            if (!selected) { form.find('[data-total]').text(english ? 'Select items' : 'Выберите товары'); form.find('[data-quote-message]').text(''); return; }
            request = $.ajax({url: '/order/quote', method: 'POST', data: form.serialize(), dataType: 'json'}).done(function (data) {
                form.find('[data-total]').text(data.formatted);
                form.find('[data-quote-message]').text(english ? 'Includes selected delivery option.' : 'С учётом выбранного способа получения.');
            }).fail(function (xhr, status) {
                if (status === 'abort') return;
                form.find('[data-total]').text('—');
                form.find('[data-quote-message]').text((xhr.responseJSON || {}).error || (english ? 'Unable to calculate. Try again.' : 'Не удалось рассчитать. Попробуйте ещё раз.'));
            });
        }
        form.on('input change', 'input[type=number], [data-delivery]', function () { clearTimeout(debounce); debounce = setTimeout(quote, 200); });
        form.on('submit', function () { if (this.checkValidity()) $(this).find('button[type=submit]').prop('disabled', true); });
        quote();
    }
}(jQuery));
