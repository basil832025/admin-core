@php
    use App\Models\SiteText;

    // Текущий язык
    $locale = app()->getLocale();

    // Забираем все записи со слагом, начинающимся на "auth."
    $authTexts = SiteText::query()
        ->where('slug', 'like', 'auth.%')
        ->get(['slug', 'value']);

    // Формируем словарь: ['auth.login' => 'Увійти', ...]
    $authI18n = $authTexts
        ->mapWithKeys(fn($row) => [
            $row->slug => $row->getTranslation('value', $locale) ?: $row->slug,
        ])
        ->toArray();
@endphp
<div
    id="authModal"
    x-data="authModal({ i18n: @js($authI18n) })"
    x-show="open"
    x-effect='
      if (open && tab === "login") {
    $nextTick(() => {
      requestAnimationFrame(() => {
        setTimeout(() => {
          $refs.loginPhone?.focus({ preventScroll: true });
          try { $refs.loginPhone.setSelectionRange(100,100) } catch(e) {}
        }, 30);
      });
    });
  }
        if (open && tab === "sms") {
            $nextTick(() => {
                requestAnimationFrame(() => {
                    const el = $el.querySelector(`[data-otp-index="0"]`);
                    if (el) { el.focus(); try { el.select(); } catch(e) {} }
                });
            });
        }
    '
    data-login="{{ route('auth.login') }}"
    data-send-code="{{ route('auth.register.send-code') }}"
    data-verify="{{ route('auth.register.verify') }}"
    data-pwd-send-code="{{ route('auth.password.sendCode') }}"
    data-pwd-verify="{{ route('auth.password.verify') }}"

    x-transition.opacity
    x-cloak
    @open-auth-modal.window="
    const payload = $event.detail || {};

    // открыть модалку
    open = true;
    loginLoading = false;

    // вкладка: login / register
    if (payload.tab) {
        switchTab(payload.tab);
    } else {
        switchTab('login');
    }

    // подставить имя в регистрацию
    if (payload.name) {
        registerData.name = payload.name;
    }

    // подставить телефон в логин и регистрацию
    if (payload.phone) {
        loginData.phone    = payload.phone;
        registerData.phone = payload.phone;
    }

    // сфокусироваться на поле логина (как и было раньше)
    focusLoginAfterOpen();
"

    class="fixed inset-0 z-50"
