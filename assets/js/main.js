/* =========================================================
   Goen. 結婚相談所  —  Interactions
   ========================================================= */
(function () {
  "use strict";

  /* ---- Mobile navigation ---- */
  var toggle = document.querySelector(".nav__toggle");
  var links = document.querySelector(".nav__links");
  if (toggle && links) {
    toggle.addEventListener("click", function () {
      var open = links.classList.toggle("is-open");
      toggle.classList.toggle("is-open", open);
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
    links.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        links.classList.remove("is-open");
        toggle.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ---- Scroll reveal ---- */
  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window && reveals.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add("is-visible");
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.12 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add("is-visible"); });
  }

  /* ---- Footer year ---- */
  var y = document.querySelector("[data-year]");
  if (y) { y.textContent = new Date().getFullYear(); }

  /* ---- Contact form ---- */
  var form = document.getElementById("contact-form");
  if (!form) { return; }
  var status = document.getElementById("form-status");
  var loadedAt = Date.now();
  var GENERIC_ERROR = "送信に失敗しました。お手数ですが、お電話またはメールにてご連絡ください。";
  var LIMIT_ERROR = "短時間に多くの送信がありました。しばらく時間をおいてから再度お試しください。";

  function scrollToStatus() {
    if (status && typeof status.scrollIntoView === "function") {
      status.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  function showError(text, scroll) {
    if (!status) { return; }
    status.className = "form-status ng";
    status.textContent = text;
    if (scroll) { scrollToStatus(); }
  }

  function showSuccess() {
    if (!status) { return; }
    status.className = "form-status ok form-status--rich";
    status.innerHTML =
      '<span class="form-status__icon" aria-hidden="true">' +
        '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.6"/><path d="M7.5 12.5l3 3 6-6.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
      '</span>' +
      '<div class="form-status__body">' +
        '<p class="form-status__title">お問い合わせを受け付けました</p>' +
        '<p>この度はお問い合わせいただき、誠にありがとうございます。内容を確認のうえ、<b>2〜3営業日以内</b>に担当者よりご連絡いたします。</p>' +
        '<p class="form-status__sub">お急ぎの場合は、<span class="form-status__nowrap">お電話（<a href="tel:07076035050">070-7603-5050</a>）</span>でも承っております。ご連絡まで今しばらくお待ちください。</p>' +
      '</div>';
    scrollToStatus();
  }

  function setError(field, message) {
    var msg = form.querySelector('[data-error-for="' + field.name + '"]');
    if (msg) { msg.textContent = message || ""; }
    field.setAttribute("aria-invalid", message ? "true" : "false");
  }

  function validateField(field) {
    var v = (field.value || "").trim();
    if (field.hasAttribute("required") && !v) {
      setError(field, "ご入力をお願いいたします");
      return false;
    }
    if (field.type === "email" && v && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) {
      setError(field, "メールアドレスの形式をご確認ください");
      return false;
    }
    if (field.type === "tel" && v && !/^[0-9+\-() ]{8,20}$/.test(v)) {
      setError(field, "電話番号の形式をご確認ください");
      return false;
    }
    if (field.type === "checkbox" && field.hasAttribute("required") && !field.checked) {
      setError(field, "ご同意をお願いいたします");
      return false;
    }
    setError(field, "");
    return true;
  }

  function focusFirstInvalid() {
    var firstBad = form.querySelector('[aria-invalid="true"]');
    if (firstBad) { firstBad.focus(); }
  }

  // 検証対象は利用者が入力する欄だけ（hidden とハニーポットは除く）
  var fields = Array.prototype.filter.call(
    form.querySelectorAll("input, select, textarea"),
    function (f) { return f.type !== "hidden" && !f.closest(".form__hp"); }
  );
  fields.forEach(function (f) {
    f.addEventListener("blur", function () { validateField(f); });
    f.addEventListener("input", function () {
      if (f.getAttribute("aria-invalid") === "true") { validateField(f); }
    });
  });

  /* JS 無効時の通常送信は contact.php が ?sent=1 / ?error=… 付きでこのページへ戻すので、その結果を表示 */
  if (window.URLSearchParams && window.location.search) {
    var query = new URLSearchParams(window.location.search);
    if (query.get("sent") === "1") {
      showSuccess();
    } else if (query.has("error")) {
      var key = query.get("error");
      showError(key === "limit" ? LIMIT_ERROR :
                key === "invalid" ? "入力内容をご確認のうえ、もう一度送信してください。" :
                key === "js" ? "送信できませんでした。ページを再読み込みしてもう一度お試しいただくか、お電話・メールにてご連絡ください。" :
                GENERIC_ERROR, true);
    }
    if ((query.has("sent") || query.has("error")) && window.history && history.replaceState) {
      history.replaceState(null, "", window.location.pathname + window.location.hash);
    }
  }

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var ok = true;
    fields.forEach(function (f) { if (!validateField(f)) { ok = false; } });
    if (!ok) {
      showError("入力内容をご確認ください。", false);
      focusFirstInvalid();
      return;
    }

    var submitBtn = form.querySelector(".form__submit");
    var endpoint = form.getAttribute("action");
    var usingPlaceholder = !endpoint || endpoint.indexOf("your-form-id") !== -1;

    if (usingPlaceholder) {
      // No form backend configured yet — demo confirmation only.
      if (status) {
        status.className = "form-status ok";
        status.textContent =
          "お問い合わせありがとうございます。送信機能を有効にするには、フォーム送信先の設定が必要です。詳しくは README をご覧ください。";
      }
      form.reset();
      return;
    }

    // ページ表示から送信までの所要時間（contact.php の bot 判定用）
    if (form.elements.elapsed) { form.elements.elapsed.value = String(Date.now() - loadedAt); }

    if (submitBtn) { submitBtn.textContent = "送信中..."; submitBtn.disabled = true; }
    fetch(endpoint, {
      method: "POST",
      body: new FormData(form),
      headers: { Accept: "application/json" }
    })
      .then(function (res) {
        return res.json().catch(function () { return null; }).then(function (data) {
          if (res.ok) {
            showSuccess();
            form.reset();
            return;
          }
          // contact.php が返す項目別エラー（{ errors: { name: "...", ... } }）
          if (data && data.errors && typeof data.errors === "object" && !Array.isArray(data.errors)) {
            Object.keys(data.errors).forEach(function (name) {
              var field = form.elements[name];
              if (field && typeof field.setAttribute === "function") { setError(field, data.errors[name]); }
            });
            showError("入力内容をご確認ください。", false);
            focusFirstInvalid();
            return;
          }
          showError((data && data.message) || GENERIC_ERROR, true);
        });
      })
      .catch(function () {
        showError(GENERIC_ERROR, true);
      })
      .finally(function () {
        if (submitBtn) { submitBtn.textContent = "送信する"; submitBtn.disabled = false; }
      });
  });
})();
