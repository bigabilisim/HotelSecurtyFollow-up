const CACHE_NAME = "otel-guvenlik-pwa-v20";
const PRECACHE_URLS = [
  "/offline.html",
  "/assets/app.css",
  "/assets/dashboard.js",
  "/assets/pwa.js",
  "/icons/icon.svg",
  "/icons/icon-192.png",
  "/icons/icon-512.png"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;

  if (request.method !== "GET") {
    return;
  }

  const url = new URL(request.url);

  if (url.origin !== self.location.origin) {
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(
      fetch(request).catch(() => caches.match("/offline.html"))
    );
    return;
  }

  if (
    url.pathname.startsWith("/assets/") ||
    url.pathname.startsWith("/icons/") ||
    url.pathname === "/manifest.webmanifest" ||
    url.pathname === "/offline.html"
  ) {
    event.respondWith(
      caches.match(request).then((cached) => {
        if (cached) {
          return cached;
        }

        return fetch(request).then((response) => {
          const copy = response.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(request, copy));
          return response;
        });
      })
    );
  }
});

self.addEventListener("push", (event) => {
  let payload = {};

  if (event.data) {
    try {
      payload = event.data.json();
    } catch (error) {
      payload = {
        title: "Otel Güvenlik",
        message: event.data.text()
      };
    }
  }

  const title = payload.title || "Otel Güvenlik";
  const actions = Array.isArray(payload.actions)
    ? payload.actions.filter((action) => action && action.action && action.title).slice(0, 2)
    : [];
  const options = {
    body: payload.message || "Yeni bildirim var.",
    icon: "/icons/icon-192.png",
    badge: "/icons/icon-192.png",
    actions,
    requireInteraction: payload.require_interaction === true,
    tag: "otel-security-" + (payload.id || Date.now()),
    data: {
      url: payload.url || "/index.php?route=%2Fdashboard",
      actionUrls: payload.action_urls || {}
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener("notificationclick", (event) => {
  event.notification.close();

  const action = event.action || "";
  if (action === "yes" || action === "no") {
    event.waitUntil(answerDepartmentQuestion(event.notification, action));
    return;
  }

  const targetUrl = event.notification.data && event.notification.data.url
    ? event.notification.data.url
    : "/index.php?route=%2Fdashboard";

  event.waitUntil(openTargetWindow(targetUrl));
});

function answerDepartmentQuestion(notification, action) {
  const actionUrls = notification.data && notification.data.actionUrls
    ? notification.data.actionUrls
    : {};
  const targetUrl = actionUrls[action];

  if (!targetUrl) {
    return openTargetWindow(notification.data && notification.data.url ? notification.data.url : "/index.php?route=%2Fdashboard");
  }

  return fetch(targetUrl, {
    method: "GET",
    credentials: "include",
    cache: "no-store"
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error("Cevap kaydedilemedi");
      }

      return self.registration.showNotification("Cevap kaydedildi", {
        body: action === "yes"
          ? "Evet cevabınız güvenlik ekibine iletildi."
          : "Hayır cevabınız kaydedildi, eskalasyon süreci başlatıldı.",
        icon: "/icons/icon-192.png",
        badge: "/icons/icon-192.png",
        tag: (notification.tag || "otel-security-response") + "-answered",
        data: {
          url: "/index.php?route=%2Fdashboard"
        }
      });
    })
    .catch(() => openTargetWindow(targetUrl));
}

function openTargetWindow(targetUrl) {
  return clients.matchAll({ type: "window", includeUncontrolled: true }).then((clientList) => {
    for (const client of clientList) {
      if ("focus" in client) {
        client.navigate(targetUrl);
        return client.focus();
      }
    }

    if (clients.openWindow) {
      return clients.openWindow(targetUrl);
    }

    return null;
  });
}
