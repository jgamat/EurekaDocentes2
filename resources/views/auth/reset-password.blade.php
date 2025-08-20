@php($title = 'Restablecer contraseña')
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/><title>{{ $title }}</title>@vite(['resources/css/auth-public.css','resources/js/app.js'])</head><body class="auth-body">
<div class="auth-container">
    <div class="auth-card">
    {{-- Logo oculto intencionalmente --}}
        <h1 class="auth-title">{{ $title }}</h1>
        <p class="auth-subtitle">Define tu nueva contraseña.</p>
    <form method="POST" action="{{ route('password.update') }}" class="auth-form" id="resetForm" novalidate>
        @csrf
        <input type="hidden" name="token" value="{{ request('token') }}">
        <input type="hidden" name="email" value="{{ request('email') }}">
        <div class="auth-field">
            <label for="password" class="auth-label">Nueva contraseña</label>
            <input id="password" name="password" type="password" required autofocus autocomplete="new-password" class="auth-input" />
            <div class="pw-meter" aria-hidden="true">
                <div class="pw-bar" id="pwBar1"></div>
                <div class="pw-bar" id="pwBar2"></div>
                <div class="pw-bar" id="pwBar3"></div>
            </div>
            <p class="auth-hint" id="pwStrength"></p>
            @error('password')<p class="auth-error server-error">{{ $message }}</p>@enderror
        </div>
        <div class="auth-field">
            <label for="password_confirmation" class="auth-label">Confirmar contraseña</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="auth-input" />
            <p class="auth-hint" id="pwMatch"></p>
        </div>
                <div class="auth-actions">
                        <button type="submit" class="auth-btn" id="submit-btn-reset">Guardar nueva contraseña</button>
                </div>
    </form>
        <div class="auth-row">
                <a href="{{ url('admin/login') }}" class="auth-link">Volver al login</a>
                <button type="button" class="auth-theme-toggle" id="themeToggle" aria-label="Toggle theme">Tema</button>
        </div>
    <div class="auth-footer">&copy; {{ date('Y') }} — Sistema</div>
  </div>
</div>
</body></html>
<script>
    (function(){
        const toggle = document.getElementById('themeToggle');
        if(toggle){
            toggle.addEventListener('click',()=>{
                document.documentElement.classList.toggle('dark');
                localStorage.setItem('auth-theme', document.documentElement.classList.contains('dark') ? 'dark' : 'light');
            });
            const pref = localStorage.getItem('auth-theme');
            if(pref==='dark'){ document.documentElement.classList.add('dark'); }
        }
        const form = document.getElementById('resetForm');
        const btn = document.getElementById('submit-btn-reset');
        const pw = document.getElementById('password');
        const pw2 = document.getElementById('password_confirmation');
        const strengthLabel = document.getElementById('pwStrength');
        const matchLabel = document.getElementById('pwMatch');
        const bars=[document.getElementById('pwBar1'),document.getElementById('pwBar2'),document.getElementById('pwBar3')];
        function evaluateStrength(value){
            let score=0;
            if(value.length>=8) score++;
            if(/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
            if(/\d/.test(value) || /[^A-Za-z0-9]/.test(value)) score++;
            return score;
        }
        function updateStrength(){
            const v=pw.value.trim();
            const s=evaluateStrength(v);
            bars.forEach(b=>b.className='pw-bar');
            if(s>0) bars[0].classList.add('active-1');
            if(s>1) bars[1].classList.add('active-2');
            if(s>2) bars[2].classList.add('active-3');
            let text=''; let cls='auth-hint';
            if(!v){ text=''; }
            else if(s===1){ text='Débil'; cls+=' auth-invalid'; }
            else if(s===2){ text='Media'; }
            else { text='Fuerte'; cls+=' auth-valid'; }
            strengthLabel.textContent=text; strengthLabel.className=cls;
        }
        function updateMatch(){
            const m = pw2.value && pw.value === pw2.value;
            matchLabel.textContent = pw2.value ? (m ? 'Coinciden' : 'No coinciden') : '';
            matchLabel.className = 'auth-hint ' + (m ? 'auth-valid' : 'auth-invalid');
        }
        pw.addEventListener('input',()=>{ updateStrength(); updateMatch(); });
        pw2.addEventListener('input',updateMatch);
        if(form && btn){
            form.addEventListener('submit',e=>{ updateStrength(); updateMatch(); if(strengthLabel.textContent==='Débil'|| matchLabel.classList.contains('auth-invalid')) { e.preventDefault(); return; } btn.dataset.loading='true'; });
        }
    })();
</script>
