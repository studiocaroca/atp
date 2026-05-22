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
    // Get all links that open modals
    var links = document.querySelectorAll('.openModalLink');
    links.forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault(); // Prevent the default link behavior
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
            }
        });
    });

    // Get all span elements that close modals
    var spans = document.querySelectorAll('.close');
    spans.forEach(function (span) {
        span.addEventListener('click', function () {
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Close the modal if user clicks outside of it
    window.onclick = function (event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    };
});


//translation
document.addEventListener('DOMContentLoaded', function () {
    const flagsContainer = document.getElementById('flags');

    function updateTranslations(translations, language) {
        const translation = translations[language];
        if (translation) {
            document.querySelectorAll('[data-section][data-translate]').forEach(element => {
                const section = element.getAttribute('data-section');
                const key = element.getAttribute('data-translate');
                if (translation[section] && translation[section][key]) {
                    element.textContent = translation[section][key];
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

            // Set default language
            const defaultLanguage = flagsContainer.querySelector('.flags__item').getAttribute('data-language');
            updateTranslations(translations, defaultLanguage);

            // Add click event listener to flags container for delegation
            flagsContainer.addEventListener('click', function (event) {
                // Check if the clicked element or its parent has the 'flags__item' class
                const flagsItem = event.target.closest('.flags__item');
                if (flagsItem) {
                    const selectedLanguage = flagsItem.getAttribute('data-language');
                    updateTranslations(translations, selectedLanguage);
                }
            });

        } catch (error) {
            console.error('There has been a problem with your fetch operation:', error);
        }
    }

    fetchTranslations();
});
