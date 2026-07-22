function showError(input, msg) {
  const existing = input.parentElement.querySelector('.field-error');
  if (!existing) {
    const el = document.createElement('span');
    el.className = 'field-error';
    el.textContent = msg;
    input.parentElement.insertBefore(el, input.nextSibling);
  }
}

function clearError(input) {
  const el = input.parentElement.querySelector('.field-error');
  if (el) el.remove();
}

function clearAllErrors(form) {
  form.querySelectorAll('.field-error').forEach(e => e.remove());
}

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PASSWORD_RE = /^(?=.*[A-Za-z])(?=.*\d).{6,}$/;

// This file is loaded by login.php AND forgot-password.php, which share the
// .login_form class but not its fields — forgot-password has no password box.
// Both lookups are therefore optional: reading .value off a missing element
// threw here, and because preventDefault() had already run, the exception left
// the forgot-password form permanently unsubmittable.
document.querySelector('.login_form')?.addEventListener('submit', (e) => {
  e.preventDefault();
  clearAllErrors(e.target);

  const email = document.getElementById('email');
  const password = document.getElementById('password');
  let valid = true;

  if (email && !EMAIL_RE.test(email.value.trim())) {
    showError(email, 'Please enter a valid email address');
    valid = false;
  }

  if (password && !PASSWORD_RE.test(password.value)) {
    showError(password, 'Password must be at least 6 characters with letters and numbers');
    valid = false;
  }

  // reset-password.php is the only page with a confirm box. Catching the
  // mismatch here saves a round trip that would otherwise come back as a bare
  // ?status=error with the reason lost.
  const confirm = document.getElementById('password_confirm');
  if (confirm && password && confirm.value !== password.value) {
    showError(confirm, 'Passwords do not match');
    valid = false;
  }

  if (valid) {
    const btn = e.target.querySelector('[type="submit"]');
    if (btn) {
      // Each page states its own busy label — inferring it from which fields
      // exist was guesswork that mislabelled the reset form as "Logging in".
      btn.value = btn.dataset.busy || 'Please wait…';
      btn.disabled = true;
    }
    e.target.submit();
  }
});

['input', 'change'].forEach(ev => {
  document.querySelectorAll('.login_form input').forEach(inp => {
    inp.addEventListener(ev, () => {
      if (inp.value.trim()) clearError(inp);
    });
  });
});

// Password show/hide. Bound per button and scoped to that button's own
// .input-wrap, so a page with more than one password box works — the previous
// version grabbed only the first .pwd-toggle and hardcoded #password and
// #eye-icon, so on reset-password.php the confirm field's toggle would have
// silently operated the field above it.
document.querySelectorAll('.pwd-toggle').forEach((btn) => {
  btn.addEventListener('click', () => {
    const field = btn.closest('.input-wrap')?.querySelector('input[type="password"], input[type="text"]');
    if (!field) return;
    const icon = btn.querySelector('svg');
    const reveal = field.type === 'password';
    field.type = reveal ? 'text' : 'password';
    if (icon) icon.style.opacity = reveal ? '0.45' : '1';
    btn.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
  });
});

// PHP redirect toast with fixed status codes only.
(function () {
  const params = new URLSearchParams(window.location.search);
  const messages = {
    success: { text: 'Logged in successfully.', error: false },
    logged_out: { text: 'You have been logged out.', error: false },
    reset_sent: { text: 'Password reset instructions were sent if the email exists.', error: false },
    invalid: { text: 'Email or password is incorrect.', error: true },
    expired: { text: 'Your session expired. Please log in again.', error: true },
    error: { text: 'Something went wrong. Please try again.', error: true },
  };
  const status = params.get('status');
  const entry = messages[status];
  if (entry) {
    const t = document.createElement('div');
    t.className = 'toast' + (entry.error ? ' toast-error' : '');
    t.textContent = entry.text;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 3500);
    history.replaceState(null, '', window.location.pathname);
  }
})();
