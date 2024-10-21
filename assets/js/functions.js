$(document).ready(function () {
    if ($(window).width() > 768) {
        $('.addEfect').each(function () {
            if ($(this).find('> *').length > 0) {
                $(this).find('> *').addClass('esconder');
            } else {
                $(this).addClass('esconder');
            }
            observer.observe($(this)[0]);
        });
    }

    initializeSearch();
});

function initializeSearch() {
    $('#query').parents('form').on('submit', function(e) {
        e.preventDefault()
    });
    $('#result').hide();
    $.ajaxSetup({cache: false});
    $('#query').keyup($.debounce(500, function () {
        var searchField = $('#query').val();
        if (searchField.length > 3) {
            $('#result').html('');
            $('#state').val('');
            var expression = new RegExp(searchField, "i");
            $.getJSON('assets/data/search.json', function (data) {
                $.each(data, function (key, value) {
                    if (value.palavrasChave.search(expression) != -1 || value.palavrasChave.search(expression) != -1) {

                        let text = value.titulo;
                        if (value.icone.length > 0) {
                            text = `<i class="${value.icone}"></i> ${text}`;
                        }
                        let linkInit = '';
                        let linkFin = '';
                        switch (value.tipoLink) {
                            case 'link':
                                linkInit = `<a href="${value.link}">`;
                                linkFin = `</a>`;
                                break;
                            case 'link_blank':
                                linkInit = `<a href="${value.link}" target="_blank">`;
                                linkFin = `</a>`;
                                break;
                        }

                        $('#result').append(`<li>${linkInit}${text}${linkFin}</li>`);
                    }
                });
                $('#result').fadeIn();
            });
        } else{
            $('#result').fadeOut();
        }
    }));
}


//efeito
const effect = function (wrapper, entryTarget) {
    if (wrapper.isIntersecting) {
        setTimeout(function () {
            $(entryTarget).removeClass('esconder');
            $(entryTarget).addClass('animate__animated animate__bounceIn');
        }, 250);
        return;
    }

    $(entryTarget).addClass('esconder');
    $(entryTarget).removeClass('animate__animated animate__bounceIn');
}

const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
        var items = $(entry.target).find('> *');
        if ($(items).length == 0) {
            effect(entry, entry.target);
        } else {

            $(items).each(function () {
                effect(entry, this);
            });
        }
    });
});
//efeito