>
    <div class="hidden" x-text="'tab='+tab"></div>
    <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]" @click="open=false"></div>

    <div class="relative mx-auto mt-10 w-[480px] max-w-full rounded-2xl bg-white p-6 shadow-xl">
        <button class="absolute right-3 top-3 text-2xl" @click="open=false">&times;</button>

        <h2 class="text-3xl font-bold text-center mb-4" x-text="title"></h2>

        <div class="grid grid-cols-2 gap-2 mb-4">
            <button :class="tab==='login'?'bg-orange-600 text-white':'bg-gray-200'"
                    class="tab-btn rounded-full py-2 font-medium"
                    @click="switchTab('login')">{{ st('auth.login','Увійти') }}</button>

            <button :class="tab==='register'?'bg-orange-600 text-white':'bg-gray-200'"
                    class="tab-btn rounded-full py-2 font-medium"
                    @click="switchTab('register')">{{ st('auth.register','Реєстрація') }}</button>
        </div>

        {{-- LOGIN --}}
        <form x-show="tab==='login'" @submit.prevent="login" class="space-y-3">
            <input
                id="login-phone"
                type="tel"
                x-model="loginData.phone"
                x-ref="loginPhone"
                x-init="window.applyUaPhoneMask($el)"
                @focus="onLoginFocus($event)"
                @click="onLoginClick($event)"
                @keydown.backspace="onLoginBackspace($event)"
                @input="loginError=null"
                inputmode="numeric"
                dir="ltr"
                class="w-full rounded-md ring-1 px-3 py-2 focus:ring-1 caret-black"
                :class="loginError ? 'ring-red-400 focus:ring-red-500' : 'ring-black/10 focus:ring-[#FF7500]'"
                placeholder="+380 __ ___ __ __"
                required
            >




            <input type="password"
                   x-model="loginData.password"
                   @input="loginError=null"
                   class="w-full rounded-md ring-1 px-3 py-2"
                   :class="loginError ? 'ring-red-400 focus:ring-red-500' : 'ring-black/10 focus:ring-[#FF7500]'"
                   placeholder="{{ st('auth.password','Пароль') }}"
                   required>

            <p x-show="loginError" x-text="loginError" class="text-red-600 text-sm"></p>
            <div class="text-right">
                <button type="button" class="text-sm text-orange-600 hover:underline"
                        @click="
    switchTab('forgot');
    $nextTick(() => requestAnimationFrame(() => {
      setTimeout(() => {
        $refs.forgotPhone?.focus({ preventScroll: true });
        try { $refs.forgotPhone.setSelectionRange(5,5) } catch(e) {}
      }, 30);
    }));
  "
                >
                    {{ st('auth.forgot_password','Забули пароль') }}?
                </button>

            </div>
            <button
                x-show="tab === 'login'"
                x-cloak
                class="w-full h-12 rounded-full bg-[#FF7500] text-white font-semibold"
                :disabled="loginLoading"
                @click="login"
            >
                <span x-show="!loginLoading">{{ st('auth.login','Увійти') }}</span>
                <span x-show="loginLoading">{{ st('auth.login2','Вхід…') }}</span>
            </button>
        </form>



        {{-- REGISTER --}}
        <form x-show="tab==='register'" @submit.prevent="register" class="space-y-3">
            <input type="text"
                   x-ref="registerName"
                   x-model="registerData.name"
                   required
                   minlength="2"
                   class="w-full rounded-md ring-1 ring-black/10 px-3 py-2"
                   placeholder="{{ st('reviews.enter_your_name','Введіть ім’я') }}">
            <input
                id="reg-phone"
                type="tel"
                x-model="registerData.phone"
                x-init="window.applyUaPhoneMask($el)"
                required
                inputmode="numeric"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#FF7500]"
                placeholder="+38 0__ ___ __ __"
            />
            <input type="email"   x-model="registerData.email" class="w-full rounded-md ring-1 ring-black/10 px-3 py-2" placeholder="{{ st('reviews.enter_your_email','Ведіть email') }}">
            <input type="password"  required
                   minlength="6" x-model="registerData.password" class="w-full rounded-md ring-1 ring-black/10 px-3 py-2" placeholder="{{ st('auth.password','Пароль') }}">
            <input type="password" required x-model="registerData.password_confirmation" class="w-full rounded-md ring-1 ring-black/Н0 px-3 py-2" placeholder="{{ st('auth.repeat_password','Повторіть пароль') }}">
            <p x-show="registerError" x-text="registerError" class="text-red-600 text-sm"></p>
            <button
                x-show="tab === 'register'"
                x-cloak
                class="w-full h-12 rounded-full bg-[#FF7500] text-white font-semibold"
                @click="register"
            >
                {{ st('auth.register2','Зареєструватися') }}
            </button>
        </form>

        {{-- SMS --}}
        <template x-if="tab==='sms'">
            <div class="space-y-3">

                <p class="text-center text-sm text-gray-600">
                    {{ st('auth.to_number','На номер') }}   <span class="font-medium" x-text="sms.phonePretty"></span>{{ st('auth.confirmation_code_sent','надіслано код підтвердження') }}  .
                </p>

                <div class="flex gap-3 justify-center">
                    <input
                        x-ref="otp1"
                        x-model="otp[0]"
                        x-init="$nextTick(() => { otpRefs[0] = $el })"
                        type="tel" inputmode="numeric" maxlength="1"
                        class="w-12 h-12 text-center border rounded text-lg"
                        @input="handleOtpInput('sms', 1, $event)"
                        @keydown.backspace.prevent="handleOtpBackspace('sms', 1)"
                    >

                    <input
                        x-ref="otp2"
                        x-model="otp[1]"
                        x-init="$nextTick(() => { otpRefs[1] = $el })"
                        type="tel" inputmode="numeric" maxlength="1"
                        class="w-12 h-12 text-center border rounded text-lg"
                        @input="handleOtpInput('sms', 2, $event)"
                        @keydown.backspace.prevent="handleOtpBackspace('sms', 2)"
                    >

                    <input
                        x-ref="otp3"
                        x-model="otp[2]"
                        x-init="$nextTick(() => { otpRefs[2] = $el })"
                        type="tel" inputmode="numeric" maxlength="1"
                        class="w-12 h-12 text-center border rounded text-lg"
                        @input="handleOtpInput('sms', 3, $event)"
                        @keydown.backspace.prevent="handleOtpBackspace('sms', 3)"
                    >

                    <input
                        x-ref="otp4"
                        x-model="otp[3]"
                        x-init="$nextTick(() => { otpRefs[3] = $el })"
                        type="tel" inputmode="numeric" maxlength="1"
                        class="w-12 h-12 text-center border rounded text-lg"
                        @input="handleOtpInput('sms', 4, $event)"
                        @keydown.backspace.prevent="handleOtpBackspace('sms', 4)"
                    >
                </div>





                <p x-show="smsError" x-text="smsError" class="text-center text-red-600 text-sm"></p>

                <button
                    x-show="tab === 'sms'"
                    x-cloak
                    class="w-full h-12 rounded-full bg-[#FF7500] text-white font-semibold"
                    :disabled="verifying"
                    @click="verifySms"
                >
                    <span x-show="!verifying">{{ st('auth.confirm','Підтвердити') }}</span>
                    <span x-show="verifying">{{ st('auth.verifying','Перевіряємо…') }}</span>
                </button>

                <button
                    class="block w-full text-center text-sm text-gray-600 hover:text-gray-800"
                    :disabled="sms.resendIn>0"
                    @click="resendCode()">
                    <span x-show="sms.resendIn===0">  {{ st('auth.send_again','Надіслати код ще раз') }}</span>
                    <span x-show="sms.resendIn>0">{{ st('auth.resend_in','Повторно через') }} <span x-text="sms.resendIn"></span> c</span>

                </button>

            </div>

        </template>

        <!-- SUCCESS -->
        <div x-show="tab==='success'" x-transition class="space-y-3 text-center">
            <div class="mx-auto w-14 h-14 rounded-full bg-green-100 flex items-center justify-center">
                <!-- простая галочка -->
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M5 13l4 4L19 7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h3 class="text-xl font-semibold">{{ st('auth.done','Готово!') }}</h3>
            <p class="text-gray-600" x-text="successMessage"></p>
            <p class="text-sm text-gray-500">{{ st('auth.redirecting','Зараз відбудеться перехід…') }}</p>
        </div>

        <!-- Відновлення паролю: ввести телефон -->
        <div x-show="tab==='forgot'" class="space-y-3">
            <p class="text-sm text-gray-600">{{ st('auth.enter_phone_send_code','Вкажіть номер телефону, ми надішлемо код для входу.') }}</p>

            <input
                id="forgot-phone"
                type="tel"
                x-model="forgot.phonePretty"
                x-ref="forgotPhone"
                x-init="window.applyUaPhoneMask($el)"
                @focus="onForgotFocus($event)"
                @click="onForgotClick($event)"
                @keydown.backspace="onForgotBackspace($event)"
                inputmode="numeric"
                dir="ltr"
                class="w-full border rounded-lg px-3 py-2 caret-black"
                placeholder="+380 __ ___ __ __"
                required
            >


            <p x-show="forgotError" x-text="forgotError" class="text-sm text-red-600"></p>

            <button class="w-full rounded-full bg-orange-600 text-white py-3"
                    :disabled="sending"
                    @click="forgotSend()">
                {{ st('auth.send_code2','Надіслати код') }}
            </button>
        </div>
        <!-- Підтвердження коду для відновлення -->

        <div x-show="tab==='forgot-otp'" x-cloak
             x-effect="if (tab==='forgot-otp') { $nextTick(() => { forgotRefs[0]?.focus(); try{forgotRefs[0].select()}catch(e){} }) }"

             class="space-y-3">
            <p class="text-sm text-gray-600">
                {{ st('auth.to_number','На номер') }}   <span class="font-medium" x-text="forgot.phonePretty"></span>{{ st('auth.code_sent','надіслано код') }} .
            </p>

            <div class="flex gap-3 justify-center">
                <input
                    x-ref="fotp1"
                    x-model="forgotOtp[0]"
                    x-init="$nextTick(() => { forgotRefs[0] = $el })"
                    type="tel" inputmode="numeric" maxlength="1"
                    class="w-12 h-12 text-center border rounded text-lg"
                    @input="handleOtpInput('forgot', 1, $event)"
                    @keydown.backspace.prevent="handleOtpBackspace('forgot', 1)"
                >

                <input
                    x-ref="fotp2"
                    x-model="forgotOtp[1]"
                    x-init="$nextTick(() => { forgotRefs[1] = $el })"
                    type="tel" inputmode="numeric" maxlength="1"
                    class="w-12 h-12 text-center border rounded text-lg"
                    @input="handleOtpInput('forgot', 2, $event)"
                    @keydown.backspace.prevent="handleOtpBackspace('forgot', 2)"
                >

                <input
                    x-ref="fotp3"
                    x-model="forgotOtp[2]"
                    x-init="$nextTick(() => { forgotRefs[2] = $el })"
                    type="tel" inputmode="numeric" maxlength="1"
                    class="w-12 h-12 text-center border rounded text-lg"
                    @input="handleOtpInput('forgot', 3, $event)"
                    @keydown.backspace.prevent="handleOtpBackspace('forgot', 3)"
                >

                <input
                    x-ref="fotp4"
                    x-model="forgotOtp[3]"
                    x-init="$nextTick(() => { forgotRefs[3] = $el })"
                    type="tel" inputmode="numeric" maxlength="1"
                    class="w-12 h-12 text-center border rounded text-lg"
                    @input="handleOtpInput('forgot', 4, $event)"
                    @keydown.backspace.prevent="handleOtpBackspace('forgot', 4)"
                >
            </div>


            <!-- помилки для forgot -->
            <p x-show="forgotError" x-text="forgotError" class="text-sm text-red-600 text-center"></p>

            <!-- підтвердження коду (forgot) -->
            <button
                class="w-full h-12 rounded-full bg-[#FF7500] text-white font-semibold"
                :disabled="verifying"
                @click="forgotVerify"
            >
                <span x-show="!verifying"> {{ st('auth.confirm','Підтвердити') }}</span>
                    <span x-show="verifying">{{ st('auth.verifying','Перевіряємо…') }}</span>
            </button>

            <!-- повторна відправка (forgot) -->
            <button type="button"
                    class="block w-full text-center text-sm text-gray-600 hover:text-gray-800"
                    :disabled="forgot.resendIn>0"
                    @click="forgotSend()">
                <span x-show="forgot.resendIn===0">{{ st('auth.send_again','Надіслати код ще раз') }}</span>
                <span x-show="forgot.resendIn>0">{{ st('auth.resend_in','Повторно через') }} <span x-text="forgot.resendIn"></span> c</span>
            </button>
        </div>




        </div>


        <div class="my-4 flex items-center gap-3">
            <div class="flex-1 h-px bg-black/10"></div>
            <div class="text-sm text-gray-500">Або</div>
            <div class="flex-1 h-px bg-black/10"></div>
        </div>

       <!-- <div class="flex items-center justify-center gap-3">
            <a href="{{ route('auth.redirect','apple') }}"><img src="/images/svg/apple.svg" class="w-10 h-10" alt=""></a>
            <a href="{{ route('auth.redirect','facebook') }}"><img src="/images/svg/facebook.svg" class="w-10 h-10" alt=""></a>
            <a href="{{ route('auth.redirect','google') }}"><img src="/images/svg/google.svg" class="w-10 h-10" alt=""></a>
        </div>-->
    </div>
</div>

