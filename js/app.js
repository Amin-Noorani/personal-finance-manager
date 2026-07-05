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

    // --- SMS Transaction Modal ---
    var smsModal = document.getElementById('smsModal');
    var openSmsBtn = document.getElementById('openSmsModal');
    var closeSmsBtn = document.getElementById('closeSmsModal');
    var processSmsBtn = document.getElementById('processSms');
    var submitSmsBtn = document.getElementById('submitSmsTransaction');
    var resetSmsBtn = document.getElementById('resetSmsForm');
    var smsResults = document.getElementById('smsResults');

    function resetSmsModal() {
        document.getElementById('sms_text').value = '';
        document.getElementById('sms_account_id').value = '';
        document.getElementById('sms_type').value = 'expense';
        document.getElementById('sms_category_id').value = '';
        document.getElementById('sms_tag_id').value = '';
        var smsJalaliInput = document.getElementById('sms_jalali_date');
        var smsGregInput = document.getElementById('sms_parsed_date');
        if (smsJalaliInput) smsJalaliInput.value = '';
        if (smsGregInput) smsGregInput.value = '';
        smsResults.style.display = 'none';
        if (smsTypeSelect) filterSmsCategories();
    }

    if (openSmsBtn && smsModal) {
        openSmsBtn.addEventListener('click', function() {
            resetSmsModal();
            smsModal.style.display = 'flex';
        });
    }
    if (closeSmsBtn && smsModal) {
        closeSmsBtn.addEventListener('click', function() {
            resetSmsModal();
            smsModal.style.display = 'none';
        });
    }
    if (smsModal) {
        smsModal.addEventListener('click', function(e) {
            if (e.target === smsModal) {
                resetSmsModal();
                smsModal.style.display = 'none';
            }
        });
    }

    // SMS category filter by type
    var smsTypeSelect = document.getElementById('sms_type');
    var smsCatSelect = document.getElementById('sms_category_id');
    if (smsTypeSelect && smsCatSelect) {
        function filterSmsCategories() {
            var selectedType = smsTypeSelect.value;
            var options = smsCatSelect.querySelectorAll('option[data-cattype]');
            var currentVal = smsCatSelect.value;
            options.forEach(function(opt) {
                var catType = opt.getAttribute('data-cattype');
                if (catType === selectedType || catType === 'both' || !selectedType) {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                    if (opt.value === currentVal) {
                        smsCatSelect.value = '';
                    }
                }
            });
        }
        smsTypeSelect.addEventListener('change', filterSmsCategories);
        filterSmsCategories();
    }

    // Parse SMS text
    if (processSmsBtn) {
        processSmsBtn.addEventListener('click', function() {
            var text = document.getElementById('sms_text').value;
            if (!text.trim()) {
                alert('لطفاً متن پیامک را وارد کنید.');
                return;
            }
            var parsed = parseBankSms(text);
            if (!parsed) {
                alert('پیامک قابل پردازش نیست. لطفاً متن پیامک بانکی صحیح را وارد کنید.');
                return;
            }
            document.getElementById('sms_parsed_amount').value = parsed.amount || '';
            document.getElementById('sms_parsed_type').value = parsed.type || 'expense';
            // Set Jalali display field and hidden Gregorian field
            var smsJalaliInput = document.getElementById('sms_jalali_date');
            var smsGregInput = document.getElementById('sms_parsed_date');
            if (smsJalaliInput) smsJalaliInput.value = parsed.jalaliDate || '';
            if (smsGregInput) smsGregInput.value = parsed.gregorianDate || '';
            document.getElementById('sms_parsed_time').value = parsed.time || '';
            document.getElementById('sms_parsed_description').value = parsed.description || '';
            smsTypeSelect.value = parsed.type || 'expense';
            filterSmsCategories();
            smsResults.style.display = 'block';
        });
    }

    // Reset SMS form
    if (resetSmsBtn) {
        resetSmsBtn.addEventListener('click', function() {
            resetSmsModal();
        });
    }

    // Submit SMS transaction via AJAX
    if (submitSmsBtn) {
        submitSmsBtn.addEventListener('click', function() {
            var accountId = document.getElementById('sms_account_id').value;
            var amount = document.getElementById('sms_parsed_amount').value;
            var type = document.getElementById('sms_parsed_type').value;
            var date = document.getElementById('sms_parsed_date').value;
            var time = document.getElementById('sms_parsed_time').value || '00:00:00';
            var categoryId = document.getElementById('sms_category_id').value;
            var tagId = document.getElementById('sms_tag_id').value;
            var description = document.getElementById('sms_parsed_description').value;

            if (!accountId) {
                alert('لطفاً حساب را انتخاب کنید.');
                return;
            }
            if (!amount || amount <= 0) {
                alert('مبلغ نامعتبر است.');
                return;
            }
            if (!date) {
                alert('تاریخ نامعتبر است.');
                return;
            }

            var csrfToken = document.querySelector('#smsModal input[name="csrf_token"]');
            if (!csrfToken) {
                // Create a hidden input to hold CSRF token
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = document.querySelector('form input[name="csrf_token"]').value;
                document.getElementById('smsModal').appendChild(input);
                csrfToken = input;
            }

            var formData = new FormData();
            formData.append('csrf_token', csrfToken.value);
            formData.append('action', 'add');
            formData.append('type', type);
            formData.append('amount', amount);
            formData.append('date', date);
            formData.append('time', time);
            formData.append('account_id', accountId);
            formData.append('category_id', categoryId || '');
            formData.append('tag_id', tagId || '');
            formData.append('description', description);

            fetch('/pfm/transactions.php', {
                method: 'POST',
                body: formData
            }).then(function(response) {
                return response.text();
            }).then(function(html) {
                // Check for error in response
                var errMatch = html.match(/class="alert alert-error"[^>]*>([^<]+)/);
                if (errMatch) {
                    alert(errMatch[1]);
                } else {
                    // No error found — assume success
                    smsModal.style.display = 'none';
                    window.location.reload();
                }
            }).catch(function() {
                alert('خطا در ارتباط با سرور.');
            });
        });
    }

    function parseBankSms(text) {
        // Normalize: convert Arabic chars to Persian equivalents so regex matches
        // Arabic ي (U+064A) → Persian ی (U+06CC)
        // Arabic ك (U+0643) → Persian ک (U+06A9)
        // Arabic ة (U+0629) → Persian ه (U+0647)
        // Arabic أ (U+0623) → Persian ا (U+0627)
        var normalized = text
            .replace(/\u064A/g, '\u06CC')
            .replace(/\u0643/g, '\u06A9')
            .replace(/\u0629/g, '\u0647')
            .replace(/\u0623/g, '\u0627');

        // Convert Persian digits to English
        var persianNums = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        var engNums = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        for (var i = 0; i < persianNums.length; i++) {
            normalized = normalized.split(persianNums[i]).join(engNums[i]);
        }

        var result = { amount: 0, type: 'expense', jalaliDate: '', gregorianDate: '', time: '', description: '' };

        // Determine transaction type from keywords
        var expenseKeywords = ['برداشت', 'خرید', 'کارت به کارت به', 'پرداخت', 'انتقال'];
        var incomeKeywords = ['واریز', 'کارت به کارت از', 'سود'];
        var detectedType = 'expense';

        for (var k = 0; k < expenseKeywords.length; k++) {
            if (normalized.indexOf(expenseKeywords[k]) !== -1) {
                detectedType = 'expense';
                break;
            }
        }
        for (var k = 0; k < incomeKeywords.length; k++) {
            if (normalized.indexOf(incomeKeywords[k]) !== -1) {
                detectedType = 'income';
                break;
            }
        }
        result.type = detectedType;

        // Extract amount - handle both formats:
        // Old: "خرید: 3,350,000" (Toman)
        // New: "برداشت:3,550,000-" (Rial, with trailing minus)
        var amount = 0;
        var amountMatch = normalized.match(/(?:برداشت|خرید|انتقال|واریز|پرداخت|سود|کارت\s*به\s*کارت\s*(?:از|به)?)\s*:\s*([\d,]+)\-?/);
        if (!amountMatch) {
            amountMatch = normalized.match(/(?:مبلغ|مقدار)\s*:\s*([\d,]+)\-?/);
        }
        if (amountMatch) {
            amount = parseInt(amountMatch[1].replace(/,/g, ''), 10);
        }

        // Detect if amount is in Rial (new format with MMDD-HH:MM at end)
        // If the message has the compact date format at the end, amount is in Rial
        var isRialFormat = /\d{4}-\d{2}:\d{2}\s*$/.test(normalized);
        if (isRialFormat && amount > 0) {
            amount = Math.round(amount / 10);
        }
        result.amount = amount;

        // Extract date - try new format first: MMDD-HH:MM at end of message
        // e.g., "0413-20:19" = month 4, day 13, time 20:19
        var newDateMatch = normalized.match(/(\d{2})(\d{2})-(\d{2}):(\d{2})\s*$/);
        if (newDateMatch) {
            var jm = parseInt(newDateMatch[1], 10);
            var jd = parseInt(newDateMatch[2], 10);
            var hh = newDateMatch[3];
            var mm = newDateMatch[4];
            // Default year: current Jalali year (extracted from today)
            var now = new Date();
            var jToday = gregorianToJalaliJS(now.getFullYear(), now.getMonth() + 1, now.getDate());
            var jy = jToday[0];
            result.jalaliDate = jy + '/' + String(jm).padStart(2, '0') + '/' + String(jd).padStart(2, '0');
            result.gregorianDate = jalaliToGregorianJS(jy, jm, jd);
            result.time = hh + ':' + mm + ':00';
        } else {
            // Old format: تاریخ: YYYY/MM/DD and ساعت: HH:MM:SS
            var dateMatch = normalized.match(/تاریخ\s*:\s*(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/);
            if (dateMatch) {
                var jy2 = parseInt(dateMatch[1], 10);
                var jm2 = parseInt(dateMatch[2], 10);
                var jd2 = parseInt(dateMatch[3], 10);
                result.jalaliDate = jy2 + '/' + String(jm2).padStart(2, '0') + '/' + String(jd2).padStart(2, '0');
                result.gregorianDate = jalaliToGregorianJS(jy2, jm2, jd2);
            }
            var timeMatch = normalized.match(/ساعت\s*:\s*(\d{1,2}):(\d{1,2}):(\d{1,2})/);
            if (timeMatch) {
                result.time = String(parseInt(timeMatch[1], 10)).padStart(2, '0') + ':' +
                              String(parseInt(timeMatch[2], 10)).padStart(2, '0') + ':' +
                              String(parseInt(timeMatch[3], 10)).padStart(2, '0');
            }
        }

        // Build description from bank name and card info
        var lines = text.split('\n');
        var descParts = [];
        for (var l = 0; l < lines.length; l++) {
            var line = lines[l].trim();
            if (line.indexOf('بانک') !== -1 || line.indexOf('حساب') !== -1) {
                descParts.push(line);
            }
        }
        result.description = descParts.join(' - ');

        if (result.amount === 0) return null;
        console.log(result);
        
        return result;
    }

    function gregorianToJalaliJS(gy, gm, gd) {
        // Port of JDF gregorian_to_jalali
        var g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        if (gy > 1600) {
            var jy = 979;
            gy -= 1600;
        } else {
            var jy = 0;
            gy -= 621;
        }
        var gy2 = (gm > 2) ? (gy + 1) : gy;
        var days = (365 * gy) + Math.floor((gy2 + 3) / 4) - Math.floor((gy2 + 99) / 100) + Math.floor((gy2 + 399) / 400) - 80 + gd + g_d_m[gm - 1];
        jy += 33 * Math.floor(days / 12053);
        days %= 12053;
        jy += 4 * Math.floor(days / 1461);
        days %= 1461;
        jy += Math.floor((days - 1) / 365);
        if (days > 365) days = (days - 1) % 365;
        var jm, jd2;
        if (days < 186) {
            jm = 1 + Math.floor(days / 31);
            jd2 = 1 + (days % 31);
        } else {
            jm = 7 + Math.floor((days - 186) / 30);
            jd2 = 1 + ((days - 186) % 30);
        }
        return [jy, jm, jd2];
    }

    function jalaliToGregorianJS(jy, jm, jd) {
        // Exact port of JDF jalali_to_gregorian (jalalidate/jdf)
        if (jy > 979) {
            var gy = 1600;
            jy -= 979;
        } else {
            var gy = 621;
        }
        var days = (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor((jy % 33 + 3) / 4) + 78 + jd + ((jm < 7) ? (jm - 1) * 31 : ((jm - 7) * 30) + 186);
        gy += 400 * Math.floor(days / 146097);
        days %= 146097;
        if (days > 36524) {
            days--;
            gy += 100 * Math.floor(days / 36524);
            days %= 36524;
            if (days >= 365) days++;
        }
        gy += 4 * Math.floor(days / 1461);
        days %= 1461;
        gy += Math.floor((days - 1) / 365);
        if (days > 365) days = (days - 1) % 365;
        var gd = days + 1;
        var monthDays = [0, 31, ((gy % 4 === 0 && gy % 100 !== 0) || gy % 400 === 0) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        var gm = 0;
        for (gm = 0; gm < monthDays.length; gm++) {
            if (gd <= monthDays[gm]) break;
            gd -= monthDays[gm];
        }
        return gy + '-' + String(gm).padStart(2, '0') + '-' + String(gd).padStart(2, '0');
    }

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
