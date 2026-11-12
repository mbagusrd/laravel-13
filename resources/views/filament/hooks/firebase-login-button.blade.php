<div class="fi-fo-field-wrp my-4">
    <div class="relative my-4 flex items-center justify-center">
        <div class="border-t border-gray-200 dark:border-white/10 w-full"></div>
        <span class="bg-white dark:bg-gray-900 px-3 text-xs uppercase tracking-wider text-gray-500 font-medium absolute">atau</span>
    </div>

    <button
        type="button"
        id="firebase-google-login-btn"
        class="fi-btn fi-btn-color-gray fi-btn-size-md relative inline-grid grid-flow-col items-center justify-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm ring-1 transition duration-75 focus-visible:ring-2 w-full ring-gray-950/10 dark:ring-white/20 bg-white hover:bg-gray-50 dark:bg-white/5 dark:hover:bg-white/10 text-gray-950 dark:text-white"
        style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;"
    >
        <svg style="width: 18px; height: 18px; min-width: 18px; min-height: 18px; display: inline-block; flex-shrink: 0;" width="18" height="18" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.66-5.17 3.66-9.17z"/>
            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.1-6.72-4.93H1.25v3.15C3.26 21.36 7.34 24 12 24z"/>
            <path fill="#FBBC05" d="M5.28 14.27c-.25-.72-.38-1.49-.38-2.27s.13-1.55.38-2.27V6.58H1.25C.45 8.18 0 9.99 0 12s.45 3.82 1.25 5.42l4.03-3.15z"/>
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.34 0 3.26 2.64 1.25 6.58l4.03 3.15c.95-2.83 3.6-4.98 6.72-4.98z"/>
        </svg>
        <span id="firebase-google-login-text">Masuk dengan Google</span>
    </button>
    <div id="firebase-login-error" style="display: none; color: #ef4444; font-size: 0.8125rem; margin-top: 6px; text-align: center;"></div>
</div>

<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-auth-compat.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const btn = document.getElementById('firebase-google-login-btn');
        const btnText = document.getElementById('firebase-google-login-text');
        const errorDiv = document.getElementById('firebase-login-error');

        if (!btn) return;

        const config = {
            apiKey: "{{ config('services.firebase.api_key') }}",
            authDomain: "{{ config('services.firebase.auth_domain') }}",
            projectId: "{{ config('services.firebase.project_id') }}",
            storageBucket: "{{ config('services.firebase.storage_bucket') }}",
            messagingSenderId: "{{ config('services.firebase.messaging_sender_id') }}",
            appId: "{{ config('services.firebase.app_id') }}"
        };

        function showError(msg) {
            if (errorDiv) {
                errorDiv.textContent = msg;
                errorDiv.style.display = 'block';
            }
        }

        btn.addEventListener('click', function () {
            if (!config.apiKey || !config.projectId) {
                showError('Konfigurasi Firebase belum lengkap di .env (FIREBASE_API_KEY, FIREBASE_PROJECT_ID).');
                return;
            }

            try {
                if (!window.firebase || !window.firebase.auth) {
                    showError('Library Firebase SDK belum siap dimuat.');
                    return;
                }

                if (!firebase.apps.length) {
                    firebase.initializeApp(config);
                }

                const provider = new firebase.auth.GoogleAuthProvider();
                provider.setCustomParameters({ prompt: 'select_account' });

                btn.disabled = true;
                btnText.textContent = 'Menghubungkan ke Google...';
                errorDiv.style.display = 'none';

                firebase.auth().signInWithPopup(provider)
                    .then(async function (result) {
                        btnText.textContent = 'Memverifikasi akun...';
                        const idToken = await result.user.getIdToken();

                        const res = await fetch("{{ route('auth.firebase.verify') }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ id_token: idToken })
                        });

                        const data = await res.json();
                        if (res.ok && data.redirect_url) {
                            window.location.href = data.redirect_url;
                        } else {
                            showError(data.message || 'Gagal autentikasi di server.');
                            btn.disabled = false;
                            btnText.textContent = 'Masuk dengan Google';
                        }
                    })
                    .catch(function (error) {
                        console.error('Firebase Auth Error:', error);
                        if (error.code !== 'auth/popup-closed-by-user') {
                            showError(error.message || 'Gagal login via Google.');
                        }
                        btn.disabled = false;
                        btnText.textContent = 'Masuk dengan Google';
                    });
            } catch (err) {
                console.error(err);
                showError(err.message || 'Terjadi kesalahan sistem.');
                btn.disabled = false;
                btnText.textContent = 'Masuk dengan Google';
            }
        });
    });
</script>
