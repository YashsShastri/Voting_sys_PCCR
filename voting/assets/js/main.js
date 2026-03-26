/**
 * PCCOER Voting System - Main JavaScript
 */

// ─── Password Toggle ──────────────────────────────────────────
function togglePassword(inputId) {
  const input = document.getElementById(inputId);
  const eye   = document.getElementById('eye-' + inputId);
  if (!input) return;
  if (input.type === 'password') {
    input.type = 'text';
    if (eye) { eye.classList.remove('fa-eye'); eye.classList.add('fa-eye-slash'); }
  } else {
    input.type = 'password';
    if (eye) { eye.classList.remove('fa-eye-slash'); eye.classList.add('fa-eye'); }
  }
}

// ─── Password Strength Indicator ─────────────────────────────
const pwField = document.getElementById('reg-password');
const pwStrengthEl = document.getElementById('password-strength');
if (pwField && pwStrengthEl) {
  pwField.addEventListener('input', () => {
    const val = pwField.value;
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const labels = ['Too short','Weak','Fair','Good','Strong'];
    const colors = ['#ef4444','#ef4444','#f59e0b','#06b6d4','#10b981'];
    pwStrengthEl.innerHTML = `<span style="color:${colors[score]}">Strength: ${labels[score]}</span>`;
  });
}

// ─── Mobile Navigation ───────────────────────────────────────
const hamburger = document.getElementById('hamburger');
const navLinks  = document.querySelector('.nav-links');
if (hamburger && navLinks) {
  hamburger.addEventListener('click', () => {
    navLinks.classList.toggle('open');
  });
}

// ─── Auto-dismiss Alerts ─────────────────────────────────────
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => {
    alert.style.transition = 'opacity 0.5s ease, margin 0.5s ease';
    alert.style.opacity = '0';
    alert.style.margin = '0';
    setTimeout(() => alert.remove(), 500);
  }, 5000);
});

// ─── Candidate Card Selection Highlight ──────────────────────
document.querySelectorAll('.candidate-card input[type="radio"]').forEach(radio => {
  radio.addEventListener('change', () => {
    document.querySelectorAll('.candidate-card').forEach(card => card.classList.remove('selected'));
    if (radio.checked) radio.closest('.candidate-card').classList.add('selected');
  });
});

// ─── Progress Bar Animation on Load ─────────────────────────
document.querySelectorAll('[data-progress]').forEach(bar => {
  const pct = bar.getAttribute('data-progress');
  setTimeout(() => { bar.style.width = pct + '%'; }, 100);
});

// ─── Confirm dangerous actions ───────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
  });
});

// ─── Live election refresh countdown ─────────────────────────
const countdownEl = document.getElementById('refreshCountdown');
if (countdownEl) {
  let t = 30;
  setInterval(() => {
    countdownEl.textContent = --t;
    if (t <= 0) t = 30;
  }, 1000);
}

// ─── Table row click for mobile friendliness ─────────────────
document.querySelectorAll('.data-table tbody tr[data-href]').forEach(row => {
  row.style.cursor = 'pointer';
  row.addEventListener('click', () => window.location = row.dataset.href);
});

console.log('🗳️ PCCOER Voting System loaded.');
