// resources/js/alpine/auth-modal.js
const getCsrf = async () => {
    if (typeof window.ensureCsrfToken === 'function') {
        return await window.ensureCsrfToken();
    }

    return document.querySelector('meta[name="csrf-token"]')?.content || '';
};

export default function authModal(opts = {}) {
    // локальный словарь, пришёл из Blade: { 'auth.login': 'Увійти', ... }
    const I18N = opts.i18n || {};
    // Метод авторизации: 'phone_sms' или 'phone_password_sms'
    const authMethod = (opts.authMethod ?? 'phone_password_sms').toString().trim();


    // простая функция подстановки с плейсхолдерами :name / :sec
    const t = (key, fallbackOrParams = {}) => {
        // Если второй параметр - строка, это fallback значение
        const isFallback = typeof fallbackOrParams === 'string';
        const params = isFallback ? {} : fallbackOrParams;
        const fallback = isFallback ? fallbackOrParams : null;

        let s = I18N[key];

        // Если ключ не найден, используем fallback или сам ключ
        if (s === undefined || s === null) {
            s = fallback || key;
        }

        if (typeof s === 'string') {
            for (const [k,v] of Object.entries(params)) {
                s = s.replace(new RegExp(':'+k+'\\b','g'), String(v));
            }
        }
        return s;
    };
    return {
        // ======== ЕДИНОЕ определение authModal ========
        // tabs / ui
        open: false,
        countryDropdownOpen: false, // для dropdown выбора страны
        authMethod: authMethod, // 'phone_sms' или 'phone_password_sms'
        PREFIX: '+380 ', // Префикс номера телефона
        get PREFIX_LEN() { return this.PREFIX.length; }, // Длина префикса
        get isPhoneSms() {
            return this.authMethod === 'phone_sms';
        },

        init() {
            // console.log('authModal init', {
            //     raw: opts.authMethod,
            //     normalized: this.authMethod,
            //     isPhoneSms: this.isPhoneSms,
            // });
        },
        successMessage: '',
        smsRefs:    [],   // refs для SMS кода (4 инпута)
        forgotRefs: [],   // refs для "забыли пароль" (4 инпута)
        sending:   false,   // для отправки кода (send-code)
        verifying: false,   // для подтверждения кода (verify)
        tab: 'login', // login | register | sms
        forgot: { phonePretty:'', phoneDigits:'', resendIn:0, ttl:0 },
        forgotOtp: ['', '', '', ''],
        forgotError: null,
        otpRefs: [],  // <— тут складатимуться посилання на інпути
        sms: {
            phonePretty: '',   // как ввёл пользователь (для текста)
            phoneDigits: '',   // нормализованный 380XXXXXXXXX — ИМЕННО ЕГО шлём на verify
            phoneFormatted: '', // отформатированный номер для отображения: +380 (50) 715-27-68
            resendIn: 0,
            ttl: 0,
        },

        otp: ['', '', '', ''],
        otpError: null, // ошибка при вводе OTP
        authSuccess: false, // успешная авторизация

        // Данные для логина только по телефону + SMS
        loginPhoneSmsData: { 
            countryCode: '+380',
            countryFlag: '<svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="9" fill="#005BBB"/><rect y="9" width="24" height="9" fill="#FFD700"/></svg>',
            phoneNumber: '',
            phone: '' // полный номер (будет формироваться из countryCode + phoneNumber)
        },
        loginPhoneSmsLoading: false,
        loginPhoneSmsError: null,
        loginPhoneSmsSent: false,

        // Список стран с кодами и флагами
        phoneCountries: [
            {
                code: '+380',
                name: 'Україна',
                flag: '<svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="9" fill="#005BBB"/><rect y="9" width="24" height="9" fill="#FFD700"/></svg>'
            },
            {
                code: '+1',
                name: 'США / Канада',
                flag: '<svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="18" fill="#b22234"/><rect width="10" height="8" fill="#3c3b6e"/></svg>'
            },
            {
                code: '+44',
                name: 'Великобритания',
                flag: '<svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="18" fill="#012169"/></svg>'
            },
            {
                code: '+49',
                name: 'Германия',
                flag: '<svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="6" fill="#000000"/><rect y="6" width="24" height="6" fill="#DD0000"/><rect y="12" width="24" height="6" fill="#FFCE00"/></svg>'
            },
            {
                code: '+33',
                name: 'Франция',
                flag: '<svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="8" height="18" fill="#0055A4"/><rect x="8" width="8" height="18" fill="#FFFFFF"/><rect x="16" width="8" height="18" fill="#EF4135"/></svg>'
            },
            {
                code: '+39',
                name: 'Италия',
                flag: '<svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="8" height="18" fill="#009246"/><rect x="8" width="8" height="18" fill="#FFFFFF"/><rect x="16" width="8" height="18" fill="#CE2B37"/></svg>'
            },
            {
                code: '+34',
                name: 'Испания',
                flag: '<svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="6" fill="#AA151B"/><rect y="6" width="24" height="6" fill="#F1BF00"/><rect y="12" width="24" height="6" fill="#AA151B"/></svg>'
            },
            {
                code: '+48',
                name: 'Польша',
                flag: '<svg width="24" height="18" viewBox="0 0 24 18" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="24" height="9" fill="#FFFFFF"/><rect y="9" width="24" height="9" fill="#DC143C"/></svg>'
            }
        ],

        // Методы для нового поля номера телефона (без префикса)
        onPhoneNumberFocus(e) {
            // Не добавляем автоматически 0, так как он уже в коде страны
        },
        onPhoneNumberClick(e) {
            // При клике убеждаемся, что каретка в правильном месте
        },
        onPhoneNumberBackspace(e) {
            // Обычная обработка Backspace для поля номера
            const el = e.target;
            try {
                if (!el || typeof el.selectionStart === 'undefined' || !el.setSelectionRange) return;
                if (el.selectionStart <= this.PREFIX_LEN) {        // не даём стирать префикс
                    e.preventDefault();
                    el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN);
                }
            } catch (_) {}
        },
        // Методы для старого поля логина (legacy modal) - для обратной совместимости
        onLoginFocus(e) {
            const el = e.target;
            try {
                if (!el || typeof el.value === 'undefined') return;
                if (!el.value || el.value === '') {
                    el.value = this.PREFIX;
                }
                if (typeof el.setSelectionRange === 'function') {
                    const start = Math.max(this.PREFIX_LEN, el.selectionStart || this.PREFIX_LEN);
                    el.setSelectionRange(start, start);
                }
            } catch (_) {}
        },
        onLoginClick(e) {
            this.onLoginFocus(e);
        },
        onLoginBackspace(e) {
            const el = e.target;
            try {
                if (!el || typeof el.selectionStart === 'undefined' || !el.setSelectionRange) return;
                if (el.selectionStart <= this.PREFIX_LEN) {
                    e.preventDefault();
                    el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN);
                }
            } catch (_) {}
        },
        focusLoginAfterOpen() {                                 // вызывать при открытии модалки
            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        try {
                            // Если метод авторизации phone_sms - фокусируемся на новом поле
                            if (this.authMethod === 'phone_sms') {
                                const el = document.getElementById('login-phone-sms-new') || this.$refs?.loginPhoneSms;
                                if (el) {
                                    if (!el.value) el.value = this.PREFIX;
                                    el.focus({ preventScroll: true });
                                    try { el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN); } catch (_) {}
                                }
                            } else {
                                // Иначе фокусируемся на старом поле (phone_password_sms)
                                const el = document.getElementById('login-phone') || this.$refs?.loginPhone;
                                if (el) {
                                    if (!el.value) el.value = this.PREFIX;
                                    el.focus({ preventScroll: true });
                                    try { el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN); } catch (_) {}
                                }
                            }
                        } catch(e) {
                            // Silent error
                        }
                    }, 150);
                });
            });
        },
        focusForgotAfterOpen() {
            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        const el = this.$refs?.forgotPhone;
                        if (!el) return;
                        if (!el.value) el.value = this.PREFIX;
                        el.focus({ preventScroll: true });
                        try { el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN); } catch (_) {}
                    }, 30);
                });
            });
        },
        onForgotFocus(e) {
            const el = e.target;
            if (!el.value) el.value = this.PREFIX;
            this.$nextTick(() => {
                try { el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN); } catch (_) {}
            });
        },
        onForgotClick(e) {
            const el = e.target;
            try {
                if (!el || typeof el.selectionStart === 'undefined' || !el.setSelectionRange) return;
                if (el.selectionStart < this.PREFIX_LEN)
                    el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN);
            } catch (_) {}
        },
        onForgotBackspace(e) {
            const el = e.target;
            try {
                if (!el || typeof el.selectionStart === 'undefined' || !el.setSelectionRange) return;
                if (el.selectionStart <= this.PREFIX_LEN) {
                    e.preventDefault();
                    el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN);
                }
            } catch (_) {}
        },
        title: t('auth.title'),
        normalizePhone(val){
            let d = String(val || '').replace(/\D/g, '');
            if (d.startsWith('0')) d = '38' + d;
            if (d.startsWith('3800')) d = '380' + d.slice(4);
            if (d.length === 9)  d = '380' + d;
            if (!d.startsWith('380') && d.length >= 10) d = '380' + d.slice(-9);
            return d;
        },
        switchTab(tabName){
            // Синхронизация данных при переключении вкладок
            if (tabName === 'register' && this.tab === 'login') {
                // Переключение с логина на регистрацию: копируем phone и password
                if (this.loginData.phone) {
                    this.registerData.phone = this.loginData.phone;
                }
                if (this.loginData.password) {
                    this.registerData.password = this.loginData.password;
                    this.registerData.password_confirmation = this.loginData.password;
                }
            } else if (tabName === 'login' && this.tab === 'register') {
                // Переключение с регистрации на логин: копируем phone и password
                if (this.registerData.phone) {
                    this.loginData.phone = this.registerData.phone;
                }
                if (this.registerData.password) {
                    this.loginData.password = this.registerData.password;
                }
            }

            this.tab = tabName;
            this.title =
                tabName==='login'   ? t('auth.login') :
                tabName==='register'? t('auth.register')  :
                tabName==='sms'     ? t('auth.phone_confirm') :
                                t('auth.title');
        },
        get routes(){
            const el = document.getElementById('authModal');
            const R = window.Routes || {};
            return {
                login:    R.login    || el?.dataset.login,
                sendCode: R.sendCode || el?.dataset.sendCode,
                verify:   R.verify   || el?.dataset.verify,
                pwdSendCode:      R.pwdSendCode      || el?.dataset.pwdSendCode,
                pwdVerify:        R.pwdVerify        || el?.dataset.pwdVerify,
                phoneSmsSendCode: R.phoneSmsSendCode || el?.dataset.phoneSmsSendCode,
                phoneSmsVerify:   R.phoneSmsVerify   || el?.dataset.phoneSmsVerify,
            };
        },
        // login
        loginLoading: false,
        loginError: null,
        loginData: { phone:'', password:'' },
        async login(){
            this.loginLoading = true; this.loginError = null;
            try{
                const csrf = await getCsrf();
                const res = await fetch(this.routes.login, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(this.loginData),
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok || data.ok === false){
                    this.loginError = data.message || data?.errors?.phone?.[0] || t('auth.Login_error');
                    return;
                }
                window.location.reload();
            } finally {
                this.loginLoading = false;
            }
        },

        afterPaint(fn) {
            requestAnimationFrame(() => requestAnimationFrame(fn));
        },
        isVisible(el) {
            if (!el) return false;
            if (el.offsetParent === null) return false; // display:none
            const cs = getComputedStyle(el);
            return cs.visibility !== 'hidden' && cs.opacity !== '0';
        },

        focusRefWhenReady(refName, { tries = 60, step = 30 } = {}) {
            return new Promise((resolve) => {
                const tryFocus = () => {
                    const el = this.$refs?.[refName] || this.forgotRefs?.[0]; // fallback
                    if (el && this.isVisible(el)) {
                        requestAnimationFrame(() => {
                            requestAnimationFrame(() => {
                                try { el.focus(); el.select?.(); } catch (_) {}
                                resolve(true);
                            });
                        });
                    } else if (tries > 0) {
                        setTimeout(() => { tries--; tryFocus(); }, step);
                    } else {
                        resolve(false);
                    }
                };
                this.$nextTick(tryFocus);
            });
        },

        focusOtp(which = 'sms', idx = 0) {
            const arr = which === 'sms' ? this.otpRefs : this.forgotRefs;
            this.$nextTick(() => {
                this.afterPaint(() => {
                    const el = arr[idx];
                    if (el && typeof el.focus === 'function') {
                        el.focus();
                        try { el.select(); } catch(e) {}
                    }
                });
            });
        },

        deferFocus(n){
            // фокусим после того, как Alpine закончит патчить DOM
            this.$nextTick(() => {
                setTimeout(() => {
                    const el = this.$refs['fotp' + n];
                    if (el && typeof el.focus === 'function') {
                        el.focus();
                        try { el.select(); } catch(e) {}
                    }
                }, 0); // можно 0–10 мс
            });
        },

        focusIndex(which, idx){
            const refs = which === 'sms' ? this.otpRefs : this.forgotRefs;
            this.$nextTick(() => {
                setTimeout(() => {
                    const el = refs[idx];
                    if (el && typeof el.focus === 'function') {
                        el.focus();
                        try { el.select(); } catch(_) {}
                    }
                }, 0);
            });
        },

        // --- универсальная обработка ввода цифры ---
        handleOtpInput(which, i, e){
            const arr = which === 'sms' ? this.otp : this.forgotOtp;
            let v = (e?.target?.value || '').replace(/\D/g, '').slice(0, 1);
            e.target.value = v;
            arr[i - 1] = v;
            if (v && i < 4) this.focusIndex(which, i); // на следующий
        },

        // --- универсальная обработка backspace ---
        handleOtpBackspace(which, i){
            const arr = which === 'sms' ? this.otp : this.forgotOtp;
            if (!arr[i - 1] && i > 1) this.focusIndex(which, i - 2); // на предыдущий
            arr[i - 1] = '';
        },
        // безопасный фокус
        deferFocusIdx(idx){
            this.$nextTick(() => {
                setTimeout(() => {
                    const el = this.forgotRefs[idx];
                    if (el && typeof el.focus === 'function') {
                        el.focus();
                        try { el.select(); } catch (e) {}
                    }
                }, 0);
            });
        },

        // register → send code
        registerError: null,
        registerData: { name:'', phone:'', email:'', password:'', password_confirmation:'' },
        async register(){
            if (this.sending) return;
            this.registerError = null;

            // Валидация пароля: минимум 6 символов
            const password = String(this.registerData.password || '').trim();
            if (password.length < 6) {
                this.registerError = t('auth.password_min_6', 'Пароль повинен містити мінімум 6 символів');
                return;
            }

            // Проверка совпадения паролей
            if (password !== String(this.registerData.password_confirmation || '').trim()) {
                this.registerError = t('auth.password_mismatch', 'Паролі не співпадають');
                return;
            }

            this.sending = true;

            // открываем SMS и ставим фокус
            this.switchTab('sms');
            this.sms.resendIn = 0;
            this.sms.ttl = 0;
            await this.focusRefWhenReady('otp1');

            const fd = new FormData();
            Object.entries(this.registerData).forEach(([k,v]) => fd.append(k, v ?? ''));
            fd.append('_token', await getCsrf());

            try {
                const res = await fetch(this.routes.sendCode, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json().catch(() => ({}));

                if (!res.ok || data.ok !== true){
                    // показать точную причину
                    this.registerError = data.message || (data.errors && Object.values(data.errors)[0][0]) || t('auth.login_error');
                    // вернём обратно на форму регистрации
                    this.switchTab('register');
                    return;
                }

                // успех
                const digits = data.phone || this.normalizePhone(this.registerData.phone); // ← this.
                this.sms.phonePretty = this.registerData.phone;
                this.sms.phoneDigits = digits;
                sessionStorage.setItem('regPhoneDigits', digits);
                sessionStorage.setItem('regPhonePretty', this.registerData.phone);

                this.sms.resendIn = data.resend_in ?? 60;
                this.sms.ttl      = data.ttl ?? 180;
                this.startResendTimer();
                await this.focusRefWhenReady('otp1');

            } finally {
                this.sending = false;
            }
        },

        // запустить таймер resend для forgot
        startForgotTimer(){
            if (this._ftimer) clearInterval(this._ftimer);
            this._ftimer = setInterval(()=>{
                if (this.forgot.resendIn > 0) this.forgot.resendIn--;
                else clearInterval(this._ftimer);
            }, 1000);
        },

        async forgotSend(){
            if (this.sending) return;
            this.sending = true; this.forgotError = null;

            // 1) Сначала открыть форму ввода кода и поставить фокус
            this.switchTab('forgot-otp');
            this.forgot.resendIn = 0;
            this.forgot.ttl = 0;

            // гарантированный фокус на первый инпут кода
            await this.focusRefWhenReady('fotp1');

            try {
                const fd = new FormData();
                fd.append('phone', this.forgot.phonePretty);
                fd.append('_token', await getCsrf());

                const res = await fetch(this.routes.pwdSendCode, {
                    method:'POST',
                    credentials:'same-origin',
                    headers:{ 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json().catch(()=>({}));

                if (!res.ok || data.ok !== true){
                    this.forgotError = data.errors?.phone?.[0] || data.message || t('auth.send_error');
                    // если ошибка — вернёмся на ввод телефона
                    this.switchTab('forgot');
                    return;
                }

                // 2) Сохраняем номер и запускаем таймер
                const digits = data.phone || this.normalizePhone(this.forgot.phonePretty); // ← this.
                this.forgot.phoneDigits = digits;
                sessionStorage.setItem('pwdPhoneDigits', digits);
                sessionStorage.setItem('pwdPhonePretty', this.forgot.phonePretty);

                this.forgot.resendIn = data.resend_in ?? 60;
                this.forgot.ttl      = data.ttl ?? 180;
                this.startForgotTimer();

                // 3) Подстраховка — повторный фокус (если DOM перерисовался)
                await this.focusRefWhenReady('fotp1');

            } finally {
                this.sending = false;
            }
        },

        async forgotVerify(){
            if (this.verifying) return;
            this.verifying = true; this.forgotError = null;

            try {
                const code = (this.forgotOtp || []).join('');
                if (code.length !== 4){ this.forgotError = t('auth.enter4'); return; }

                const phoneDigits =
                    this.forgot.phoneDigits ||
                    sessionStorage.getItem('pwdPhoneDigits') ||
                    this.normalizePhone(this.forgot.phonePretty || sessionStorage.getItem('pwdPhonePretty') || ''); // ← this.

                if (!/^380\d{9}$/.test(phoneDigits)){
                    this.forgotError = t('auth.enter_phone'); return;
                }

                const fd = new FormData();
                fd.append('phone', phoneDigits);
                fd.append('code',  code);
                fd.append('_token', await getCsrf());

                const res = await fetch(this.routes.pwdVerify, {
                    method:'POST',
                    credentials:'same-origin',
                    headers:{ 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json().catch(()=>({}));

                if (!res.ok || data.ok !== true){
                    const codeKey = data.errors?.code?.[0];

                    this.forgotError =
                        data.message
                        || (codeKey === 'expired' ? t('auth.code_expired') :
                        codeKey === 'invalid' ? t('auth.code_invalid') :
                            'Помилка перевірки');

                    return;
                }

                // залогинен → ведём на страницу смены пароля
                window.location = data.redirect || '/profile/password';

            } finally { this.verifying = false; }
        },

        // sms step
        smsError: null,
        async verifySms(){
            if (this.verifying) return;
            this.verifying = true;
            this.smsError = null;
            this.otpError = null;
            this.authSuccess = false;

            try {
                const code = (this.otp || []).join('');
                if (code.length !== 4){ 
                    // Не устанавливаем ошибку, если код неполный - просто выходим
                    this.verifying = false;
                    return; 
                }

                // Определяем, какой тип верификации: регистрация или логин по телефону
                const isLoginSms = this.authMethod === 'phone_sms' && this.loginPhoneSmsSent;

                // берём из состояния -> из sessionStorage -> нормализуем pretty
                const phoneDigits =
                    this.sms.phoneDigits ||
                    sessionStorage.getItem('regPhoneDigits') ||
                    this.normalizePhone(this.sms.phonePretty || sessionStorage.getItem('regPhonePretty') || '');

                // если номер не собрали — сразу понятная ошибка и выходим
                if (!/^380\d{9}$/.test(phoneDigits)) {
                    this.otpError = t('auth.enter_phone', 'Введите номер телефона');
                    this.verifying = false;
                    return;
                }

                // Определяем маршрут для верификации (isLoginSms уже определена выше)
                const verifyRoute = isLoginSms ? this.routes.phoneSmsVerify : this.routes.verify;

                const fd = new FormData();
                fd.append('phone', phoneDigits);
                fd.append('code',  code);
                fd.append('_token', await getCsrf());

                const res  = await fetch(verifyRoute, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json().catch(()=>({}));

                if (!res.ok || data.ok !== true){
                    const codeKey = data.errors?.code?.[0];

                    this.otpError =
                        data.message
                        || (codeKey === 'expired' ? t('auth.code_expired', 'Код истек') :
                        codeKey === 'invalid' ? t('auth.code_invalid', 'Невірний код. Перевірте цифри та спробуйте ще раз.') :
                            t('auth.verify_error', 'Ошибка верификации'));
                    
                    // console.log('OTP Error set:', this.otpError);
                    this.verifying = false;
                    return;
                }

                // Успешная верификация
                this.authSuccess = true;
                this.otpError = null;

                // Если это логин по телефону - показываем успех и редиректим
                if (isLoginSms) {
                    // Ждем немного, чтобы показать сообщение об успехе
                    setTimeout(() => {
                        if (data.redirect) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.reload();
                        }
                    }, 2000);
                    return;
                }

                // Если это регистрация - показываем успех
                this.successMessage = data.message || t('auth.success_registered', 'Регистрация успешна');
                this.switchTab('success');
                setTimeout(() => window.location = data.redirect || '/', 1500);
            } finally {
                // verifying устанавливается в false только при ошибке, при успехе остается true
                if (this.otpError) {
                    this.verifying = false;
                }
            }
        },

        async resendCode(){
            if (this.sms.resendIn > 0 || this.sending) return;
            this.sending = true;
            this.smsError = null;

            try {
                const phoneDigits = this.sms.phoneDigits ||
                    this.normalizePhone(this.sms.phonePretty || sessionStorage.getItem('regPhonePretty') || '');

                // Определяем, какой тип верификации: регистрация или логин по телефону
                const isLoginSms = this.authMethod === 'phone_sms' && this.loginPhoneSmsSent;
                const sendRoute = isLoginSms ? this.routes.phoneSmsSendCode : this.routes.sendCode;

                const fd = new FormData();
                fd.append('phone', phoneDigits);
                fd.append('_token', await getCsrf());

                const res = await fetch(sendRoute, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json().catch(()=>({}));

                if (!res.ok || data.ok !== true){
                    this.smsError = data.message || (data.errors && Object.values(data.errors)[0]?.[0]) || t('auth.send_error');
                    return;
                }

                this.sms.resendIn = data.resend_in ?? 60;
                this.sms.ttl      = data.ttl ?? 180;
                this.startResendTimer();
                await this.focusRefWhenReady('otp1');

            } finally {
                this.sending = false;
            }
        },

        // Авторизация только по телефону + SMS (без пароля)
        async sendLoginPhoneSms() {
            if (this.loginPhoneSmsLoading) return;
            this.loginPhoneSmsLoading = true;
            this.loginPhoneSmsError = null;

            try {
                // Формируем полный номер телефона из кода страны и номера
                const countryCode = (this.loginPhoneSmsData.countryCode || '+380').replace(/\+/g, '');
                let phoneNumber = (this.loginPhoneSmsData.phoneNumber || '').replace(/\D/g, '');

                if (countryCode === '380') {
                    phoneNumber = phoneNumber.replace(/^0+/, '');
                }
                
                if (!phoneNumber) {
                    this.loginPhoneSmsError = t('auth.enter_phone', 'Вкажіть номер телефону');
                    return;
                }

                this.loginPhoneSmsData.phoneNumber = phoneNumber;

                // Формируем полный номер для отображения
                const fullPhoneDisplay = '+' + countryCode + ' ' + phoneNumber;
                this.loginPhoneSmsData.phone = fullPhoneDisplay;
                
                // Формируем номер для отправки на сервер (убираем все нецифровые символы)
                const phoneDigits = countryCode + phoneNumber;
                
                // Валидация для украинских номеров (9 цифр после кода страны)
                if (countryCode === '380' && !/^380\d{9}$/.test(phoneDigits)) {
                    this.loginPhoneSmsError = t('auth.enter_phone', 'Вкажіть номер телефону');
                    return;
                }

                // Отправка запроса на сервер (в тестовом режиме сервер не отправляет реальное SMS)
                const fd = new FormData();
                fd.append('phone', phoneDigits);
                fd.append('_token', await getCsrf());

                const res = await fetch(this.routes.phoneSmsSendCode, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });

                const data = await res.json().catch(() => ({}));

                if (!res.ok || data.ok !== true) {
                    this.loginPhoneSmsError = data.message || (data.errors && Object.values(data.errors)[0]?.[0]) || t('auth.send_error', 'Помилка відправки');
                    return;
                }

                // Сохраняем данные для SMS верификации
                this.sms.phonePretty = this.loginPhoneSmsData.phone;
                this.sms.phoneDigits = phoneDigits;
                // Форматируем номер телефона для отображения: +380 (50) 715-27-68
                if (phoneDigits && phoneDigits.length >= 12 && phoneDigits.startsWith('380')) {
                    const code = phoneDigits.substring(3, 5); // 50
                    const part1 = phoneDigits.substring(5, 8); // 715
                    const part2 = phoneDigits.substring(8, 10); // 27
                    const part3 = phoneDigits.substring(10, 12); // 68
                    this.sms.phoneFormatted = `+380 (${code}) ${part1}-${part2}-${part3}`;
                } else {
                    this.sms.phoneFormatted = this.loginPhoneSmsData.phone;
                }
                this.sms.resendIn = data.resend_in ?? 60;
                this.sms.ttl = data.ttl ?? 180;
                this.loginPhoneSmsSent = true;

                // Переключаемся на вкладку SMS
                this.switchTab('sms');
                
                // Отладка
                // console.log('SMS code sent:', {
                //     loginPhoneSmsSent: this.loginPhoneSmsSent,
                //     tab: this.tab,
                //     phoneFormatted: this.sms.phoneFormatted,
                //     phonePretty: this.sms.phonePretty
                // });
                
                this.startResendTimer();
                
                // Фокусируемся на первом поле OTP - используем прямую проверку через refs
                this.$nextTick(() => {
                    requestAnimationFrame(() => {
                        setTimeout(() => {
                            const el = this.$refs?.otp1;
                            if (el) {
                                el.focus();
                                try { el.select(); } catch(e) {}
                            }
                        }, 150);
                    });
                });

            } finally {
                this.loginPhoneSmsLoading = false;
            }
        },

        // Изменить номер телефона (для phone_sms варианта)
        changePhone() {
            this.loginPhoneSmsSent = false;
            this.loginPhoneSmsError = null;
            this.otp = ['', '', '', ''];
            this.switchTab('login');
            this.$nextTick(() => {
                // Используем querySelector вместо $refs для надежности
                const el = document.getElementById('login-phone-sms-new') ||
                          document.getElementById('login-phone-sms') ||
                          this.$refs?.loginPhoneSms;
                if (el) {
                    el.focus();
                    try { el.setSelectionRange(5,5) } catch(e) {}
                }
            });
        },

        startResendTimer(){
            if (this._timer) clearInterval(this._timer);
            this._timer = setInterval(()=>{
                if (this.sms.resendIn > 0) this.sms.resendIn--;
                else clearInterval(this._timer);
            }, 1000);
        },
        stopResendTimer(){
            if (this._timer) { clearInterval(this._timer); this._timer = null; }
        }
    };
}
