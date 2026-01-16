// resources/js/alpine/auth-modal.js
// если у тебя уже есть глобальный getCsrf(), этот можно удалить
const getCsrf = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || '';

export default function authModal(opts = {}) {
    // локальный словарь, пришёл из Blade: { 'auth.login': 'Увійти', ... }
    const I18N = opts.i18n || {};

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
            resendIn: 0,
            ttl: 0,
        },

        otp: ['', '', '', ''],

        PREFIX: '+380 ',
        PREFIX_LEN: 5,

        onLoginFocus(e) {
            const el = e.target;
            if (!el.value) el.value = this.PREFIX;               // гарантируем префикс
            this.$nextTick(() => {                               // ставим каретку после +380␠
                try { el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN); } catch (_) {}
            });
        },
        onLoginClick(e) {
            const el = e.target;
            try {
                if (el.selectionStart < this.PREFIX_LEN) {
                    el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN);
                }
            } catch (_) {}
        },
        onLoginBackspace(e) {
            const el = e.target;
            try {
                if (el.selectionStart <= this.PREFIX_LEN) {        // не даём стирать префикс
                    e.preventDefault();
                    el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN);
                }
            } catch (_) {}
        },
        focusLoginAfterOpen() {                                 // вызывать при открытии модалки
            this.$nextTick(() => {
                requestAnimationFrame(() => {
                    setTimeout(() => {
                        const el = this.$refs?.loginPhone;
                        if (!el) return;
                        if (!el.value) el.value = this.PREFIX;
                        el.focus({ preventScroll: true });
                        try { el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN); } catch (_) {}
                    }, 30);
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
                if (el.selectionStart < this.PREFIX_LEN)
                    el.setSelectionRange(this.PREFIX_LEN, this.PREFIX_LEN);
            } catch (_) {}
        },
        onForgotBackspace(e) {
            const el = e.target;
            try {
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
                pwdSendCode:      R.pwdSendCode      || el?.dataset.pwdSendCode, // НОВОЕ
                pwdVerify:        R.pwdVerify        || el?.dataset.pwdVerify,   // НОВОЕ
            };
        },
        // login
        loginLoading: false,
        loginError: null,
        loginData: { phone:'', password:'' },
        async login(){
            this.loginLoading = true; this.loginError = null;
            try{
                const res = await fetch(this.routes.login, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrf(),
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
            fd.append('_token', getCsrf());

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
                fd.append('_token', getCsrf());

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
                fd.append('_token', getCsrf());

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

            try {
                const code = (this.otp || []).join('');
                if (code.length !== 4){ this.smsError = t('auth.enter4'); return; }

                // берём из состояния -> из sessionStorage -> нормализуем pretty
                const phoneDigits =
                    this.sms.phoneDigits ||
                    sessionStorage.getItem('regPhoneDigits') ||
                    this.normalizePhone(this.sms.phonePretty || sessionStorage.getItem('regPhonePretty') || ''); // ← this.

                // если номер не собрали — сразу понятная ошибка и выходим
                if (!/^380\d{9}$/.test(phoneDigits)) {
                    this.smsError =  t('auth.enter_phone');
                    return;
                }

                const fd = new FormData();
                fd.append('phone', phoneDigits);
                fd.append('code',  code);
                fd.append('_token', getCsrf());

                const res  = await fetch(this.routes.verify, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json().catch(()=>({}));

                if (!res.ok || data.ok !== true){
                    const codeKey = data.errors?.code?.[0];

                    this.smsError =
                        data.message
                        || (codeKey === 'expired' ? t('auth.code_expired') :
                        codeKey === 'invalid' ? t('auth.code_invalid') :
                            t('auth.verify_error'));

                    return;
                }

                this.successMessage = data.message || t('auth.success_registered');
                this.switchTab('success');
                setTimeout(() => window.location = data.redirect || '/', 1500);
            } finally {
                this.verifying = false;
            }
        },

        async resendCode(){
            if (this.sms.resendIn > 0 || this.sending) return;
            this.sending = true;
            this.smsError = null;

            try {
                const fd = new FormData();
                // достаточно номера телефона, остальное сервер может игнорить при ресенде
                fd.append('phone', this.sms.phonePretty || sessionStorage.getItem('regPhonePretty') || '');
                fd.append('_token', getCsrf());

                const res = await fetch(this.routes.sendCode, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json().catch(()=>({}));

                if (!res.ok || data.ok !== true){
                    this.smsError = data.message || (data.errors && Object.values(data.errors)[0][0]) || t('auth.send_error');
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

        startResendTimer(){
            if (this._timer) clearInterval(this._timer);
            this._timer = setInterval(()=>{
                if (this.sms.resendIn > 0) this.sms.resendIn--;
                else clearInterval(this._timer);
            }, 1000);
        },
        stopResendTimer(){
            if (this._timer) { clearInterval(this._timer); this._timer = null; }
        },
    };
}


