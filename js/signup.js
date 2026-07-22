function showError(input, msg) {
  const container = input.closest('label') || input.parentElement;
  const existing = container.querySelector('.field-error');
  if (!existing) {
    const el = document.createElement('span');
    el.className = 'field-error';
    el.textContent = msg;
    container.appendChild(el);
  }
}

function clearError(input) {
  const container = input.closest('label') || input.parentElement;
  const el = container.querySelector('.field-error');
  if (el) el.remove();
}

function clearAllErrors() {
  document.querySelectorAll('.field-error').forEach(e => e.remove());
}

const EMAIL_RE    = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_RE    = /^(?:\+961|00961|0)[0-9]{6,11}$/;
const URL_RE      = /^https?:\/\/.+/i;
const PASSWORD_RE = /^(?=.*[A-Za-z])(?=.*\d).{6,}$/;

let currentContactMethod = 'email';
let chosenRole = '';

// ==========================================
// 1. PHONE / EMAIL TOGGLE (Step 1)
// ==========================================
document.querySelector('.phone_btn').addEventListener('click', () => {
    document.querySelector('.contact-email').style.display = 'none';
    document.querySelector('.contact-phone').style.display = 'flex';
    document.querySelector('.slide').style.transform = 'translateX(0%)';
    document.querySelector('.phone_btn').style.color = 'royalblue';
    document.querySelector('.email_btn').style.color = 'rgba(84, 84, 84,0.8)';
    currentContactMethod = 'phone';
    clearAllErrors();
});

document.querySelector('.email_btn').addEventListener('click', () => {
    document.querySelector('.contact-email').style.display = 'flex';
    document.querySelector('.contact-phone').style.display = 'none';
    document.querySelector('.slide').style.transform = 'translateX(100%)';
    document.querySelector('.phone_btn').style.color = 'rgba(84, 84, 84,0.8)';
    document.querySelector('.email_btn').style.color = 'royalblue';
    currentContactMethod = 'email';
    clearAllErrors();
});

// ==========================================
// 2. IMAGE UPLOAD PREVIEWS
// ==========================================
function setPreview(previewEl, file) {
    if (!file) return;
    const prev = previewEl.dataset.objUrl;
    if (prev) URL.revokeObjectURL(prev);
    const url = URL.createObjectURL(file);
    previewEl.dataset.objUrl = url;
    previewEl.src = url;
}

document.querySelector('#profile_pic_upload').addEventListener('change', function(event) {
    setPreview(document.querySelector('#profile_preview'), event.target.files[0]);
});

document.querySelector('#organisation_pic_upload').addEventListener('change', function(event) {
    setPreview(document.querySelector('#organisation_preview'), event.target.files[0]);
});

// ==========================================
// 3. BIO CHARACTER COUNTERS
// ==========================================
const basicBio = document.getElementById('user_description_basic');
const basicCounter = document.getElementById('basic-bio-counter');
const orgBio = document.getElementById('user_description_org');
const orgCounter = document.getElementById('org-bio-counter');

basicBio?.addEventListener('input', () => {
    if (basicCounter) basicCounter.textContent = `${basicBio.value.length} / 250`;
});
orgBio?.addEventListener('input', () => {
    if (orgCounter) orgCounter.textContent = `${orgBio.value.length} / 250`;
});

// ==========================================
// 4. VALIDATION HELPERS
// ==========================================
function validateStep1() {
    let valid = true;
    clearAllErrors();

    const fullNameInput = document.querySelector('[name="full_name"]');
    if (!fullNameInput.value.trim() || fullNameInput.value.trim().length < 2) {
        showError(fullNameInput, 'Enter your full name');
        valid = false;
    }

    const emailInput = document.querySelector('.contact-email input');
    const phoneInput = document.querySelector('.contact-phone input');

    if (currentContactMethod === 'email') {
        if (!EMAIL_RE.test(emailInput.value.trim())) {
            showError(emailInput, 'Enter a valid email address');
            valid = false;
        }
    } else {
        if (!PHONE_RE.test(phoneInput.value.trim())) {
            showError(phoneInput, 'Enter a valid Lebanese phone number');
            valid = false;
        }
    }

    const pwdInput  = document.getElementById('user_password');
    const confInput = document.getElementById('user_password_confirm');
    if (!PASSWORD_RE.test(pwdInput.value)) {
        showError(pwdInput, 'Password must be at least 6 characters with letters and numbers');
        valid = false;
    } else if (pwdInput.value !== confInput.value) {
        showError(confInput, 'Passwords do not match');
        valid = false;
    }

    const birthday = document.querySelector('input[name="user_birthdate"]');
    if (!birthday.value) {
        showError(birthday, 'Please select your birth date');
        valid = false;
    } else {
        const dob = new Date(birthday.value);
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        if (age < 10) {
            showError(birthday, 'You must be at least 10 years old');
            valid = false;
        }
    }

    const genderChecked = Array.from(document.querySelectorAll('input[name="user_gender"]')).some(r => r.checked);
    if (!genderChecked) {
        const genderGroup = document.querySelector('.gender-group');
        if (!genderGroup.querySelector('.field-error')) {
            const el = document.createElement('span');
            el.className = 'field-error field-error--centered';
            el.textContent = 'Please select your gender';
            genderGroup.appendChild(el);
        }
        valid = false;
    }

    return valid;
}

