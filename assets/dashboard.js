(function () {
  let countdownRows = [];
  let relativeTimes = [];
  const quickCategoryButtons = Array.from(document.querySelectorAll("[data-quick-category-id]"));
  const heartbeatRoot = document.querySelector("[data-dashboard-heartbeat]");
  const entryForm = document.querySelector(".entry-form");
  const categorySelect = document.querySelector("[data-category-select]");
  const visitorNameInput = document.querySelector("[data-visitor-name]");
  const autofillStatus = document.querySelector("[data-autofill-status]");
  const visitorProfiles = loadVisitorProfiles();
  let lastAutofilledName = "";
  let heartbeatBusy = false;

  quickCategoryButtons.forEach((button) => {
    button.addEventListener("click", () => {
      if (!categorySelect) {
        return;
      }

      categorySelect.value = button.dataset.quickCategoryId || "";
      syncQuickCategorySelection();
      categorySelect.dispatchEvent(new Event("change", { bubbles: true }));
    });
  });

  if (categorySelect) {
    categorySelect.addEventListener("change", syncQuickCategorySelection);
    syncQuickCategorySelection();
  }

  if (visitorNameInput && entryForm) {
    visitorNameInput.addEventListener("input", handleVisitorSuggestion);
    visitorNameInput.addEventListener("change", handleVisitorSuggestion);
  }

  function parseDate(value) {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date;
  }

  function updateCountdowns() {
    const now = Date.now();

    countdownRows.forEach((row) => {
      const entryAt = parseDate(row.dataset.entryAt);
      const limitMinutes = Number(row.dataset.limitMinutes || 0);

      if (!entryAt) return;

      const elapsedSeconds = Math.max(0, Math.floor((now - entryAt.getTime()) / 1000));
      const elapsedMinutes = Math.floor(elapsedSeconds / 60);
      const countdownLabel = row.querySelector("[data-countdown-label]");
      const elapsedLabel = row.querySelector("[data-elapsed-label]");
      const bar = row.querySelector("[data-countdown-bar]");

      if (!limitMinutes) {
        if (countdownLabel) {
          countdownLabel.textContent = formatDuration(elapsedSeconds);
        }

        if (elapsedLabel) {
          elapsedLabel.textContent = "canlı süre";
        }

        return;
      }

      const limitSeconds = limitMinutes * 60;
      const remainingSeconds = Math.max(0, limitSeconds - elapsedSeconds);
      const remainingDisplay = `${formatDuration(remainingSeconds)} kaldı`;
      const progress = Math.min(100, Math.max(0, (elapsedSeconds / Math.max(1, limitSeconds)) * 100));

      if (countdownLabel) {
        countdownLabel.textContent = remainingSeconds === 0 ? "Süre doldu" : remainingDisplay;
      }

      if (elapsedLabel) {
        elapsedLabel.textContent = `${formatDuration(elapsedSeconds)} / ${limitMinutes} dk içeride`;
      }

      if (bar) {
        bar.style.width = `${progress}%`;
      }

      row.classList.toggle("countdown-warning", progress >= 75 && progress < 100);
      row.classList.toggle("countdown-overdue", progress >= 100);
    });
  }

  function formatDuration(totalSeconds) {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const minutes = Math.floor(seconds / 60);
    const remainder = seconds % 60;

    if (minutes <= 0) {
      return `${remainder} sn`;
    }

    return `${minutes} dk ${String(remainder).padStart(2, "0")} sn`;
  }

  function formatVehiclePlate(value) {
    const compact = String(value || "")
      .toUpperCase()
      .replace(/Ç/g, "C")
      .replace(/Ğ/g, "G")
      .replace(/İ/g, "I")
      .replace(/Ö/g, "O")
      .replace(/Ş/g, "S")
      .replace(/Ü/g, "U")
      .replace(/[^A-Z0-9]/g, "");

    let index = 0;
    let province = "";
    let letters = "";
    let numbers = "";

    while (index < compact.length && /\d/.test(compact[index]) && province.length < 2) {
      province += compact[index];
      index += 1;
    }

    while (index < compact.length && /[A-Z]/.test(compact[index]) && letters.length < 3) {
      letters += compact[index];
      index += 1;
    }

    while (index < compact.length && /\d/.test(compact[index]) && numbers.length < 4) {
      numbers += compact[index];
      index += 1;
    }

    return [province, letters, numbers].filter(Boolean).join(" ");
  }

  function syncQuickCategorySelection() {
    if (!categorySelect) {
      return;
    }

    quickCategoryButtons.forEach((button) => {
      button.classList.toggle("is-selected", button.dataset.quickCategoryId === categorySelect.value);
    });
  }

  function loadVisitorProfiles() {
    const source = document.querySelector("#visitor-suggestion-data");
    if (!source) {
      return new Map();
    }

    try {
      const rows = JSON.parse(source.textContent || "[]");
      const profiles = new Map();

      rows.forEach((row) => {
        const key = normalizeName(row.full_name);
        if (!key || profiles.has(key)) {
          return;
        }

        profiles.set(key, row);
      });

      return profiles;
    } catch (error) {
      return new Map();
    }
  }

  function handleVisitorSuggestion() {
    const normalizedName = normalizeName(visitorNameInput.value);

    if (!normalizedName) {
      lastAutofilledName = "";
      setAutofillStatus("");
      return;
    }

    const profile = visitorProfiles.get(normalizedName);
    if (!profile) {
      lastAutofilledName = "";
      setAutofillStatus("");
      return;
    }

    if (lastAutofilledName === normalizedName) {
      return;
    }

    fillVisitorProfile(profile);
    lastAutofilledName = normalizedName;
    setAutofillStatus(`${profile.full_name} kaydı bulundu, bilgiler dolduruldu.`);
  }

  function fillVisitorProfile(profile) {
    setFormValue("phone", profile.phone || "");
    setFormValue("vehicle_plate", formatVehiclePlate(profile.vehicle_plate || ""));
    setFormValue("company", profile.company || "");
    setFormValue("category_id", profile.category_id || "");
    setFormValue("department_id", profile.department_id || "");
    setFormValue("host_name", profile.host_name || "");
    setCheckboxValue("has_appointment", profile.appointment_status === "appointment");

    const noteInput = entryForm.elements.note;
    if (noteInput && !noteInput.value.trim() && profile.note) {
      noteInput.value = profile.note;
    }
  }

  function setFormValue(name, value) {
    const field = entryForm.elements[name];
    if (!field || value === undefined || value === null || String(value) === "") {
      return;
    }

    field.value = String(value);
  }

  function setCheckboxValue(name, checked) {
    const field = entryForm.elements[name];
    if (!field) {
      return;
    }

    field.checked = Boolean(checked);
  }

  function setAutofillStatus(message) {
    if (!autofillStatus) {
      return;
    }

    autofillStatus.textContent = message;
    autofillStatus.classList.toggle("visible", Boolean(message));
  }

  function normalizeName(value) {
    return String(value || "")
      .trim()
      .replace(/\s+/g, " ")
      .toLocaleLowerCase("tr-TR");
  }

  function updateRelativeTimes() {
    const now = Date.now();

    relativeTimes.forEach((item) => {
      const createdAt = parseDate(item.dataset.createdAt);
      if (!createdAt) return;

      const diffSeconds = Math.max(0, Math.floor((now - createdAt.getTime()) / 1000));
      if (diffSeconds < 60) {
        item.textContent = "az önce";
        return;
      }

      const diffMinutes = Math.floor(diffSeconds / 60);
      if (diffMinutes < 60) {
        item.textContent = `${diffMinutes} dk önce`;
        return;
      }

      const diffHours = Math.floor(diffMinutes / 60);
      item.textContent = `${diffHours} saat önce`;
    });
  }

  function tick() {
    updateCountdowns();
    updateRelativeTimes();
  }

  function refreshLiveCollections() {
    countdownRows = Array.from(document.querySelectorAll(".js-duration"));
    relativeTimes = Array.from(document.querySelectorAll("[data-relative-time]"));
    bindPlateInputs();
    bindDetailToggles();
    bindEditToggles();
  }

  function bindPlateInputs() {
    document.querySelectorAll("[data-plate-format]").forEach((input) => {
      if (input.dataset.plateBound === "1") {
        return;
      }

      input.dataset.plateBound = "1";
      input.addEventListener("input", () => {
        input.value = formatVehiclePlate(input.value);
      });

      input.addEventListener("blur", () => {
        input.value = formatVehiclePlate(input.value);
      });
    });
  }

  function bindDetailToggles() {
    document.querySelectorAll("[data-detail-toggle]").forEach((button) => {
      if (button.dataset.detailBound === "1") {
        return;
      }

      button.dataset.detailBound = "1";
      button.addEventListener("click", () => {
        const row = button.closest(".visit-row");
        const panel = row ? row.querySelector(".visit-detail-panel") : null;

        if (!panel) {
          return;
        }

        const isOpen = !panel.hidden;
        panel.hidden = isOpen;
        button.setAttribute("aria-expanded", String(!isOpen));
        button.textContent = isOpen ? "Detay" : "Gizle";
      });
    });
  }

  function bindEditToggles() {
    document.querySelectorAll("[data-edit-toggle]").forEach((button) => {
      if (button.dataset.editBound === "1") {
        return;
      }

      button.dataset.editBound = "1";
      button.addEventListener("click", () => {
        const row = button.closest(".visit-row");
        const panel = row ? row.querySelector(".visit-detail-panel") : null;
        const detailButton = row ? row.querySelector("[data-detail-toggle]") : null;

        if (!panel) {
          return;
        }

        panel.hidden = false;
        button.setAttribute("aria-expanded", "true");

        if (detailButton) {
          detailButton.setAttribute("aria-expanded", "true");
          detailButton.textContent = "Gizle";
        }

        const firstField = panel.querySelector("[data-edit-first]");
        if (firstField) {
          firstField.focus();
          firstField.select();
        }
      });
    });
  }

  function startHeartbeat() {
    if (!heartbeatRoot || !heartbeatRoot.dataset.heartbeatUrl || !heartbeatRoot.dataset.csrf) {
      return;
    }

    window.setTimeout(processHeartbeat, 2000);
    window.setInterval(processHeartbeat, 5000);
  }

  function processHeartbeat() {
    if (heartbeatBusy) {
      return;
    }

    heartbeatBusy = true;

    const body = new FormData();
    body.append("_csrf", heartbeatRoot.dataset.csrf);
    body.append("signature", heartbeatRoot.dataset.dashboardSignature || "");

    fetch(heartbeatRoot.dataset.heartbeatUrl, {
      method: "POST",
      body,
      credentials: "same-origin",
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    })
      .then((response) => (response.ok ? response.json() : null))
      .then((payload) => {
        if (!payload) {
          return;
        }

        if (payload.signature) {
          heartbeatRoot.dataset.dashboardSignature = payload.signature;
        }

        if (payload.changed && payload.html) {
          replaceLiveSection("[data-live-stats]", payload.html.stats);
          replaceLiveSection("[data-live-verifications]", payload.html.verifications);
          replaceLiveSection("[data-live-inside]", payload.html.inside);
          replaceLiveSection("[data-live-activity]", payload.html.activity);
          refreshLiveCollections();
          tick();
        }
      })
      .catch(() => {})
      .finally(() => {
        heartbeatBusy = false;
      });
  }

  function replaceLiveSection(selector, html) {
    const target = document.querySelector(selector);
    if (!target || typeof html !== "string") {
      return;
    }

    target.innerHTML = html;
  }

  refreshLiveCollections();
  tick();
  startHeartbeat();
  window.setInterval(tick, 1000);
})();
