// Language Toggle Function
function toggleLanguage(lang) {
    const elements = document.querySelectorAll('.lang-en, .lang-hi');
    elements.forEach(el => {
        el.style.display = el.classList.contains('lang-' + lang) ? '' : 'none';
    });

    // Update active button
    document.querySelectorAll('.lang-toggle button').forEach(btn => {
        btn.classList.remove('active');
    });
    const activeBtn = document.getElementById('lang-' + lang);
    if (activeBtn) activeBtn.classList.add('active');

    // Save preference
    localStorage.setItem('preferred_language', lang);
}

// Load saved language preference
document.addEventListener('DOMContentLoaded', function() {
    const savedLang = localStorage.getItem('preferred_language') || 'en';
    if (document.getElementById('lang-' + savedLang)) {
        toggleLanguage(savedLang);
    }
});

// Smooth Scroll
function scrollToForm() {
    const formSection = document.getElementById('form-section');
    if (formSection) {
        formSection.scrollIntoView({ behavior: 'smooth' });
    }
}

// Form Validation
function validatePhone(phone) {
    const phoneRegex = /^[0-9]{10}$/;
    return phoneRegex.test(phone);
}

function validateEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Copy to Clipboard with fallback
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => alert('Copied to clipboard!'))
            .catch(err => fallbackCopy(text));
    } else {
        fallbackCopy(text);
    }
}

function fallbackCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';  // avoid scrolling to bottom
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    try {
        document.execCommand('copy');
        alert('Copied to clipboard!');
    } catch (err) {
        alert('Failed to copy text');
    }
    document.body.removeChild(textarea);
}

// Debounce utility
function debounce(fn, delay) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => fn.apply(this, args), delay);
    };
}

// Table Search and Filter
function filterTable() {
    const searchValue = document.getElementById('search')?.value.toLowerCase() || '';
    const scoreFilter = document.getElementById('filter-score')?.value || '';
    const statusFilter = document.getElementById('filter-status')?.value.toLowerCase() || '';

    const table = document.querySelector('.leads-table tbody');
    if (!table) return;

    const rows = table.getElementsByTagName('tr');

    for (let row of rows) {
        const name = row.cells[0]?.textContent.toLowerCase() || '';
        const phone = row.cells[1]?.textContent.toLowerCase() || '';
        const score = row.cells[3]?.textContent || '';
        const status = row.cells[4]?.textContent.toLowerCase() || '';

        const matchesSearch = name.includes(searchValue) || phone.includes(searchValue);
        const matchesScore = !scoreFilter || score.includes(scoreFilter);
        const matchesStatus = !statusFilter || status.includes(statusFilter);

        row.style.display = (matchesSearch && matchesScore && matchesStatus) ? '' : 'none';
    }
}

// Attach debounced event listeners for filtering
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(filterTable, 300));
    }
    const scoreFilter = document.getElementById('filter-score');
    if (scoreFilter) {
        scoreFilter.addEventListener('change', filterTable);
    }
    const statusFilter = document.getElementById('filter-status');
    if (statusFilter) {
        statusFilter.addEventListener('change', filterTable);
    }
});

// Loading Indicator
function showLoading() {
    if (document.getElementById('loading-overlay')) return; // prevent duplicates
    const loader = document.createElement('div');
    loader.id = 'loading-overlay';
    loader.innerHTML = '<div class="spinner"></div>';
    loader.style.cssText = `
        position: fixed;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    `;
    document.body.appendChild(loader);
}

function hideLoading() {
    const loader = document.getElementById('loading-overlay');
    if (loader) loader.remove();
}

// Auto-save form data to localStorage (handles inputs, selects, checkboxes, radios)
function autoSaveForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    // Load saved data
    const savedData = localStorage.getItem(formId);
    if (savedData) {
        const data = JSON.parse(savedData);
        Object.keys(data).forEach(key => {
            const input = form.elements[key];
            if (!input) return;
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = data[key];
            } else {
                input.value = data[key];
            }
        });
    }

    // Save on input/change
    form.addEventListener('input', saveData);
    form.addEventListener('change', saveData);

    function saveData() {
        const formData = {};
        for (let element of form.elements) {
            if (!element.name) continue;
            if (element.type === 'checkbox' || element.type === 'radio') {
                formData[element.name] = element.checked;
            } else {
                formData[element.name] = element.value;
            }
        }
        localStorage.setItem(formId, JSON.stringify(formData));
    }

    // Clear on submit
    form.addEventListener('submit', () => {
        localStorage.removeItem(formId);
    });
}

// Initialize auto-save for all forms
document.addEventListener('DOMContentLoaded', function() {
    ['form-a', 'form-b', 'form-c', 'form-d'].forEach(autoSaveForm);
});