function validateBasicInfo() {
    let valid = true;
    clearAllErrors();

    // Bio is optional. The server already stores NULL for a blank one, and
    // nothing downstream needs it — only enforce the minimum once the user has
    // actually written something.
    const bio = document.getElementById('user_description_basic');
    const bioText = bio.value.trim();
    if (bioText.length > 0 && bioText.length < 10) {
        showError(bio, 'Either write at least 10 characters or leave the bio empty');
        valid = false;
    }

    const location = document.getElementById('user_location');
    if (location.value.trim().length < 2) {
        showError(location, 'Please enter your location');
        valid = false;
    }

    return valid;
}

function validateOrgInfo() {
    let valid = true;
    clearAllErrors();

    // Optional, same as the personal bio above.
    const bio = document.getElementById('user_description_org');
    const bioText = bio.value.trim();
    if (bioText.length > 0 && bioText.length < 10) {
        showError(bio, 'Either write at least 10 characters or leave the bio empty');
        valid = false;
    }

    const contact = document.getElementById('user_contact');
    if (!PHONE_RE.test(contact.value.trim())) {
        showError(contact, 'Enter a valid Lebanese phone number');
        valid = false;
    }

    const website = document.getElementById('user_website');
    if (website.value.trim() && !URL_RE.test(website.value.trim())) {
        showError(website, 'Enter a valid URL (https://...)');
        valid = false;
    }

    const organisationName = document.getElementById('organisation_name');
    if (!organisationName.value.trim() || organisationName.value.trim().length < 2) {
        showError(organisationName, 'Enter the registered organisation name');
        valid = false;
    }

    return valid;
}

// ==========================================
// 5. NAVIGATION & ROLE LOGIC
// ==========================================
const cards = document.querySelectorAll('.image-card-container');
const step1 = document.querySelector('.step_1');
const roleCardsSection = document.querySelector('.role_cards');
const roleDropdown = document.querySelector('#role');
const basicinfo = document.querySelector('.basicinfo');
const orginfo = document.querySelector('.organisation_role');

const continueBtn = document.querySelector('.role-continue-btn');

function advanceToStep3() {
    roleCardsSection.style.display = 'none';
    if (chosenRole === 'basic') {
        basicinfo.style.display = 'flex';
        orginfo.style.display = 'none';
    } else {
        orginfo.style.display = 'flex';
        basicinfo.style.display = 'none';
    }
    window.scrollTo(0, 0);
}

// Card click — desktop selects (Continue advances); mobile keeps the auto-advance behavior
cards.forEach(card => {
    card.addEventListener('click', () => {
        const img1 = card.querySelector('.image_card1');
        const img2 = card.querySelector('.image_card2');
        const img3 = card.querySelector('.image_card3');

        if (img1) { chosenRole = 'basic'; roleDropdown.value = 'user'; }
        else if (img2) { chosenRole = 'basic'; roleDropdown.value = 'beneficiary'; }
        else if (img3) { chosenRole = 'org'; roleDropdown.value = 'organisation'; }

        if (window.innerWidth >= 768) {
            // Desktop: mark selected, enable Continue, don't advance
            cards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            if (continueBtn) continueBtn.disabled = false;
        } else {
            // Mobile: original auto-advance behavior
            advanceToStep3();
        }
    });
});

