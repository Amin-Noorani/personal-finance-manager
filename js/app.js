document.addEventListener('DOMContentLoaded', function() {
    // Mobile nav toggle
    var toggle = document.querySelector('.nav-toggle');
    var links = document.querySelector('.nav-links');
    if (toggle && links) {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            links.classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            if (!toggle.contains(e.target) && !links.contains(e.target)) {
                links.classList.remove('show');
            }
        });
    }

    // Persian datepicker initialization
    var datepickers = document.querySelectorAll('.pwt-datepicker-input');
    datepickers.forEach(function(input) {
        var altFieldId = input.getAttribute('data-alt');
        var altField = altFieldId ? document.getElementById(altFieldId) : null;

        // Set initial Jalali value from hidden Gregorian field BEFORE init
        if (altField && altField.value) {
            var parts = altField.value.split('-');
            if (parts.length === 3) {
                var gDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
                var pd = new persianDate(gDate);
                input.value = pd.format('YYYY/MM/DD');
            }
        }

        $(input).pDatepicker({
            calendarType: 'persian',
            format: 'YYYY/MM/DD',
            autoClose: true,
            initialValue: input.value ? true : false,
            calendar: {
                persian: {
                    locale: 'fa'
                }
            },
            toolbox: {
                todayButton: { enabled: true, text: { fa: 'امروز' } },
                submitButton: { enabled: true, text: { fa: 'تایید' } },
                calendarSwitch: { enabled: false }
            },
            onSelect: function(unixDate) {
                if (altField) {
                    var gDate = new Date(unixDate);
                    var gy = gDate.getFullYear();
                    var gm = String(gDate.getMonth() + 1).padStart(2, '0');
                    var gd = String(gDate.getDate()).padStart(2, '0');
                    altField.value = gy + '-' + gm + '-' + gd;
                }
            }
        });
    });

    // Category filter by transaction type
    var typeSelects = document.querySelectorAll('select[name="type"]');
    typeSelects.forEach(function(typeSelect) {
        var catSelect = document.getElementById('category_id');
        if (!catSelect) return;

        function filterCategories() {
            var selectedType = typeSelect.value;
            var options = catSelect.querySelectorAll('option[data-cattype]');
            var currentVal = catSelect.value;

            options.forEach(function(opt) {
                var catType = opt.getAttribute('data-cattype');
                if (catType === selectedType || catType === 'both' || !selectedType) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                    if (opt.value === currentVal) {
                        catSelect.value = '';
                    }
                }
            });
        }

        typeSelect.addEventListener('change', filterCategories);
        filterCategories();
    });
});
