(function () {
  const securityAlert = document.querySelector("[data-security-alert]");
  const securityAlertClose = document.querySelector("[data-security-alert-close]");
  const profileStorage = initUserProfileStorage();
  window.hotelSecurityProfileStorage = profileStorage;
  initSubmitLocks();
  initAdminSortableCards();
  initPermissionPresets();
  initWhatsAppWizard();
  initPwaInstall();
  initMobileMenuToggle();
  initSectionFilterMenus();
  initPersistentFilterForms();
  const serviceWorkerRegistration = registerServiceWorker();

  if (securityAlert && securityAlertClose) {
    securityAlertClose.focus();
    securityAlertClose.addEventListener("click", function () {
      securityAlert.classList.add("is-hidden");
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        securityAlert.classList.add("is-hidden");
      }
    });
  }

  const suggestionModal = document.querySelector("[data-suggestion-modal]");
  const suggestionOpenButtons = Array.from(document.querySelectorAll("[data-suggestion-open]"));
  const suggestionCloseButtons = Array.from(document.querySelectorAll("[data-suggestion-close]"));

  if (suggestionModal) {
    const firstField = suggestionModal.querySelector("select, input, textarea, button");

    function openSuggestionModal() {
      suggestionModal.hidden = false;
      suggestionModal.setAttribute("aria-hidden", "false");
      document.body.classList.add("modal-open");

      window.setTimeout(function () {
        if (firstField) {
          firstField.focus();
        }
      }, 0);
    }

    function closeSuggestionModal() {
      suggestionModal.hidden = true;
      suggestionModal.setAttribute("aria-hidden", "true");
      document.body.classList.remove("modal-open");
    }

    suggestionOpenButtons.forEach(function (button) {
      button.addEventListener("click", openSuggestionModal);
    });

    suggestionCloseButtons.forEach(function (button) {
      button.addEventListener("click", closeSuggestionModal);
    });

    suggestionModal.addEventListener("click", function (event) {
      if (event.target === suggestionModal) {
        closeSuggestionModal();
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape" && !suggestionModal.hidden) {
        closeSuggestionModal();
      }
    });
  }

  initMobileNotifications();

  function initUserProfileStorage() {
    const root = document.querySelector(".app-shell[data-user-preferences-url]");
    const syncKeys = [
      "otelSecurityMobileMenuHidden",
      "otelSecurityAdminCardOrder",
      "hotelSecurity.visitColumns.hidden",
      "hotelSecurity.dashboardBlocks.hidden",
      "hotelSecurity.dashboard.template",
      "hotelSecurity.dashboard.order",
      "hotelSecurity.dashboard.zoom",
      "hotelSecurity.dashboard.viewPanelOpen",
      "hotelSecurity.sectionFilter.dashboard-inside-columns.open",
      "hotelSecurity.sectionFilter.admin-records-filter.open",
      "hotelSecurity.sectionFilter.admin-suggestions-filter.open",
      "hotelSecurity.filterForm.admin-records",
      "hotelSecurity.filterForm.admin-suggestions"
    ];
    const syncKeySet = new Set(syncKeys);
    const pending = {};
    let saveTimer = null;

    function parseServerPreferences() {
      if (!root || !root.dataset.userPreferences) {
        return {};
      }

      try {
        const parsed = JSON.parse(root.dataset.userPreferences || "{}");
        return parsed && typeof parsed === "object" && !Array.isArray(parsed) ? parsed : {};
      } catch (error) {
        return {};
      }
    }

    function isSyncKey(key) {
      return syncKeySet.has(String(key || ""));
    }

    function readLocal(key) {
      try {
        return window.localStorage ? localStorage.getItem(key) : null;
      } catch (error) {
        return null;
      }
    }

    function writeLocal(key, value) {
      try {
        if (window.localStorage) {
          localStorage.setItem(key, String(value));
        }
      } catch (error) {}
    }

    function removeLocal(key) {
      try {
        if (window.localStorage) {
          localStorage.removeItem(key);
        }
      } catch (error) {}
    }

    function queueSave(key, value) {
      if (!root || !root.dataset.userPreferencesUrl || !root.dataset.csrf || !isSyncKey(key)) {
        return;
      }

      pending[key] = value;
      if (saveTimer) {
        window.clearTimeout(saveTimer);
      }

      saveTimer = window.setTimeout(flush, 350);
    }

    function queueMany(values) {
      Object.keys(values).forEach(function (key) {
        if (isSyncKey(key)) {
          pending[key] = values[key];
        }
      });

      if (!Object.keys(pending).length || !root || !root.dataset.userPreferencesUrl || !root.dataset.csrf) {
        return;
      }

      if (saveTimer) {
        window.clearTimeout(saveTimer);
      }

      saveTimer = window.setTimeout(flush, 350);
    }

    function flush() {
      const values = Object.assign({}, pending);
      Object.keys(pending).forEach(function (key) {
        delete pending[key];
      });

      if (!Object.keys(values).length || !root || !root.dataset.userPreferencesUrl || !root.dataset.csrf) {
        return;
      }

      const body = new URLSearchParams();
      body.set("_csrf", root.dataset.csrf);
      body.set("preferences", JSON.stringify(values));

      fetch(root.dataset.userPreferencesUrl, {
        method: "POST",
        body,
        credentials: "same-origin",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8"
        }
      }).catch(function () {});
    }

    const serverPreferences = parseServerPreferences();
    const backfill = {};
    syncKeys.forEach(function (key) {
      if (Object.prototype.hasOwnProperty.call(serverPreferences, key)) {
        const value = serverPreferences[key];
        if (value === null || value === undefined) {
          removeLocal(key);
        } else {
          writeLocal(key, value);
        }
        return;
      }

      const localValue = readLocal(key);
      if (localValue !== null) {
        backfill[key] = localValue;
      }
    });

    queueMany(backfill);

    return {
      getItem: readLocal,
      setItem: function (key, value) {
        writeLocal(key, value);
        queueSave(key, String(value));
      },
      removeItem: function (key) {
        removeLocal(key);
        queueSave(key, null);
      },
      flush
    };
  }

  function registerServiceWorker() {
    if (!("serviceWorker" in navigator)) {
      return Promise.resolve(null);
    }

    return navigator.serviceWorker.register("/service-worker.js").catch(function () {
      // PWA kurulumu desteklenmiyorsa panel normal web uygulaması olarak çalışır.
      return null;
    });
  }

  function initPwaInstall() {
    const installButtons = Array.from(document.querySelectorAll("[data-pwa-install]"));
    let deferredInstallPrompt = null;
    let installPromptRequested = false;

    function updateInstallButtons(state) {
      const installed = isStandaloneMode();

      installButtons.forEach(function (button) {
        button.hidden = installed;
        button.disabled = installed;
        button.textContent = "Uygulamayı Kur";
        button.title = installed
          ? "Uygulama zaten kurulu modda açılmış."
          : state === "ready"
            ? "Kurulum penceresini açmak için tıklayın."
            : "Kurulum yardımı ve tarayıcı adımlarını görmek için tıklayın.";
      });
    }

    function showInstallHelp() {
      if (isStandaloneMode()) {
        showPhonePermissionBanner(
          "Uygulama zaten kurulu",
          "Panel ana ekran uygulaması olarak açılmış durumda.",
          null,
          null,
          "Tamam",
          null
        );
        return;
      }

      if (!window.isSecureContext) {
        showPhonePermissionBanner(
          "HTTPS gerekli",
          "PWA kurulumu için site HTTPS üzerinden açılmalıdır. Yerel testte 127.0.0.1 desteklenir, canlıda HTTPS zorunludur.",
          null,
          null,
          "Tamam",
          null
        );
        return;
      }

      if (!("serviceWorker" in navigator)) {
        showPhonePermissionBanner(
          "Tarayıcı desteklemiyor",
          "Bu tarayıcı PWA kurulumunu desteklemiyor. Telefon için Chrome veya Safari, bilgisayar için Chrome veya Edge kullanın.",
          null,
          null,
          "Tamam",
          null
        );
        return;
      }

      if (!document.querySelector("link[rel='manifest']")) {
        showPhonePermissionBanner(
          "Manifest bulunamadı",
          "Uygulama kurulum dosyası sayfada görünmüyor. Sayfayı yenileyip tekrar deneyin.",
          "Yenile",
          function () {
            window.location.reload();
          },
          "Tamam",
          null
        );
        return;
      }

      if (isIosDevice()) {
        showPhonePermissionBanner(
          "iPhone kurulumu",
          "Safari'de Paylaş butonuna basın, Ana Ekrana Ekle seçeneğini seçin ve uygulamayı ana ekran ikonundan açın.",
          null,
          null,
          "Tamam",
          null
        );
        return;
      }

      serviceWorkerRegistration.finally(function () {
        showPhonePermissionBanner(
          "Tarayıcı kurulum penceresini vermedi",
          "Bu durum genelde uygulama zaten kuruluyken, desteklenmeyen uygulama içi tarayıcıda veya Chrome/Edge kurulum şartlarını henüz hazır görmediğinde olur. Chrome/Edge menüsünden Uygulamayı yükle / Ana ekrana ekle seçeneğini de deneyebilirsiniz.",
          "Yenile ve Tekrar Dene",
          function () {
            window.location.reload();
          },
          "Tamam",
          null
        );
      });
    }

    function runInstallPrompt(promptElement) {
      if (!deferredInstallPrompt) {
        installPromptRequested = true;
        showInstallHelp();
        return;
      }

      installPromptRequested = false;
      const promptEvent = deferredInstallPrompt;
      deferredInstallPrompt = null;
      updateInstallButtons("help");

      if (promptElement) {
        promptElement.remove();
      }

      promptEvent.prompt();
      promptEvent.userChoice
        .then(function (choice) {
          if (choice && choice.outcome === "accepted") {
            showPhonePermissionBanner(
              "Kurulum başlatıldı",
              "Uygulama kurulumu onaylandı. Kurulum tamamlandıktan sonra ana ekran ikonundan açabilirsiniz.",
              null,
              null,
              "Tamam",
              null
            );
            return;
          }

          showInstallHelp();
        })
        .catch(showInstallHelp);
    }

    installButtons.forEach(function (button) {
      button.addEventListener("click", function () {
        runInstallPrompt(null);
      });
    });

    updateInstallButtons("help");

    window.addEventListener("beforeinstallprompt", function (event) {
      event.preventDefault();
      deferredInstallPrompt = event;
      updateInstallButtons("ready");

      if (installPromptRequested) {
        runInstallPrompt(null);
        return;
      }

      showPhonePermissionBanner(
        "Uygulama kurulabilir",
        "Bu cihazda paneli PWA olarak kurabilirsiniz. Kurulumdan sonra ana ekran ikonundan hızlı giriş yapılır.",
        "Kur",
        runInstallPrompt,
        "Sonra",
        "pwaInstallPromptClosed"
      );
    });

    window.addEventListener("appinstalled", function () {
      deferredInstallPrompt = null;
      updateInstallButtons("help");
      showPhonePermissionBanner(
        "Kurulum tamamlandı",
        "Otel Güvenlik Sistemi cihazınıza uygulama olarak kuruldu.",
        null,
        null,
        "Tamam",
        null
      );
    });
  }

  function initMobileMenuToggle() {
    const toggle = document.querySelector("[data-mobile-menu-toggle]");
    const nav = document.querySelector("[data-mobile-nav]");

    if (!toggle || !nav) {
      return;
    }

    const storageKey = "otelSecurityMobileMenuHidden";

    function readPreference() {
      return profileStorage.getItem(storageKey) === "1";
    }

    function writePreference(hidden) {
      profileStorage.setItem(storageKey, hidden ? "1" : "0");
    }

    function applyState(hidden) {
      document.body.classList.toggle("mobile-menu-hidden", hidden);
      toggle.textContent = hidden ? "Menüyü Göster" : "Menüyü Gizle";
      toggle.setAttribute("aria-expanded", hidden ? "false" : "true");
      toggle.setAttribute("aria-label", hidden ? "Alt menüyü göster" : "Alt menüyü gizle");
      writePreference(hidden);
    }

    applyState(readPreference());

    toggle.addEventListener("click", function () {
      applyState(!document.body.classList.contains("mobile-menu-hidden"));
    });
  }

  function initSectionFilterMenus() {
    document.querySelectorAll("[data-section-filter]").forEach(function (section) {
      const key = section.dataset.sectionFilter || "default";
      const toggle = section.querySelector("[data-section-filter-toggle]");
      const panel = section.querySelector("[data-section-filter-panel]");

      if (!toggle || !panel || toggle.dataset.sectionFilterBound === "1") {
        return;
      }

      const storageKey = `hotelSecurity.sectionFilter.${key}.open`;
      toggle.dataset.sectionFilterBound = "1";

      function readPreference() {
        return profileStorage.getItem(storageKey) === "1";
      }

      function writePreference(open) {
        profileStorage.setItem(storageKey, open ? "1" : "0");
      }

      function applyState(open, persist) {
        panel.hidden = !open;
        section.classList.toggle("is-filter-open", open);
        toggle.classList.toggle("is-open", open);
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
        toggle.setAttribute("title", open ? "Filtreleri gizle" : "Filtreleri göster");

        const label = toggle.querySelector("[data-filter-label]");
        if (label) {
          label.textContent = open ? "Gizle" : "Filtre";
        }

        if (persist) {
          writePreference(open);
        }
      }

      applyState(readPreference(), false);

      toggle.addEventListener("click", function () {
        applyState(panel.hidden, true);
      });
    });
  }

  function initPersistentFilterForms() {
    document.querySelectorAll("form[data-persist-filter-form]").forEach(function (form) {
      if ((form.method || "get").toLowerCase() !== "get") {
        return;
      }

      const key = form.dataset.persistFilterForm || "default";
      const storageKey = `hotelSecurity.filterForm.${key}`;
      const routeField = form.querySelector("input[name='route']");
      const routeValue = routeField ? routeField.value : "";
      const fieldNames = persistentFilterFieldNames(form);
      const currentParams = new URLSearchParams(window.location.search);
      const sameRoute = !routeValue || currentParams.get("route") === routeValue;
      const hasQueryFilter = fieldNames.some(function (name) {
        return currentParams.has(name);
      });

      if (sameRoute && hasQueryFilter) {
        writePersistentFilter(storageKey, collectFilterValues(form, fieldNames));
      } else if (sameRoute) {
        restorePersistentFilter(form, storageKey, routeValue, fieldNames);
      }

      form.addEventListener("submit", function () {
        writePersistentFilter(storageKey, collectFilterValues(form, fieldNames));
      });
    });

    document.querySelectorAll("[data-clear-persisted-filter]").forEach(function (link) {
      if (link.dataset.clearFilterBound === "1") {
        return;
      }

      link.dataset.clearFilterBound = "1";
      link.addEventListener("click", function () {
        const key = link.dataset.clearPersistedFilter || "";
        if (!key) {
          return;
        }

        profileStorage.removeItem(`hotelSecurity.filterForm.${key}`);
      });
    });
  }

  function persistentFilterFieldNames(form) {
    return Array.from(form.elements)
      .filter(function (field) {
        return field && field.name && field.name !== "route" && !field.disabled;
      })
      .map(function (field) {
        return field.name;
      })
      .filter(function (name, index, names) {
        return names.indexOf(name) === index;
      });
  }

  function collectFilterValues(form, fieldNames) {
    const values = {};

    fieldNames.forEach(function (name) {
      const field = form.elements[name];

      if (!field) {
        return;
      }

      if (field instanceof RadioNodeList) {
        const checked = Array.from(field).find(function (item) {
          return item.checked;
        });
        values[name] = checked ? checked.value : "";
        return;
      }

      if (field.type === "checkbox") {
        values[name] = field.checked ? (field.value || "1") : "";
        return;
      }

      values[name] = field.value || "";
    });

    return values;
  }

  function writePersistentFilter(storageKey, values) {
    const hasValue = Object.keys(values).some(function (name) {
      return String(values[name] || "").trim() !== "";
    });

    if (hasValue) {
      profileStorage.setItem(storageKey, JSON.stringify(values));
    } else {
      profileStorage.removeItem(storageKey);
    }
  }

  function restorePersistentFilter(form, storageKey, routeValue, fieldNames) {
    let values = null;

    try {
      values = JSON.parse(profileStorage.getItem(storageKey) || "null");
    } catch (error) {
      values = null;
    }

    if (!values || typeof values !== "object") {
      return;
    }

    const hasValue = fieldNames.some(function (name) {
      return String(values[name] || "").trim() !== "";
    });

    if (!hasValue) {
      return;
    }

    const url = new URL(window.location.href);
    if (routeValue) {
      url.searchParams.set("route", routeValue);
    }

    fieldNames.forEach(function (name) {
      const value = String(values[name] || "");

      if (value.trim() === "") {
        url.searchParams.delete(name);
      } else {
        url.searchParams.set(name, value);
      }
    });

    if (url.href !== window.location.href) {
      window.location.replace(url.href);
    }
  }

  function initMobileNotifications() {
    const root = document.querySelector("[data-mobile-notifications='1']");
    if (!root || !root.dataset.mobileNotificationUrl || !root.dataset.csrf) {
      return;
    }

    const supportBlocker = mobileNotificationBlocker();
    if (supportBlocker) {
      showPhonePermissionBanner(supportBlocker.title, supportBlocker.message, null, null, "Anladım", supportBlocker.key);
      return;
    }

    if (!("Notification" in window)) {
      showPhonePermissionBanner(
        "Bildirim desteklenmiyor",
        "Bu tarayıcı cihaz bildirimi göstermiyor. Panel açıkken canlı ekran güncellenmeye devam eder.",
        null,
        null,
        "Anladım",
        "mobileNotificationNoApiClosed"
      );
      return;
    }

    if (Notification.permission === "granted") {
      activateWebPush(root);
      startMobileNotificationPolling(root);
      return;
    }

    if (Notification.permission === "denied") {
      showPhonePermissionBanner(
        "Bildirim izni kapalı",
        "Bu cihazda bildirim izni reddedilmiş. Tarayıcı veya iPhone bildirim ayarlarından izin açılmadan bildirim gönderilemez.",
        null,
        null,
        "Anladım",
        "mobileNotificationDeniedClosed"
      );
      return;
    }

    if (sessionStorage.getItem("mobileNotificationPromptClosed") === "1") {
      return;
    }

    showPhonePermissionBanner(
      "Telefon bildirimleri",
      "Yeni giriş ve departman onay kayıtları için cihazınızda bildirim gösterebiliriz.",
      "İzin Ver",
      function (prompt) {
        Notification.requestPermission().then(function (permission) {
          prompt.remove();

          if (permission === "granted") {
            showSystemNotification({
              title: "Telefon bildirimleri aktif",
              message: "Yeni giriş ve departman onay kayıtlarında bu cihaza bildirim gelecek.",
              url: "/index.php?route=%2Fdashboard",
              id: "permission-ok"
            });
            activateWebPush(root);
            startMobileNotificationPolling(root);
          }
        });
      },
      "Sonra",
      "mobileNotificationPromptClosed"
    );
  }

  function activateWebPush(root) {
    if (!("serviceWorker" in navigator) || !root.dataset.webPushConfigUrl || !root.dataset.webPushSubscribeUrl) {
      return;
    }

    if (!("PushManager" in window)) {
      showPhonePermissionBanner(
        "Web Push sınırlı",
        "Bu tarayıcı gerçek Web Push aboneliği desteklemiyor. Uygulama açıkken bildirim yedeği çalışır.",
        null,
        null,
        "Anladım",
        "mobileNotificationNoPushClosed"
      );
      return;
    }

    fetch(root.dataset.webPushConfigUrl, {
      method: "GET",
      credentials: "same-origin",
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    })
      .then(function (response) {
        return response.ok ? response.json() : null;
      })
      .then(function (payload) {
        if (!payload || !payload.enabled || !payload.publicKey) {
          return null;
        }

        return navigator.serviceWorker.ready.then(function (registration) {
          return registration.pushManager.getSubscription().then(function (subscription) {
            if (subscription) {
              return subscription;
            }

            return registration.pushManager.subscribe({
              userVisibleOnly: true,
              applicationServerKey: urlBase64ToUint8Array(payload.publicKey)
            });
          });
        });
      })
      .then(function (subscription) {
        if (subscription) {
          return sendWebPushSubscription(root, subscription);
        }

        return null;
      })
      .catch(function (error) {
        showPhonePermissionBanner(
          "Web Push aboneliği alınamadı",
          error && error.message ? error.message : "Cihaz aboneliği kaydedilemedi. Panel açıkken bildirim yedeği çalışır.",
          null,
          null,
          "Anladım",
          "mobileNotificationSubscribeErrorClosed"
        );
        startMobileNotificationPolling(root);
      });
  }

  function sendWebPushSubscription(root, subscription) {
    const body = new FormData();
    const payload = subscription.toJSON ? subscription.toJSON() : {};
    payload.contentEncoding = preferredPushEncoding();

    body.append("_csrf", root.dataset.csrf);
    body.append("subscription", JSON.stringify(payload));

    return fetch(root.dataset.webPushSubscribeUrl, {
      method: "POST",
      body,
      credentials: "same-origin",
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    })
      .then(function (response) {
        if (response.ok) {
          root.dataset.webPushSubscribed = "1";
          return response.json().catch(function () {
            return { ok: true };
          });
        }

        return response.json()
          .then(function (payload) {
            throw new Error(payload && payload.message ? payload.message : "Web Push aboneliği sunucuya kaydedilemedi.");
          })
          .catch(function (error) {
            throw error instanceof Error ? error : new Error("Web Push aboneliği sunucuya kaydedilemedi.");
          });
      });
  }

  function preferredPushEncoding() {
    if (window.PushManager && PushManager.supportedContentEncodings && typeof PushManager.supportedContentEncodings.includes === "function") {
      return PushManager.supportedContentEncodings.includes("aes128gcm") ? "aes128gcm" : PushManager.supportedContentEncodings[0];
    }

    return "aes128gcm";
  }

  function urlBase64ToUint8Array(value) {
    const padding = "=".repeat((4 - value.length % 4) % 4);
    const base64 = (value + padding).replace(/-/g, "+").replace(/_/g, "/");
    const rawData = window.atob(base64);
    const output = new Uint8Array(rawData.length);

    for (let index = 0; index < rawData.length; index += 1) {
      output[index] = rawData.charCodeAt(index);
    }

    return output;
  }

  function startMobileNotificationPolling(root) {
    if (root.dataset.mobileNotificationPolling === "1") {
      return;
    }

    root.dataset.mobileNotificationPolling = "1";
    let busy = false;

    function poll() {
      if (busy || Notification.permission !== "granted") {
        return;
      }

      busy = true;
      const body = new FormData();
      body.append("_csrf", root.dataset.csrf);

      fetch(root.dataset.mobileNotificationUrl, {
        method: "POST",
        body,
        credentials: "same-origin",
        headers: {
          "X-Requested-With": "XMLHttpRequest"
        }
      })
        .then(function (response) {
          return response.ok ? response.json() : null;
        })
        .then(function (payload) {
          if (!payload || !Array.isArray(payload.notifications)) {
            return;
          }

          payload.notifications.forEach(showSystemNotification);
        })
        .catch(function () {})
        .finally(function () {
          busy = false;
        });
    }

    window.setTimeout(poll, 1500);
    window.setInterval(poll, 12000);
  }

  function showSystemNotification(item) {
    const title = item.title || "Otel Güvenlik";
    const options = {
      body: item.message || "",
      icon: "/icons/icon-192.png",
      badge: "/icons/icon-192.png",
      tag: "otel-security-" + (item.id || Date.now()),
      data: {
        url: item.url || "/index.php?route=%2Fdashboard"
      }
    };

    if ("serviceWorker" in navigator) {
      navigator.serviceWorker.ready
        .then(function (registration) {
          registration.showNotification(title, options);
        })
        .catch(function () {
          new Notification(title, options);
        });
      return;
    }

    new Notification(title, options);
  }

  function mobileNotificationBlocker() {
    if (!window.isSecureContext) {
      return {
        key: "mobileNotificationInsecureClosed",
        title: "HTTPS gerekli",
        message: "Telefon bildirimi için uygulama güvenli HTTPS bağlantısı üzerinden açılmalıdır."
      };
    }

    if (isIosDevice() && !isStandaloneMode()) {
      return {
        key: "mobileNotificationIosInstallClosed",
        title: "iPhone bildirimi için uygulamayı kurun",
        message: "Safari'de Paylaş menüsünden Ana Ekrana Ekle deyin, sonra uygulamayı ana ekran ikonundan açıp tekrar giriş yapın."
      };
    }

    if (!("serviceWorker" in navigator)) {
      return {
        key: "mobileNotificationNoWorkerClosed",
        title: "Bildirim desteklenmiyor",
        message: "Bu tarayıcı service worker desteği vermiyor. Panel açıkken canlı ekran güncellenmeye devam eder."
      };
    }

    return null;
  }

  function showPhonePermissionBanner(title, message, primaryLabel, primaryHandler, closeLabel, storageKey) {
    if (storageKey && sessionStorage.getItem(storageKey) === "1") {
      return;
    }

    const existing = document.querySelector("[data-phone-permission-banner]");
    if (existing) {
      existing.remove();
    }

    const prompt = document.createElement("section");
    prompt.className = "phone-permission-banner";
    prompt.dataset.phonePermissionBanner = "1";
    prompt.innerHTML = [
      "<div>",
      "<strong></strong>",
      "<small></small>",
      "</div>",
      primaryLabel ? "<button class=\"primary-action\" type=\"button\" data-phone-permission-primary></button>" : "",
      "<button class=\"ghost-link\" type=\"button\" data-phone-permission-close></button>"
    ].join("");

    const titleNode = prompt.querySelector("strong");
    const messageNode = prompt.querySelector("small");
    const primaryButton = prompt.querySelector("[data-phone-permission-primary]");
    const closeButton = prompt.querySelector("[data-phone-permission-close]");

    if (titleNode) {
      titleNode.textContent = title;
    }

    if (messageNode) {
      messageNode.textContent = message;
    }

    if (primaryButton) {
      primaryButton.textContent = primaryLabel || "";
      primaryButton.addEventListener("click", function () {
        if (typeof primaryHandler === "function") {
          primaryHandler(prompt);
        }
      });
    }

    if (closeButton) {
      closeButton.textContent = closeLabel || "Kapat";
      closeButton.addEventListener("click", function () {
        if (storageKey) {
          sessionStorage.setItem(storageKey, "1");
        }
        prompt.remove();
      });
    }

    document.body.appendChild(prompt);
  }

  function isIosDevice() {
    return /iPad|iPhone|iPod/i.test(navigator.userAgent)
      || (navigator.platform === "MacIntel" && navigator.maxTouchPoints > 1);
  }

  function isStandaloneMode() {
    return window.matchMedia("(display-mode: standalone)").matches || window.navigator.standalone === true;
  }

  function initAdminSortableCards() {
    const grid = document.querySelector("[data-admin-sortable]");
    if (!grid) {
      return;
    }

    const storageKey = "otelSecurityAdminCardOrder";
    const toolbar = document.querySelector("[data-admin-sortable-toolbar]");
    const resetButton = document.querySelector("[data-admin-sort-reset]");
    let cards = Array.from(grid.querySelectorAll(".admin-card"));
    let draggedCard = null;
    let dragStartedAt = 0;

    if (!cards.length) {
      return;
    }

    cards.forEach(function (card, index) {
      const key = card.dataset.adminCard || card.getAttribute("href") || String(index);
      card.dataset.adminCard = key;
      card.dataset.defaultOrder = String(index);
      card.draggable = true;
      card.title = card.title || "Sürükleyerek sırala";
    });

    applySavedOrder();
    cards = Array.from(grid.querySelectorAll(".admin-card"));

    if (toolbar) {
      toolbar.hidden = false;
    }

    cards.forEach(function (card) {
      card.addEventListener("dragstart", function (event) {
        draggedCard = card;
        dragStartedAt = Date.now();
        card.classList.add("is-dragging");
        grid.classList.add("is-sorting");

        if (event.dataTransfer) {
          event.dataTransfer.effectAllowed = "move";
          event.dataTransfer.setData("text/plain", card.dataset.adminCard || "");
        }
      });

      card.addEventListener("dragend", function () {
        clearDragState();
        saveOrder();
      });

      card.addEventListener("click", function (event) {
        if (Date.now() - dragStartedAt < 250 && card.classList.contains("was-dragged")) {
          event.preventDefault();
        }

        card.classList.remove("was-dragged");
      });
    });

    grid.addEventListener("dragover", function (event) {
      if (!draggedCard) {
        return;
      }

      event.preventDefault();
      const target = event.target instanceof Element ? event.target.closest(".admin-card") : null;
      if (!target || target === draggedCard || !grid.contains(target)) {
        return;
      }

      cards.forEach(function (card) {
        card.classList.toggle("is-drag-target", card === target);
      });

      const rect = target.getBoundingClientRect();
      const after = event.clientY > rect.top + rect.height / 2 || event.clientX > rect.left + rect.width / 2;
      grid.insertBefore(draggedCard, after ? target.nextSibling : target);
      draggedCard.classList.add("was-dragged");
    });

    grid.addEventListener("drop", function (event) {
      if (draggedCard) {
        event.preventDefault();
        saveOrder();
      }

      clearDragState();
    });

    if (resetButton) {
      resetButton.addEventListener("click", function () {
        profileStorage.removeItem(storageKey);
        Array.from(grid.querySelectorAll(".admin-card"))
          .sort(function (a, b) {
            return Number(a.dataset.defaultOrder || 0) - Number(b.dataset.defaultOrder || 0);
          })
          .forEach(function (card) {
            grid.appendChild(card);
          });
      });
    }

    function applySavedOrder() {
      let order = [];
      try {
        order = JSON.parse(profileStorage.getItem(storageKey) || "[]");
      } catch (error) {
        order = [];
      }

      if (!Array.isArray(order) || !order.length) {
        return;
      }

      const indexedCards = new Map(cards.map(function (card) {
        return [card.dataset.adminCard, card];
      }));

      order.forEach(function (key) {
        const card = indexedCards.get(key);
        if (card) {
          grid.appendChild(card);
        }
      });

      cards.forEach(function (card) {
        if (!order.includes(card.dataset.adminCard)) {
          grid.appendChild(card);
        }
      });
    }

    function saveOrder() {
      const order = Array.from(grid.querySelectorAll(".admin-card"))
        .map(function (card) {
          return card.dataset.adminCard;
        })
        .filter(Boolean);

      profileStorage.setItem(storageKey, JSON.stringify(order));
    }

    function clearDragState() {
      grid.classList.remove("is-sorting");
      cards.forEach(function (card) {
        card.classList.remove("is-dragging", "is-drag-target");
      });
      draggedCard = null;
    }
  }

  function initPermissionPresets() {
    document.querySelectorAll("[data-permission-workspace]").forEach(function (workspace) {
      if (workspace.dataset.permissionWorkspaceBound === "1") {
        return;
      }

      workspace.dataset.permissionWorkspaceBound = "1";
      const checkboxes = Array.from(workspace.querySelectorAll("[data-permission-code]"));
      const presetButtons = Array.from(workspace.querySelectorAll("[data-permission-preset]"));
      const summary = workspace.querySelector("[data-permission-summary]");

      function updatePermissionState() {
        const selected = checkboxes
          .filter(function (input) {
            return input.checked;
          })
          .map(function (input) {
            return input.dataset.permissionCode || "";
          })
          .filter(Boolean);
        const selectedSet = new Set(selected);

        checkboxes.forEach(function (input) {
          const card = input.closest(".permission-card");
          if (card) {
            card.classList.toggle("is-enabled", input.checked);
          }
        });

        presetButtons.forEach(function (button) {
          const preset = String(button.dataset.permissionPreset || "")
            .split(",")
            .map(function (code) {
              return code.trim();
            })
            .filter(Boolean);
          const isActive = preset.length === selected.length && preset.every(function (code) {
            return selectedSet.has(code);
          });

          button.classList.toggle("is-active", isActive);
        });

        if (summary) {
          summary.textContent = selected.length + " / " + checkboxes.length + " açık";
        }
      }

      presetButtons.forEach(function (button) {
        button.addEventListener("click", function () {
          const allowed = new Set(String(button.dataset.permissionPreset || "")
            .split(",")
            .map(function (code) {
              return code.trim();
            })
            .filter(Boolean));

          checkboxes.forEach(function (input) {
            input.checked = allowed.has(input.dataset.permissionCode || "");
          });
          updatePermissionState();
        });
      });

      checkboxes.forEach(function (input) {
        input.addEventListener("change", updatePermissionState);
      });

      workspace.querySelectorAll("[data-permission-group-toggle]").forEach(function (button) {
        button.addEventListener("click", function () {
          const group = button.closest(".permission-group");
          const groupCard = button.closest(".permission-group-card");
          const scope = group || groupCard;
          const groupInputs = scope ? Array.from(scope.querySelectorAll("[data-permission-code]")) : [];
          const shouldCheck = groupInputs.some(function (input) {
            return !input.checked;
          });

          groupInputs.forEach(function (input) {
            input.checked = shouldCheck;
          });
          updatePermissionState();
        });
      });

      updatePermissionState();
    });
  }

  function initWhatsAppWizard() {
    const forms = Array.from(document.querySelectorAll("[data-whatsapp-wizard]"));
    if (!forms.length) {
      return;
    }

    forms.forEach(function (form) {
      const provider = form.querySelector("[data-whatsapp-provider]");
      const apiVersion = form.querySelector("[data-whatsapp-api-version]");
      const phoneId = form.querySelector("[data-whatsapp-phone-id]");
      const businessId = form.querySelector("[data-whatsapp-business-id]");
      const token = form.querySelector("[data-whatsapp-token]");
      const endpoint = form.querySelector("[data-whatsapp-endpoint]");
      const endpointRow = form.querySelector("[data-custom-endpoint-row]");
      const preview = form.querySelector("[data-whatsapp-endpoint-preview]");
      const status = form.querySelector("[data-whatsapp-status]");
      const configured = form.querySelector("input[name='configured']");
      const recipient = form.querySelector("input[name='test_recipient']");
      const senderPhone = form.querySelector("[data-whatsapp-phone-format]");

      [provider, apiVersion, phoneId, businessId, token].forEach(function (field) {
        if (field) {
          field.addEventListener("input", syncWhatsAppWizard);
          field.addEventListener("change", syncWhatsAppWizard);
        }
      });

      [recipient, senderPhone].forEach(function (field) {
        if (!field) {
          return;
        }

        field.addEventListener("blur", function () {
          const normalized = normalizeWhatsAppPhone(field.value);
          if (normalized) {
            field.value = normalized;
          }
        });
      });

      form.addEventListener("submit", function (event) {
        const submitter = event.submitter;
        const action = submitter && submitter.name === "action" ? submitter.value : "";

        if (!["save_whatsapp_wizard", "complete_whatsapp_integration", "validate_whatsapp", "test"].includes(action)) {
          return;
        }

        if (provider && provider.value !== "meta_cloud") {
          return;
        }

        const missing = missingWhatsAppFields(action);
        if (missing.length) {
          event.preventDefault();
          window.alert("WhatsApp kurulumu için eksik alanlar: " + missing.join(", "));
        }
      });

      syncWhatsAppWizard();

      function syncWhatsAppWizard() {
        const selectedProvider = provider ? provider.value : "meta_cloud";
        const isMeta = selectedProvider === "meta_cloud";

        if (endpointRow) {
          endpointRow.hidden = isMeta;
        }

        if (isMeta && endpoint && endpoint.value.trim() !== "") {
          endpoint.value = "";
        }

        if (preview) {
          const version = (apiVersion && apiVersion.value.trim()) || "v25.0";
          const id = phoneId && phoneId.value.trim();
          const business = businessId && businessId.value.trim();
          preview.textContent = isMeta
            ? (id ? `https://graph.facebook.com/${version}/${id}/messages` : (business ? "Meta WABA üzerinden otomatik bulunacak" : `https://graph.facebook.com/${version}/{PHONE_NUMBER_ID}/messages`))
            : ((endpoint && endpoint.value.trim()) || "Özel HTTP endpoint");
        }

        const ready = missingWhatsAppFields("complete_whatsapp_integration").length === 0 || !isMeta;
        if (configured && ready) {
          configured.checked = true;
        }

        if (status) {
          status.textContent = ready ? "Hazır" : "Eksik";
          status.classList.toggle("ok", ready);
          status.classList.toggle("pending", !ready);
        }
      }

      function missingWhatsAppFields(action) {
        const fields = [];
        if (!apiVersion || apiVersion.value.trim() === "") {
          fields.push("API versiyonu");
        }

        const requiresExactPhoneId = action === "save_whatsapp_wizard" || action === "validate_whatsapp" || action === "test";
        const hasPhoneId = phoneId && phoneId.value.trim() !== "";
        const hasBusinessId = businessId && businessId.value.trim() !== "";

        if (requiresExactPhoneId && !hasPhoneId) {
          fields.push("Phone Number ID");
        } else if (!requiresExactPhoneId && !hasPhoneId && !hasBusinessId) {
          fields.push("WhatsApp Business Account ID veya Phone Number ID");
        }

        const hasToken = token && (token.value.trim() !== "" || token.dataset.tokenSaved === "1");
        if (!hasToken) {
          fields.push("Access token");
        }

        return fields;
      }
    });

    function normalizeWhatsAppPhone(value) {
      let digits = String(value || "").replace(/\D+/g, "");
      if (digits.startsWith("00")) {
        digits = digits.slice(2);
      }

      if (digits.startsWith("0") && digits.length === 11) {
        digits = "90" + digits.slice(1);
      } else if (digits.length === 10 && digits.startsWith("5")) {
        digits = "90" + digits;
      }

      return digits;
    }
  }

  function initSubmitLocks() {
    document.addEventListener("submit", function (event) {
      const form = event.target;
      if (!(form instanceof HTMLFormElement)) {
        return;
      }

      const method = String(form.getAttribute("method") || "get").toLowerCase();
      if (method !== "post" || form.dataset.submitLockDisabled === "1") {
        return;
      }

      if (event.defaultPrevented) {
        return;
      }

      if (form.dataset.submitLocked === "1") {
        event.preventDefault();
        return;
      }

      const submitter = event.submitter && event.submitter.matches("[type='submit'], button:not([type]), input[type='submit']")
        ? event.submitter
        : form.querySelector("button[type='submit'], button:not([type]), input[type='submit']");

      preserveSubmitterValue(form, submitter);
      lockSubmitButtons(form, submitter);
    });
  }

  function preserveSubmitterValue(form, submitter) {
    if (!submitter || !submitter.name) {
      return;
    }

    const hidden = document.createElement("input");
    hidden.type = "hidden";
    hidden.name = submitter.name;
    hidden.value = submitter.value || "";
    hidden.dataset.submitterMirror = "1";
    form.appendChild(hidden);
  }

  function lockSubmitButtons(form, submitter) {
    const buttons = Array.from(form.querySelectorAll("button[type='submit'], button:not([type]), input[type='submit']"));
    const lockSeconds = Number(form.dataset.submitLockSeconds || 2);
    const seconds = Number.isFinite(lockSeconds) ? Math.max(1, Math.min(30, lockSeconds)) : 2;

    form.dataset.submitLocked = "1";
    form.setAttribute("aria-busy", "true");

    buttons.forEach(function (button) {
      if (!button.dataset.originalText) {
        button.dataset.originalText = button.tagName === "INPUT" ? button.value : button.textContent;
      }

      button.disabled = true;
      button.classList.add("is-waiting");
    });

    let remaining = seconds;
    updateSubmitLockText(buttons, submitter, remaining);

    const timer = window.setInterval(function () {
      remaining -= 1;
      if (remaining <= 0) {
        window.clearInterval(timer);
        unlockSubmitButtons(form, buttons);
        return;
      }

      updateSubmitLockText(buttons, submitter, remaining);
    }, 1000);
  }

  function updateSubmitLockText(buttons, submitter, remaining) {
    buttons.forEach(function (button) {
      const text = button === submitter ? `Bekleyin ${remaining} sn` : "Bekleyin";
      if (button.tagName === "INPUT") {
        button.value = text;
      } else {
        button.textContent = text;
      }
    });
  }

  function unlockSubmitButtons(form, buttons) {
    form.dataset.submitLocked = "0";
    form.removeAttribute("aria-busy");

    buttons.forEach(function (button) {
      button.disabled = false;
      button.classList.remove("is-waiting");

      if (button.dataset.originalText) {
        if (button.tagName === "INPUT") {
          button.value = button.dataset.originalText;
        } else {
          button.textContent = button.dataset.originalText;
        }
      }
    });

    form.querySelectorAll("[data-submitter-mirror='1']").forEach(function (input) {
      input.remove();
    });
  }
})();
