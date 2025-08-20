@php($title = 'Recuperar contraseña')
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/><title>{{ $title }}</title>@vite(['resources/css/auth-public.css','resources/js/app.js'])</head><body class="auth-body">
<div class="auth-container">
    <div class="auth-card">
        {{-- Logo oculto intencionalmente --}}
        <h1 class="auth-title">{{ $title }}</h1>
        <p class="auth-subtitle">Ingresa tu correo y te enviaremos un enlace para restablecerla.</p>
    @if (session('status'))
        <p class="auth-status">{{ session('status') }}</p>
    @endif
    <form method="POST" action="{{ route('password.email') }}" class="auth-form" id="forgotForm" novalidate>
        @csrf
        <div class="auth-field">
            <label for="email" class="auth-label">Correo</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="email" class="auth-input" />
            <p class="auth-hint" id="emailHint"></p>
            @error('email')<p class="auth-error server-error">{{ $message }}</p>@enderror
        </div>
                <div class="auth-actions">
                        <button type="submit" class="auth-btn" id="submit-btn-forgot">Enviar enlace</button>
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
            const form = document.getElementById('forgotForm');
            const btn = document.getElementById('submit-btn-forgot');
            const emailInput = document.getElementById('email');
            const emailHint = document.getElementById('emailHint');
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
            function validateEmail(){
                    const val = emailInput.value.trim();
                    if(!val){ emailHint.textContent='Campo requerido'; emailHint.className='auth-error'; return false; }
                    if(!emailRegex.test(val)){ emailHint.textContent='Formato inválido'; emailHint.className='auth-error'; return false; }
                    emailHint.textContent='✓ OK'; emailHint.className='auth-hint auth-valid'; return true; }
            emailInput.addEventListener('input', validateEmail);
            if(form && btn){
                form.addEventListener('submit',e=>{ if(!validateEmail()){ e.preventDefault(); return; } btn.dataset.loading='true'; });
            }
    })();
</script>
