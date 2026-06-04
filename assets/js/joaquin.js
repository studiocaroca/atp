/*!
=========================================================
* Creative Design Landing page
=========================================================

* Copyright: 2019 DevCRUD (https://devcrud.com)
* Licensed: (https://devcrud.com/licenses)
* Coded by www.devcrud.com

=========================================================

* The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
*/ 

// Video background — half speed native playback
document.addEventListener('DOMContentLoaded', function () {
    var video = document.querySelector('.auricular-bg');
    if (video) {
        video.playbackRate = 0.5;
    }
});

// smooth scroll
$(document).ready(function(){
    $(".navbar .nav-link").on('click', function(event) {

        if (this.hash !== "") {

            event.preventDefault();

            var hash = this.hash;

            $('html, body').animate({
                scrollTop: $(hash).offset().top
            }, 700, function(){
                window.location.hash = hash;
            });
        } 
    });
}); 



//modal
document.addEventListener('DOMContentLoaded', function () {
    function openModal(modal) {
        modal.style.display = 'block';
        var iframe = modal.querySelector('iframe[data-src]');
        if (iframe) {
            iframe.setAttribute('src', iframe.getAttribute('data-src'));
        }
    }

    function closeModal(modal) {
        modal.style.display = 'none';
        var iframe = modal.querySelector('iframe[data-src]');
        if (iframe) {
            iframe.setAttribute('src', '');
        }
    }

    // Get all links that open modals
    var links = document.querySelectorAll('.openModalLink');
    links.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) openModal(modal);
        });
    });

    // Get all span elements that close modals
    var spans = document.querySelectorAll('.close');
    spans.forEach(function (span) {
        span.addEventListener('click', function () {
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) closeModal(modal);
        });
    });

    // Close the modal if user clicks outside of it
    window.onclick = function (event) {
        if (event.target.classList.contains('modal')) {
            closeModal(event.target);
        }
    };
});


//translation
document.addEventListener('DOMContentLoaded', function () {
    const langCurrent = document.getElementById('lang-current');
    const langDropdown = document.getElementById('lang-dropdown');

    function updateTranslations(translations, language) {
        const translation = translations[language];
        if (translation) {
            document.querySelectorAll('[data-section][data-translate]').forEach(element => {
                const section = element.getAttribute('data-section');
                const key = element.getAttribute('data-translate');
                if (translation[section] && translation[section][key]) {
                    const value = translation[section][key];
                    if (element.hasAttribute('data-translate-placeholder')) {
                        element.setAttribute('placeholder', value);
                    } else if (element.hasAttribute('data-translate-value')) {
                        element.setAttribute('value', value);
                    } else {
                        element.textContent = value;
                    }
                }
            });
        }
    }

    async function fetchTranslations() {
        try {
            const response = await fetch('translations.json');
            if (!response.ok) {
                throw new Error('Network response was not ok ' + response.statusText);
            }
            const translations = await response.json();

            const allLanguages = ['en', 'es', 'pt'];
            let currentLanguage = 'en';

            function setLanguage(lang) {
                currentLanguage = lang;
                updateTranslations(translations, lang);
                langCurrent.textContent = lang.toUpperCase();
                langDropdown.innerHTML = allLanguages
                    .filter(l => l !== lang)
                    .map(l => `<div class="lang-selector__option" data-language="${l}">${l.toUpperCase()}</div>`)
                    .join('');
            }

            setLanguage('en');

            langCurrent.addEventListener('click', function (e) {
                e.stopPropagation();
                langDropdown.classList.toggle('open');
            });

            langDropdown.addEventListener('click', function (e) {
                const option = e.target.closest('.lang-selector__option');
                if (option) {
                    setLanguage(option.getAttribute('data-language'));
                    langDropdown.classList.remove('open');
                }
            });

            document.addEventListener('click', function () {
                langDropdown.classList.remove('open');
            });

        } catch (error) {
            console.error('There has been a problem with your fetch operation:', error);
        }
    }

    fetchTranslations();
});

// Contact form — AJAX submit via Formspree
(function () {
    const successMessages = {
        en: 'Your message has been sent',
        es: 'Su mensaje ha sido enviado',
        pt: 'Sua mensagem foi enviada'
    };

    const form = document.querySelector('.contact-form');
    const successEl = document.getElementById('form-success');

    if (!form || !successEl) return;

    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const data = new FormData(form);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: data,
                headers: { 'Accept': 'application/json' }
            });

            if (response.ok) {
                const lang = (document.getElementById('lang-current')?.textContent || 'en').toLowerCase();
                successEl.textContent = successMessages[lang] || successMessages.en;
                successEl.style.display = 'block';
                form.reset();
            }
        } catch (err) {
            console.error('Form submission error:', err);
        }
    });
})();
