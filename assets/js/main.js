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

  var fields = form.querySelectorAll("input, select, textarea");
  fields.forEach(function (f) {
    f.addEventListener("blur", function () { validateField(f); });
    f.addEventListener("input", function () {
      if (f.getAttribute("aria-invalid") === "true") { validateField(f); }
    });
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var ok = true;
    fields.forEach(function (f) { if (!validateField(f)) { ok = false; } });
    if (!ok) {
      if (status) {
        status.className = "form-status ng";
        status.textContent = "入力内容をご確認ください。";
      }
      var firstBad = form.querySelector('[aria-invalid="true"]');
      if (firstBad) { firstBad.focus(); }
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
          "お問い合わせありがとうございます。送信機能を有効にするには、フォーム送信先（Formspree 等）の設定が必要です。詳しくは README をご覧ください。";
      }
      form.reset();
      return;
    }

    if (submitBtn) { submitBtn.textContent = "送信中..."; submitBtn.disabled = true; }
    fetch(endpoint, {
      method: "POST",
      body: new FormData(form),
      headers: { Accept: "application/json" }
    })
      .then(function (res) {
        if (res.ok) {
          status.className = "form-status ok";
          status.textContent = "お問い合わせを受け付けました。担当者より追ってご連絡いたします。";
          form.reset();
        } else {
          throw new Error("送信に失敗しました");
        }
      })
      .catch(function () {
        status.className = "form-status ng";
        status.textContent =
          "送信に失敗しました。お手数ですが、お電話またはメールにてご連絡ください。";
      })
      .finally(function () {
        if (submitBtn) { submitBtn.textContent = "送信する"; submitBtn.disabled = false; }
      });
  });
})();
