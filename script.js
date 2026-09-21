/* =========================================================
   İsteğe Bağlı Kürtaj – Landing Page
   Form gönderimi, doğrulama, telefon maskesi ve UI etkileşimleri
   ========================================================= */

(function () {
  "use strict";

  /* ---------- AYARLAR (burayı düzenleyin) ---------- */
  var CONFIG = {
    // Lead'lerin gönderileceği adres:
    formEndpoint: "ajax.php",

    // WhatsApp fallback numarası (ülke kodu ile, boşluksuz)
    whatsappNumber: "902163977580",

    // Kampanya adı (dataLayer / endpoint'e gönderilir)
    campaign: "istege-bagli-kurtaj"
  };

  /* ---------- Yardımcılar ---------- */
  function qs(sel, root) { return (root || document).querySelector(sel); }
  function qsa(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function track(eventName, data) {
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push(Object.assign({ event: eventName, campaign: CONFIG.campaign }, data || {}));
  }

  /* ---------- Telefon maskesi: 05xx xxx xx xx ---------- */
  function normalizePhoneDigits(value) {
    var digits = String(value || "").replace(/\D/g, "");
    if (digits.indexOf("90") === 0 && digits.length > 10) digits = digits.slice(2); // +90 ile başlıyorsa
    if (digits.length && digits.charAt(0) !== "0") digits = "0" + digits;
    return digits.slice(0, 11);
  }

  function formatPhone(digits) {
    var parts = [];
    if (digits.length > 0) parts.push(digits.slice(0, 4));
    if (digits.length > 4) parts.push(digits.slice(4, 7));
    if (digits.length > 7) parts.push(digits.slice(7, 9));
    if (digits.length > 9) parts.push(digits.slice(9, 11));
    return parts.join(" ");
  }

  function isValidPhone(digits) {
    return /^0\d{10}$/.test(digits);
  }

  function attachPhoneMask(input) {
    input.addEventListener("input", function () {
      var digits = normalizePhoneDigits(input.value);
      input.value = formatPhone(digits);
    });
  }

  /* ---------- Doğrulama ---------- */
  function setError(field, message) {
    var wrap = field.closest(".field");
    var err = wrap ? qs(".field__error", wrap) : null;
    if (wrap) wrap.classList.toggle("has-error", !!message);
    if (err) err.textContent = message || "";
    field.setAttribute("aria-invalid", message ? "true" : "false");
  }

  function validateForm(form) {
    var ok = true;
    var name = qs('[name="name"]', form);
    var phone = qs('[name="phone"]', form);
    var kvkk = qs('[name="kvkk"]', form);
    var kvkkErr = qs(".consent__error", form);

    var nameVal = (name.value || "").trim();
    if (nameVal.length < 3) {
      setError(name, "Lütfen adınızı ve soyadınızı yazın.");
      ok = false;
    } else {
      setError(name, "");
    }

    var digits = normalizePhoneDigits(phone.value);
    if (!isValidPhone(digits)) {
      setError(phone, "Lütfen geçerli bir telefon numarası girin (05xx xxx xx xx).");
      ok = false;
    } else {
      setError(phone, "");
    }

    if (kvkk && !kvkk.checked) {
      if (kvkkErr) kvkkErr.textContent = "Devam etmek için onay kutusunu işaretleyin.";
      ok = false;
    } else if (kvkkErr) {
      kvkkErr.textContent = "";
    }

    return ok;
  }

  /* ---------- Gönderim ---------- */
  function buildPayload(form) {
    var phoneDigits = normalizePhoneDigits(qs('[name="phone"]', form).value);
    var msgEl = qs('[name="message"]', form);
    var emailEl = qs('[name="email"]', form);
    var sourceVal = form.getAttribute("data-source") || "contact-form";
    return {
      name: qs('[name="name"]', form).value.trim(),
      phone: formatPhone(phoneDigits),
      phone_e164: "+9" + phoneDigits, // 0532... -> +90532...
      tel: formatPhone(phoneDigits),
      telefon: formatPhone(phoneDigits),
      email: emailEl ? emailEl.value.trim() : "",
      message: msgEl ? msgEl.value.trim() : "",
      kvkk: true,
      source: sourceVal,
      type: sourceVal === "modal" ? "modal-form" : "contact-form",
      url: window.location.href,
      page: window.location.href,
      referrer: document.referrer || "",
      utm: getUtmParams(),
      submitted_at: new Date().toISOString()
    };
  }

  function getUtmParams() {
    var out = {};
    var params = new URLSearchParams(window.location.search);
    ["utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content", "gclid", "fbclid"].forEach(function (k) {
      if (params.has(k)) out[k] = params.get(k);
    });
    return out;
  }

  function showSuccess(form) {
    var card = form.closest(".form-card");
    var success = card ? qs(".form-success", card) : null;
    form.hidden = true;
    if (success) {
      success.hidden = false;
      success.setAttribute("tabindex", "-1");
      success.focus({ preventScroll: false });
    }
  }

  function setLoading(form, isLoading) {
    var btn = qs('button[type="submit"]', form);
    if (!btn) return;
    btn.classList.toggle("is-loading", isLoading);
    if (isLoading) btn.setAttribute("disabled", "disabled");
    else btn.removeAttribute("disabled");
  }

  function sendToEndpoint(payload) {
    var formData = new FormData();
    for (var key in payload) {
      if (payload.hasOwnProperty(key)) {
        if (typeof payload[key] === "object") {
          formData.append(key, JSON.stringify(payload[key]));
        } else {
          formData.append(key, payload[key]);
        }
      }
    }

    console.log("[Lead Form] İstek gönderiliyor ->", CONFIG.formEndpoint, payload);

    return fetch(CONFIG.formEndpoint, {
      method: "POST",
      body: formData,
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    }).then(function (res) {
      console.log("[Lead Form] Sunucu yanıt kodu:", res.status);
      if (!res.ok) throw new Error("HTTP " + res.status);
      return res.text();
    });
  }

  function handleSubmit(e) {
    e.preventDefault();
    var form = e.currentTarget;

    // Honeypot: botlar doldurur, gerçek kullanıcı görmez
    var hp = qs('[name="website"]', form);
    if (hp && hp.value) { showSuccess(form); return; }

    if (!validateForm(form)) {
      console.warn("[Lead Form] Form doğrulama geçersiz!");
      var firstErr = qs(".has-error input, .has-error textarea", form) || qs('[name="kvkk"]', form);
      if (firstErr) firstErr.focus();
      return;
    }

    // Varsa önceki genel hata mesajını temizle
    var existingErr = qs(".form-global-error", form);
    if (existingErr) existingErr.remove();

    var payload = buildPayload(form);
    setLoading(form, true);

    sendToEndpoint(payload)
      .then(function (responseText) {
        console.log("[Lead Form] Sunucu yanıtı:", responseText);
        setLoading(form, false);

        var isError = false;
        try {
          var data = JSON.parse(responseText);
          if (data && data.success === false) isError = true;
        } catch (err) {
          if (responseText.indexOf("Mailer Error") !== -1) {
            isError = true;
          }
        }

        if (isError) {
          throw new Error(responseText || "Gönderim başarısız");
        }

        showSuccess(form);
        track("lead_form_submit", { source: payload.source });
      })
      .catch(function (err) {
        console.error("[Lead Form] Gönderilemedi:", err);
        setLoading(form, false);
        var errBox = document.createElement("div");
        errBox.className = "field__error form-global-error";
        errBox.style.marginTop = "12px";
        errBox.style.padding = "8px 12px";
        errBox.style.borderRadius = "6px";
        errBox.style.backgroundColor = "#fee2e2";
        errBox.style.color = "#b91c1c";
        errBox.style.fontSize = "14px";
        errBox.style.textAlign = "center";
        errBox.textContent = "Mesajınız iletilirken bir hata oluştu. Lütfen tekrar deneyiniz veya bizi doğrudan telefonla arayınız.";
        var btn = qs('button[type="submit"]', form);
        if (btn && btn.parentNode) {
          btn.parentNode.insertBefore(errBox, btn.nextSibling);
        } else {
          form.appendChild(errBox);
        }
      });
  }

  function initForms() {
    qsa(".lead-form").forEach(function (form) {
      var phone = qs('[name="phone"]', form);
      if (phone) attachPhoneMask(phone);

      // Alan düzeltilince hatayı temizle
      qsa("input, textarea", form).forEach(function (el) {
        el.addEventListener("input", function () {
          if (el.closest(".field")) setError(el, "");
          if (el.name === "kvkk") { var ce = qs(".consent__error", form); if (ce) ce.textContent = ""; }
        });
      });

      form.addEventListener("submit", handleSubmit);
    });
  }

  /* ---------- Tıklama takibi (tel / WhatsApp) ---------- */
  function initClickTracking() {
    qsa("[data-track]").forEach(function (el) {
      el.addEventListener("click", function () {
        var name = el.getAttribute("data-track") || "";
        var type = name.indexOf("whatsapp") === 0 ? "whatsapp_click" : "call_click";
        track(type, { placement: name });
      });
    });
  }

  /* ---------- Header gölgesi + mobil menü ---------- */
  function initHeader() {
    var header = qs("#header");
    var toggle = qs("#nav-toggle");
    var nav = qs("#site-nav");
    if (!header) return;

    var update = function () { header.classList.toggle("is-scrolled", window.scrollY > 8); };
    update();
    window.addEventListener("scroll", update, { passive: true });

    if (!toggle || !nav) return;

    function setOpen(open) {
      header.classList.toggle("is-nav-open", open);
      document.body.classList.toggle("nav-open", open);
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
      toggle.setAttribute("aria-label", open ? "Menüyü kapat" : "Menüyü aç");
    }

    function isOpen() {
      return header.classList.contains("is-nav-open");
    }

    toggle.addEventListener("click", function () {
      setOpen(!isOpen());
    });

    nav.addEventListener("click", function (e) {
      var link = e.target.closest("a");
      if (link) setOpen(false);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && isOpen()) setOpen(false);
    });

    window.addEventListener("resize", function () {
      if (window.innerWidth >= 1100 && isOpen()) setOpen(false);
    });
  }

  /* ---------- SSS: tek seferde bir soru açık ---------- */
  function initFaq() {
    var items = qsa(".faq__item");
    items.forEach(function (item) {
      item.addEventListener("toggle", function () {
        if (!item.open) return;
        items.forEach(function (other) { if (other !== item) other.open = false; });
      });
    });
  }

  /* ---------- Kaydırma animasyonları ---------- */
  function initReveal() {
    var els = qsa(".reveal");
    if (!("IntersectionObserver" in window)) {
      els.forEach(function (el) { el.classList.add("in-view"); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("in-view");
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: "0px 0px -40px 0px" });
    els.forEach(function (el) { io.observe(el); });
  }

  /* ---------- Yıl ---------- */
  function initYear() {
    var y = qs("#year");
    if (y) y.textContent = String(new Date().getFullYear());
  }

  /* ---------- CTA Modal ---------- */
  function initModal() {
    var modal = qs("#cta-modal");
    if (!modal) return;

    var dialog = qs(".cta-modal__dialog", modal);
    var lastFocus = null;
    var AUTO_DELAY_MS = 20000;

    function isOpen() {
      return modal.classList.contains("is-open");
    }

    function openModal(source) {
      if (isOpen()) return;
      lastFocus = document.activeElement;
      modal.classList.add("is-open");
      modal.setAttribute("aria-hidden", "false");
      document.body.classList.add("modal-open");
      var first = qs("input:not([type='hidden']):not([tabindex='-1']), button[type='submit']", dialog);
      if (first) first.focus();
      try { sessionStorage.setItem("cta_modal_shown", "1"); } catch (err) {}
      track("cta_modal_open", { source: source || "click" });
    }

    function closeModal() {
      if (!isOpen()) return;
      modal.classList.remove("is-open");
      modal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("modal-open");
      if (lastFocus && typeof lastFocus.focus === "function") lastFocus.focus();
    }

    qsa("[data-open-modal]").forEach(function (el) {
      el.addEventListener("click", function (e) {
        e.preventDefault();
        openModal(el.getAttribute("data-open-modal") || "click");
      });
    });

    qsa("[data-close-modal]", modal).forEach(function (el) {
      el.addEventListener("click", closeModal);
    });

    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && isOpen()) closeModal();
    });

    try {
      if (!sessionStorage.getItem("cta_modal_shown")) {
        setTimeout(function () {
          if (isOpen() || document.hidden) return;
          var active = document.activeElement;
          if (active && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) return;
          openModal("auto");
        }, AUTO_DELAY_MS);
      }
    } catch (err) {}
  }

  /* ---------- Hekimler Slider ---------- */
  function initDoctorSlider() {
    var section = qs(".section--doctor");
    if (!section) return;

    var track = qs(".doctor-slider-track", section);
    var tabs = qsa(".doctor-tab", section);
    var slides = qsa(".doctor-slide", section);
    var dots = qsa(".doctor-dot", section);
    var prevBtn = qs(".doctor-nav-btn--prev", section);
    var nextBtn = qs(".doctor-nav-btn--next", section);

    var currentIndex = 0;
    var totalSlides = slides.length;
    if (totalSlides === 0) return;

    function goToSlide(index) {
      if (index < 0) index = totalSlides - 1;
      if (index >= totalSlides) index = 0;
      currentIndex = index;

      if (track) {
        track.style.transform = "translateX(-" + (currentIndex * 100) + "%)";
      }

      slides.forEach(function (slide, i) {
        var active = i === currentIndex;
        slide.classList.toggle("is-active", active);
        slide.setAttribute("aria-hidden", active ? "false" : "true");
      });

      tabs.forEach(function (tab, i) {
        var active = i === currentIndex;
        tab.classList.toggle("is-active", active);
        tab.setAttribute("aria-selected", active ? "true" : "false");
      });

      dots.forEach(function (dot, i) {
        var active = i === currentIndex;
        dot.classList.toggle("is-active", active);
        dot.setAttribute("aria-selected", active ? "true" : "false");
      });
    }

    tabs.forEach(function (tab) {
      tab.addEventListener("click", function () {
        var idx = parseInt(tab.getAttribute("data-doctor-tab"), 10);
        if (!isNaN(idx)) goToSlide(idx);
      });
    });

    dots.forEach(function (dot) {
      dot.addEventListener("click", function () {
        var idx = parseInt(dot.getAttribute("data-dot-index"), 10);
        if (!isNaN(idx)) goToSlide(idx);
      });
    });

    if (prevBtn) {
      prevBtn.addEventListener("click", function () {
        goToSlide(currentIndex - 1);
      });
    }

    if (nextBtn) {
      nextBtn.addEventListener("click", function () {
        goToSlide(currentIndex + 1);
      });
    }

    // Touch swipe desteği (mobil dokunmatik)
    var sliderEl = qs(".doctor-slider", section);
    if (sliderEl) {
      var touchStartX = 0;
      var touchEndX = 0;

      sliderEl.addEventListener("touchstart", function (e) {
        touchStartX = e.changedTouches[0].screenX;
      }, { passive: true });

      sliderEl.addEventListener("touchend", function (e) {
        touchEndX = e.changedTouches[0].screenX;
        var diff = touchStartX - touchEndX;
        if (Math.abs(diff) > 40) {
          if (diff > 0) {
            goToSlide(currentIndex + 1);
          } else {
            goToSlide(currentIndex - 1);
          }
        }
      }, { passive: true });
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    initForms();
    initClickTracking();
    initHeader();
    initDoctorSlider();
    initFaq();
    initReveal();
    initYear();
    initModal();
  });
})();
