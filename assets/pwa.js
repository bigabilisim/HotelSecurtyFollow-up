(function () {
  const securityAlert = document.querySelector("[data-security-alert]");
  const securityAlertClose = document.querySelector("[data-security-alert-close]");
  initSubmitLocks();
  initAdminSortableCards();

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

  if (!("serviceWorker" in navigator)) {
    return;
  }

  window.addEventListener("load", function () {
    navigator.serviceWorker.register("/service-worker.js").catch(function () {
      // PWA kurulumu desteklenmiyorsa panel normal web uygulaması olarak çalışır.
    });
  });

  function initMobileNotifications() {
    const root = document.querySelector("[data-mobile-notifications='1']");
    if (!root || !root.dataset.mobileNotificationUrl || !root.dataset.csrf) {
      return;
    }

    if (!("Notification" in window)) {
      return;
    }

    if (Notification.permission === "granted") {
      activateWebPush(root);
      startMobileNotificationPolling(root);
      return;
    }

    if (Notification.permission === "denied" || sessionStorage.getItem("mobileNotificationPromptClosed") === "1") {
      return;
    }

    const prompt = document.createElement("section");
    prompt.className = "phone-permission-banner";
    prompt.innerHTML = [
      "<div>",
      "<strong>Telefon bildirimleri</strong>",
      "<small>Yeni giriş kayıtları için cihazınızda bildirim gösterebiliriz.</small>",
      "</div>",
      "<button class=\"primary-action\" type=\"button\" data-phone-permission-allow>İzin Ver</button>",
      "<button class=\"ghost-link\" type=\"button\" data-phone-permission-close>Sonra</button>"
    ].join("");

    document.body.appendChild(prompt);

    const allowButton = prompt.querySelector("[data-phone-permission-allow]");
    const closeButton = prompt.querySelector("[data-phone-permission-close]");

    allowButton.addEventListener("click", function () {
      Notification.requestPermission().then(function (permission) {
        prompt.remove();

        if (permission === "granted") {
          showSystemNotification({
            title: "Telefon bildirimleri aktif",
            message: "Yeni giriş kayıtlarında bu cihaza bildirim gelecek.",
            url: "/index.php?route=%2Fdashboard",
            id: "permission-ok"
          });
          activateWebPush(root);
          startMobileNotificationPolling(root);
        }
      });
    });

    closeButton.addEventListener("click", function () {
      sessionStorage.setItem("mobileNotificationPromptClosed", "1");
      prompt.remove();
    });
  }

  function activateWebPush(root) {
    if (!("serviceWorker" in navigator) || !("PushManager" in window) || !root.dataset.webPushConfigUrl || !root.dataset.webPushSubscribeUrl) {
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
          sendWebPushSubscription(root, subscription);
        }
      })
      .catch(function () {
        startMobileNotificationPolling(root);
      });
  }

  function sendWebPushSubscription(root, subscription) {
    const body = new FormData();
    const payload = subscription.toJSON ? subscription.toJSON() : {};
    payload.contentEncoding = preferredPushEncoding();

    body.append("_csrf", root.dataset.csrf);
    body.append("subscription", JSON.stringify(payload));

    fetch(root.dataset.webPushSubscribeUrl, {
      method: "POST",
      body,
      credentials: "same-origin",
      headers: {
        "X-Requested-With": "XMLHttpRequest"
      }
    }).catch(function () {});
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

  function initAdminSortableCards() {
    const grid = document.querySelector("[data-admin-sortable]");
    if (!grid || !("localStorage" in window)) {
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
        localStorage.removeItem(storageKey);
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
        order = JSON.parse(localStorage.getItem(storageKey) || "[]");
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

      localStorage.setItem(storageKey, JSON.stringify(order));
    }

    function clearDragState() {
      grid.classList.remove("is-sorting");
      cards.forEach(function (card) {
        card.classList.remove("is-dragging", "is-drag-target");
      });
      draggedCard = null;
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
    const lockSeconds = Number(form.dataset.submitLockSeconds || 5);
    const seconds = Number.isFinite(lockSeconds) ? Math.max(1, Math.min(30, lockSeconds)) : 5;

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
