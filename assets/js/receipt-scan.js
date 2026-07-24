// Inkwell — receipt OCR auto-fill.
// Shared by my-billing.php and create-school.php (and any other page using
// the same #proof_image / #reference_no field IDs). When the user picks a
// receipt screenshot, we run it through Tesseract.js in the browser, try to
// spot a reference/transaction number in the recognized text, and fill
// #reference_no automatically so the user doesn't have to type it in.
//
// Nothing is uploaded anywhere for this step — OCR runs entirely client-side
// before the form is ever submitted.
(function () {
  var TESSERACT_SRC = 'https://cdnjs.cloudflare.com/ajax/libs/tesseract.js/5.0.4/tesseract.min.js';
  var tesseractLoadPromise = null;

  function loadTesseract() {
    if (window.Tesseract) return Promise.resolve(window.Tesseract);
    if (tesseractLoadPromise) return tesseractLoadPromise;
    tesseractLoadPromise = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = TESSERACT_SRC;
      script.async = true;
      script.onload = function () { resolve(window.Tesseract); };
      script.onerror = function () { reject(new Error('Could not load OCR library')); };
      document.head.appendChild(script);
    });
    return tesseractLoadPromise;
  }

  // Look for a reference/transaction number inside the raw OCR text.
  // Tries labeled patterns first ("Ref No.", "Reference:", "Transaction ID")
  // since those are the most reliable, then falls back to the longest
  // digit-heavy run on the page (GCash/Maya refs are typically 12-13 digits,
  // sometimes displayed in groups like "1234 567 890123").
  function extractReferenceNumber(text) {
    if (!text) return null;
    var cleanedLines = text.split('\n').map(function (l) { return l.trim(); }).filter(Boolean);
    var joined = cleanedLines.join('\n');

    var labeledPatterns = [
      /ref(?:erence)?\.?\s*(?:no\.?|number|#)?\s*[:\-]?\s*([A-Z0-9][A-Z0-9 ]{4,22}[A-Z0-9])/i,
      /trans(?:action)?\.?\s*(?:id|no\.?|number)?\s*[:\-]?\s*([A-Z0-9][A-Z0-9 ]{4,22}[A-Z0-9])/i,
      /txn\.?\s*(?:id|no\.?)?\s*[:\-]?\s*([A-Z0-9][A-Z0-9 ]{4,22}[A-Z0-9])/i
    ];

    for (var i = 0; i < labeledPatterns.length; i++) {
      var m = joined.match(labeledPatterns[i]);
      if (m && m[1]) {
        var candidate = normalizeCandidate(m[1]);
        if (isPlausible(candidate)) return candidate;
      }
    }

    // Fallback: longest run of digits/spaces (>= 9 digits total) anywhere in the text.
    var digitRuns = joined.match(/(?:\d[\d ]{0,3}){3,}\d/g) || [];
    var best = null;
    digitRuns.forEach(function (run) {
      var stripped = run.replace(/\s+/g, '');
      if (stripped.length >= 9 && stripped.length <= 20) {
        if (!best || stripped.length > best.length) best = stripped;
      }
    });
    if (best) return best;

    return null;
  }

  function normalizeCandidate(raw) {
    var trimmed = raw.trim();
    var digitsOnly = trimmed.replace(/\s+/g, '');
    // If it's all digits once spaces are removed, collapse the spaces
    // (receipts often chunk long numbers into groups for readability).
    if (/^\d+$/.test(digitsOnly)) return digitsOnly;
    return trimmed;
  }

  function isPlausible(candidate) {
    if (!candidate) return false;
    if (candidate.length < 6 || candidate.length > 40) return false;
    // Needs at least a few digits to be a believable payment reference.
    var digitCount = (candidate.match(/\d/g) || []).length;
    return digitCount >= 4;
  }

  function setStatus(statusEl, message, tone) {
    if (!statusEl) return;
    statusEl.textContent = message;
    statusEl.style.display = message ? 'block' : 'none';
    statusEl.style.color = tone === 'ok' ? 'var(--pine)' : tone === 'warn' ? 'var(--clay)' : 'var(--ink-dim)';
  }

  function flashField(inputEl) {
    if (!inputEl) return;
    var group = inputEl.closest('.icon-input-group');
    var target = group || inputEl;
    target.style.transition = 'box-shadow 0.2s ease';
    target.style.boxShadow = '0 0 0 2px var(--pine)';
    setTimeout(function () { target.style.boxShadow = ''; }, 1400);
  }

  function initOne(fileInput, refInput) {
    if (!fileInput || !refInput) return;

    var group = fileInput.closest('.icon-input-group') || fileInput.parentNode;
    var statusEl = document.createElement('p');
    statusEl.className = 'admin-sub proof-scan-status';
    statusEl.style.margin = '6px 0 0';
    statusEl.style.fontSize = '0.85rem';
    statusEl.style.display = 'none';
    group.insertAdjacentElement('afterend', statusEl);

    fileInput.addEventListener('change', function () {
      var file = fileInput.files && fileInput.files[0];
      if (!file) return;

      if (!/^image\//.test(file.type)) {
        setStatus(statusEl, '⚠️ That file isn\u2019t an image — you\u2019ll need to type the reference number in manually.', 'warn');
        return;
      }

      var refHadValue = refInput.value.trim().length > 0;
      setStatus(statusEl, '🔍 Scanning receipt for a reference number\u2026', null);

      loadTesseract()
        .then(function (Tesseract) {
          return Tesseract.recognize(file, 'eng');
        })
        .then(function (result) {
          var text = result && result.data ? result.data.text : '';
          var found = extractReferenceNumber(text);

          if (found) {
            if (refHadValue) {
              setStatus(statusEl, '✅ Found ' + found + ' on the receipt — replaced what was in the field (check it looks right).', 'ok');
            } else {
              setStatus(statusEl, '✅ Found reference number automatically: ' + found, 'ok');
            }
            refInput.value = found;
            flashField(refInput);
          } else {
            setStatus(statusEl, '🤔 Couldn\u2019t spot a reference number on that image — please type it in.', 'warn');
          }
        })
        .catch(function () {
          setStatus(statusEl, '⚠️ Scan failed — please type the reference number in manually.', 'warn');
        });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initOne(document.getElementById('proof_image'), document.getElementById('reference_no'));
  });
})();