// Continue button (desktop only — hidden on mobile)
continueBtn?.addEventListener('click', () => {
    if (!chosenRole) return;
    advanceToStep3();
});

// STEP 1 → NEXT (always go to role cards)
document.querySelector('.nextstep_2').addEventListener('click', () => {
    if (!validateStep1()) return;
    step1.style.display = 'none';
    roleCardsSection.style.display = 'flex';
    window.scrollTo(0, 0);
});

// ==========================================
// 6. BACK BUTTONS
// ==========================================

// Smart .back-home: walks back step 3 -> step 2 -> step 1 -> index.html
document.querySelector('.back-home')?.addEventListener('click', (e) => {
    if (basicinfo.style.display === 'flex' || orginfo.style.display === 'flex') {
        e.preventDefault();
        basicinfo.style.display = 'none';
        orginfo.style.display = 'none';
        chosenRole = '';
        roleDropdown.value = '';
        cards.forEach(c => c.classList.remove('selected'));
        if (continueBtn) continueBtn.disabled = true;
        roleCardsSection.style.display = 'flex';
        window.scrollTo(0, 0);
    } else if (roleCardsSection.style.display === 'flex') {
        e.preventDefault();
        roleCardsSection.style.display = 'none';
        chosenRole = '';
        cards.forEach(c => c.classList.remove('selected'));
        if (continueBtn) continueBtn.disabled = true;
        step1.style.display = 'flex';
        window.scrollTo(0, 0);
    }
    // else: on step 1, let the default <a href="../index.html"> navigation happen
});

// ==========================================
// 7. FORM SUBMIT → VALIDATE + SEND TO PHP
// ==========================================
document.querySelector('.form_submit').addEventListener('submit', (e) => {
    e.preventDefault();

    const isBasic = basicinfo.style.display === 'flex';
    const isOrg = orginfo.style.display === 'flex';

    if (isBasic && !validateBasicInfo()) return;
    if (isOrg && !validateOrgInfo()) return;

    const btn = e.target.querySelector('[type="submit"]');
    btn.value = 'Submitting...';
    btn.disabled = true;
    e.target.submit();
});

// ==========================================
// 8. CLEAR ERRORS ON INPUT
// ==========================================
document.querySelectorAll('input, textarea, select').forEach(el => {
    el.addEventListener('input', () => {
        if (el.value.trim()) clearError(el);
    });
});

// 9. PASSWORD STRENGTH BAR
// ==========================================
(function() {
    const pwdInput = document.getElementById('user_password');
    const bars = document.querySelectorAll('.pwd-strength-bar span');
    const label = document.querySelector('.pwd-strength-label');
    if (!pwdInput || !bars.length) return;

    const levels = [
        { min: 0,  color: '',          text: '' },
        { min: 1,  color: '#ef4444',   text: 'Too weak' },
        { min: 6,  color: '#f59e0b',   text: 'Fair' },
        { min: 8,  color: '#16a34a',   text: 'Strong' },
        { min: 10, color: '#16a34a',   text: 'Very strong' },
    ];

    pwdInput.addEventListener('input', () => {
        const val = pwdInput.value;
        let score = 0;
        if (val.length >= 6)  score++;
        if (val.length >= 8)  score++;
        if (/[A-Za-z]/.test(val) && /\d/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        const color = score === 0 ? '' : score === 1 ? '#ef4444' : score === 2 ? '#f59e0b' : '#16a34a';
        const texts = ['', 'Weak', 'Fair', 'Strong', 'Very strong'];

        bars.forEach((bar, i) => {
            bar.style.background = i < score ? color : '';
        });
        if (label) label.textContent = val.length ? texts[score] || '' : '';
        if (label) label.style.color = color;
    });
})();

// 10. PASSWORD SHOW/HIDE TOGGLES
// ==========================================
function makePwdToggle(inputId, btnId) {
    document.getElementById(btnId)?.addEventListener('click', () => {
        const inp = document.getElementById(inputId);
        const btn = document.getElementById(btnId);
        if (!inp) return;
        const shouldShow = inp.type === 'password';
        inp.type = shouldShow ? 'text' : 'password';
        btn?.setAttribute('aria-label', shouldShow ? 'Hide password' : 'Show password');
    });
}
makePwdToggle('user_password', 'pwd-toggle-1');
makePwdToggle('user_password_confirm', 'pwd-toggle-2');

