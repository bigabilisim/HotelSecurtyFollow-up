(function () {
  let countdownRows = [];
  let relativeTimes = [];
  const quickCategoryButtons = Array.from(document.querySelectorAll("[data-quick-category-id]"));
  const quickDepartmentButtons = Array.from(document.querySelectorAll("[data-quick-department-id]"));
  const heartbeatRoot = document.querySelector("[data-dashboard-heartbeat]");
  const entryForm = document.querySelector(".entry-form");
  const categorySelect = document.querySelector("[data-category-select]");
  const departmentSelect = document.querySelector("[data-department-select]");
  const visitorNameInput = document.querySelector("[data-visitor-name]");
  const autofillStatus = document.querySelector("[data-autofill-status]");
  const visitColumnControls = Array.from(document.querySelectorAll("[data-column-toggle]"));
  const dashboardBoard = document.querySelector("[data-dashboard-board]");
  const dashboardBlockControls = Array.from(document.querySelectorAll("[data-dashboard-block-toggle]"));
  const dashboardTemplateControls = Array.from(document.querySelectorAll("button[data-dashboard-template]"));
  const dashboardLayoutReset = document.querySelector("[data-dashboard-layout-reset]");
  const dashboardZoomControls = Array.from(document.querySelectorAll("[data-dashboard-zoom]"));
  const dashboardZoomValue = document.querySelector("[data-dashboard-zoom-value]");
  const dashboardViewPanel = document.querySelector("[data-dashboard-view-panel]");
  const dashboardViewToggle = document.querySelector("[data-dashboard-view-toggle]");
  const profileStorage = window.hotelSecurityProfileStorage || createLocalProfileStorage();
  const visitColumnStorageKey = "hotelSecurity.visitColumns.hidden";
  const dashboardBlockStorageKey = "hotelSecurity.dashboardBlocks.hidden";
  const dashboardTemplateStorageKey = "hotelSecurity.dashboard.template";
  const dashboardOrderStorageKey = "hotelSecurity.dashboard.order";
  const dashboardZoomStorageKey = "hotelSecurity.dashboard.zoom";
  const dashboardViewPanelStorageKey = "hotelSecurity.dashboard.viewPanelOpen";
  const dashboardZoomMin = 80;
  const dashboardZoomMax = 125;
  const dashboardZoomStep = 5;
  const visitColumns = [
    { key: "person", label: "Kişi", grid: "minmax(176px, 1.24fr)" },
    { key: "department", label: "Departman", grid: "minmax(96px, 0.6fr)" },
    { key: "duration", label: "Süre", grid: "minmax(138px, 0.82fr)" },
    { key: "status", label: "Durum", grid: "minmax(96px, 0.5fr)" },
    { key: "actions", label: "İşlem", grid: "minmax(132px, 0.72fr)" }
  ];
  const visitColumnKeys = new Set(visitColumns.map((column) => column.key));
  const dashboardBlocks = [
    { key: "door", label: "Kapı İşlemi" },
    { key: "inside", label: "İçeride Olanlar" },
    { key: "activity", label: "Canlı Akış" },
    { key: "stats", label: "Özet Kartları" },
    { key: "verifications", label: "Departman Onayı" }
  ];
  const dashboardTemplates = {
    operations: ["stats", "door", "inside", "activity", "verifications"],
    monitoring: ["stats", "inside", "activity", "verifications", "door"],
    compact: ["door", "inside", "stats", "activity", "verifications"]
  };
  const dashboardBlockKeys = new Set(dashboardBlocks.map((block) => block.key));
  let hiddenVisitColumns = loadHiddenVisitColumns();
  let hiddenDashboardBlocks = loadHiddenDashboardBlocks();
  let activeDashboardTemplate = loadDashboardTemplate();
  let dashboardZoom = loadDashboardZoom();
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

  quickDepartmentButtons.forEach((button) => {
    button.addEventListener("click", () => {
      if (!departmentSelect) {
        return;
      }

      departmentSelect.value = button.dataset.quickDepartmentId || "";
      syncQuickDepartmentSelection();
      departmentSelect.dispatchEvent(new Event("change", { bubbles: true }));
    });
  });

  if (departmentSelect) {
    departmentSelect.addEventListener("change", syncQuickDepartmentSelection);
    syncQuickDepartmentSelection();
  }

  if (visitorNameInput && entryForm) {
    visitorNameInput.addEventListener("input", handleVisitorSuggestion);
    visitorNameInput.addEventListener("change", handleVisitorSuggestion);
  }

  function createLocalProfileStorage() {
    return {
      getItem(key) {
        try {
          return window.localStorage ? window.localStorage.getItem(key) : null;
        } catch (error) {
          return null;
        }
      },
      setItem(key, value) {
        try {
          if (window.localStorage) {
            window.localStorage.setItem(key, String(value));
          }
        } catch (error) {
          // Kısıtlı tarayıcı modlarında profil sadece oturum boyunca korunur.
        }
      },
      removeItem(key) {
        try {
          if (window.localStorage) {
            window.localStorage.removeItem(key);
          }
        } catch (error) {
          // Kısıtlı tarayıcı modlarında kayıtlı profil olmayabilir.
        }
      }
    };
  }

  function bindDashboardViewPanel() {
    if (!dashboardViewPanel || !dashboardViewToggle || dashboardViewToggle.dataset.viewBound === "1") {
      return;
    }

    dashboardViewToggle.dataset.viewBound = "1";
    setDashboardViewPanel(isDashboardViewPanelOpen(), false);

    dashboardViewToggle.addEventListener("click", () => {
      setDashboardViewPanel(dashboardViewPanel.hidden, true);
    });
  }

  function isDashboardViewPanelOpen() {
    return profileStorage.getItem(dashboardViewPanelStorageKey) === "1";
  }

  function setDashboardViewPanel(open, persist) {
    if (!dashboardViewPanel || !dashboardViewToggle) {
      return;
    }

    dashboardViewPanel.hidden = !open;
    dashboardViewToggle.classList.toggle("is-open", open);
    dashboardViewToggle.textContent = open ? "Görünümü Gizle" : "Görünüm Ayarı";
    dashboardViewToggle.setAttribute("aria-expanded", String(open));

    if (!persist) {
      return;
    }

    profileStorage.setItem(dashboardViewPanelStorageKey, open ? "1" : "0");
  }

  function loadHiddenVisitColumns() {
    try {
      const raw = profileStorage.getItem(visitColumnStorageKey) || "";
      const parsed = JSON.parse(raw || "[]");

      if (!Array.isArray(parsed)) {
        return new Set();
      }

      return new Set(parsed.filter((key) => visitColumnKeys.has(key)));
    } catch (error) {
      return new Set();
    }
  }

  function saveHiddenVisitColumns() {
    profileStorage.setItem(visitColumnStorageKey, JSON.stringify(Array.from(hiddenVisitColumns)));
  }

  function loadHiddenDashboardBlocks() {
    try {
      const raw = profileStorage.getItem(dashboardBlockStorageKey) || "";
      const parsed = JSON.parse(raw || "[]");

      if (!Array.isArray(parsed)) {
        return new Set();
      }

      return new Set(parsed.filter((key) => dashboardBlockKeys.has(key)));
    } catch (error) {
      return new Set();
    }
  }

  function saveHiddenDashboardBlocks() {
    profileStorage.setItem(dashboardBlockStorageKey, JSON.stringify(Array.from(hiddenDashboardBlocks)));
  }

  function loadDashboardTemplate() {
    try {
      const template = profileStorage.getItem(dashboardTemplateStorageKey) || "";
      return Object.prototype.hasOwnProperty.call(dashboardTemplates, template || "") ? template : "operations";
    } catch (error) {
      return "operations";
    }
  }

  function loadDashboardZoom() {
    try {
      const raw = Number(profileStorage.getItem(dashboardZoomStorageKey) || 100);
      return normalizeDashboardZoom(raw || 100);
    } catch (error) {
      return 100;
    }
  }

  function normalizeDashboardZoom(value) {
    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
      return 100;
    }

    const stepped = Math.round(numeric / dashboardZoomStep) * dashboardZoomStep;
    return Math.min(dashboardZoomMax, Math.max(dashboardZoomMin, stepped));
  }

  function saveDashboardZoom() {
    profileStorage.setItem(dashboardZoomStorageKey, String(dashboardZoom));
  }

  function bindDashboardZoomControls() {
    dashboardZoomControls.forEach((button) => {
      if (button.dataset.zoomBound === "1") {
        return;
      }

      button.dataset.zoomBound = "1";
      button.addEventListener("click", () => {
        const action = button.dataset.dashboardZoom || "reset";

        if (action === "in") {
          dashboardZoom = normalizeDashboardZoom(dashboardZoom + dashboardZoomStep);
        } else if (action === "out") {
          dashboardZoom = normalizeDashboardZoom(dashboardZoom - dashboardZoomStep);
        } else {
          dashboardZoom = 100;
        }

        saveDashboardZoom();
        applyDashboardZoom();
      });
    });
  }

  function applyDashboardZoom() {
    if (dashboardBoard) {
      dashboardBoard.style.setProperty("--dashboard-zoom", `${dashboardZoom}%`);
    }

    if (dashboardZoomValue) {
      dashboardZoomValue.textContent = `${dashboardZoom}%`;
    }

    dashboardZoomControls.forEach((button) => {
      const action = button.dataset.dashboardZoom || "";
      button.disabled = (action === "out" && dashboardZoom <= dashboardZoomMin)
        || (action === "in" && dashboardZoom >= dashboardZoomMax);
    });
  }

  function saveDashboardTemplate() {
    profileStorage.setItem(dashboardTemplateStorageKey, activeDashboardTemplate);
  }

  function bindVisitColumnControls() {
    visitColumnControls.forEach((button) => {
      if (button.dataset.columnBound === "1") {
        return;
      }

      button.dataset.columnBound = "1";
      button.addEventListener("click", () => {
        const key = button.dataset.columnToggle || "";
        const isHidden = hiddenVisitColumns.has(key);

        if (!visitColumnKeys.has(key)) {
          return;
        }

        if (!isHidden && visibleVisitColumns().length <= 1) {
          return;
        }

        if (isHidden) {
          hiddenVisitColumns.delete(key);
        } else {
          hiddenVisitColumns.add(key);
        }

        saveHiddenVisitColumns();
        applyVisitColumnPreferences();
      });
    });
  }

  function visibleVisitColumns() {
    return visitColumns.filter((column) => !hiddenVisitColumns.has(column.key));
  }

  function applyVisitColumnPreferences() {
    let visibleColumns = visibleVisitColumns();

    if (!visibleColumns.length) {
      hiddenVisitColumns.delete("person");
      visibleColumns = visibleVisitColumns();
      saveHiddenVisitColumns();
    }

    const template = visibleColumns.map((column) => column.grid).join(" ");
    document.querySelectorAll(".visit-table").forEach((table) => {
      table.style.setProperty("--visit-grid-template", template);
      table.dataset.visibleColumns = String(visibleColumns.length);
    });

    document.querySelectorAll("[data-col]").forEach((cell) => {
      const key = cell.dataset.col || "";
      cell.hidden = hiddenVisitColumns.has(key);
    });

    visitColumnControls.forEach((button) => {
      const key = button.dataset.columnToggle || "";
      const isVisible = !hiddenVisitColumns.has(key);
      const column = visitColumns.find((item) => item.key === key);
      const icon = button.querySelector("[data-column-icon]");

      button.classList.toggle("is-hidden", !isVisible);
      button.setAttribute("aria-pressed", String(isVisible));
      button.setAttribute("title", `${column ? column.label : "Sütun"} sütununu ${isVisible ? "kapat" : "göster"}`);

      if (icon) {
        icon.textContent = isVisible ? "x" : "+";
      }
    });
  }

  function bindDashboardBlockControls() {
    dashboardBlockControls.forEach((button) => {
      if (button.dataset.blockBound === "1") {
        return;
      }

      button.dataset.blockBound = "1";
      button.addEventListener("click", () => {
        const key = button.dataset.dashboardBlockToggle || "";

        if (!dashboardBlockKeys.has(key)) {
          return;
        }

        if (hiddenDashboardBlocks.has(key)) {
          hiddenDashboardBlocks.delete(key);
        } else {
          hiddenDashboardBlocks.add(key);
        }

        saveHiddenDashboardBlocks();
        applyDashboardBlockPreferences();
      });
    });
  }

  function applyDashboardBlockPreferences() {
    document.querySelectorAll("[data-dashboard-block]").forEach((block) => {
      const key = block.dataset.dashboardBlock || "";
      block.hidden = hiddenDashboardBlocks.has(key);
    });

    dashboardBlockControls.forEach((button) => {
      const key = button.dataset.dashboardBlockToggle || "";
      const isVisible = !hiddenDashboardBlocks.has(key);
      const block = dashboardBlocks.find((item) => item.key === key);
      const icon = button.querySelector("[data-block-icon]");

      button.classList.toggle("is-hidden", !isVisible);
      button.setAttribute("aria-pressed", String(isVisible));
      button.setAttribute("title", `${block ? block.label : "Bölüm"} bölümünü ${isVisible ? "kapat" : "göster"}`);

      if (icon) {
        icon.textContent = isVisible ? "x" : "+";
      }
    });
  }

  function initDashboardTemplates() {
    if (!dashboardBoard) {
      return;
    }

    dashboardTemplateControls.forEach((button) => {
      if (button.dataset.templateBound === "1") {
        return;
      }

      button.dataset.templateBound = "1";
      button.addEventListener("click", () => {
        const template = button.dataset.dashboardTemplate || "operations";

        if (!Object.prototype.hasOwnProperty.call(dashboardTemplates, template)) {
          return;
        }

        activeDashboardTemplate = template;
        saveDashboardTemplate();
        removeDashboardOrder();
        applyDashboardLayout();
      });
    });

    if (dashboardLayoutReset && dashboardLayoutReset.dataset.resetBound !== "1") {
      dashboardLayoutReset.dataset.resetBound = "1";
      dashboardLayoutReset.addEventListener("click", () => {
        activeDashboardTemplate = "operations";
        hiddenDashboardBlocks = new Set();
        saveDashboardTemplate();
        saveHiddenDashboardBlocks();
        removeDashboardOrder();
        applyDashboardLayout();
        applyDashboardBlockPreferences();
      });
    }
  }

  function applyDashboardLayout() {
    if (!dashboardBoard) {
      return;
    }

    dashboardBoard.dataset.dashboardTemplate = activeDashboardTemplate;
    applyDashboardOrder();

    dashboardTemplateControls.forEach((button) => {
      const template = button.dataset.dashboardTemplate || "";
      const isActive = template === activeDashboardTemplate;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", String(isActive));
    });
  }

  function applyDashboardOrder() {
    if (!dashboardBoard) {
      return;
    }

    const order = loadDashboardOrder();
    const preferredOrder = order.length ? order : (dashboardTemplates[activeDashboardTemplate] || dashboardTemplates.operations);
    const blocks = Array.from(dashboardBoard.querySelectorAll("[data-dashboard-block]"));
    const indexedBlocks = new Map(blocks.map((block) => [block.dataset.dashboardBlock, block]));

    preferredOrder.forEach((key) => {
      const block = indexedBlocks.get(key);
      if (block) {
        dashboardBoard.appendChild(block);
      }
    });

    blocks.forEach((block) => {
      if (!preferredOrder.includes(block.dataset.dashboardBlock || "")) {
        dashboardBoard.appendChild(block);
      }
    });
  }

  function loadDashboardOrder() {
    try {
      const raw = profileStorage.getItem(dashboardOrderStorageKey) || "";
      const parsed = JSON.parse(raw || "[]");

      if (!Array.isArray(parsed)) {
        return [];
      }

      return parsed.filter((key) => dashboardBlockKeys.has(key));
    } catch (error) {
      return [];
    }
  }

  function removeDashboardOrder() {
    profileStorage.removeItem(dashboardOrderStorageKey);
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

  function syncQuickDepartmentSelection() {
    if (!departmentSelect) {
      return;
    }

    quickDepartmentButtons.forEach((button) => {
      button.classList.toggle("is-selected", button.dataset.quickDepartmentId === departmentSelect.value);
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
    syncQuickCategorySelection();
    syncQuickDepartmentSelection();

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
    bindDashboardViewPanel();
    bindDashboardZoomControls();
    applyDashboardZoom();
    initDashboardTemplates();
    applyDashboardLayout();
    bindVisitColumnControls();
    applyVisitColumnPreferences();
    bindDashboardBlockControls();
    applyDashboardBlockPreferences();
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
