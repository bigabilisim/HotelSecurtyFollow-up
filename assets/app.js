const categorySettings = {
  VIP: { short: "VIP", limit: null, badge: "vip", notify: "Patron + Genel Müdür" },
  "Rezervasyonsuz Giriş": { short: "REZ", limit: 30, badge: "vip", notify: "Geldiği Departman" },
  "Tedarikçi": { short: "TED", limit: 60, badge: "supplier", notify: "Satın Alma + Teknik Amir" },
  "Günübirlik": { short: "GÜN", limit: 480, badge: "visitor", notify: "Geldiği Departman" },
  Acenta: { short: "ACE", limit: 120, badge: "audit", notify: "Ön Büro + Genel Müdürlük" },
  "İş Görüşmesi": { short: "İŞ", limit: 60, badge: "visitor", notify: "Geldiği Departman" },
  "Ziyaretçi": { short: "ZİY", limit: 120, badge: "visitor", notify: "Geldiği Departman" },
  "Teknik Servis": { short: "TEK", limit: 90, badge: "staff", notify: "Teknik Servis Amirliği" },
  Animasyon: { short: "ANI", limit: 180, badge: "audit", notify: "Ön Büro + Operasyon" },
  "Denetçi": { short: "DEN", limit: 30, badge: "audit", notify: "Genel Müdür + Güvenlik Müdürü" }
};

const storageKeys = {
  installed: "hotelSecurityInstalled",
  settings: "hotelSecuritySettings",
  backups: "hotelSecurityBackups"
};

const defaultSettings = {
  hotelName: "Otel Güvenlik",
  domain: "security.hotel.local",
  logoText: "OG",
  logoData: "",
  adminName: "Sistem Yöneticisi",
  adminEmail: "",
  telegramBot: "@otel_guvenlik_bot",
  whatsappProvider: "Meta WhatsApp Cloud API",
  autoBackup: true,
  backupFrequency: "daily",
  retentionDays: 30,
  backupTarget: "Sunucu yedek klasörü"
};

const sampleVisitors = [
  {
    id: 1,
    name: "Ahmet Yılmaz",
    category: "VIP",
    department: "Genel Müdürlük",
    company: "ABC Holding",
    phone: "0532 000 11 22",
    plate: "34 VIP 001",
    entryAt: minutesAgo(8),
    note: "Randevulu görüşme"
  },
  {
    id: 2,
    name: "Mehmet Kaya",
    category: "Tedarikçi",
    department: "Teknik Servis",
    company: "Kaya Elektrik",
    phone: "0544 123 45 67",
    plate: "34 KYA 456",
    entryAt: minutesAgo(68),
    note: "Servis bakımı",
    askedDepartment: true
  },
  {
    id: 3,
    name: "Selin Acar",
    category: "Ziyaretçi",
    department: "İnsan Kaynakları",
    company: "Aday görüşmesi",
    phone: "0555 888 77 66",
    plate: "",
    entryAt: minutesAgo(106),
    note: "İK görüşmesi"
  },
  {
    id: 4,
    name: "Cem Arslan",
    category: "Denetçi",
    department: "Ön Büro",
    company: "Belgelendirme Kurumu",
    phone: "0533 219 80 10",
    plate: "06 DEN 090",
    entryAt: minutesAgo(38),
    note: "Evrak denetimi",
    askedDepartment: true,
    escalated: true
  },
  {
    id: 5,
    name: "Elif Demir",
    category: "Tedarikçi",
    department: "Satın Alma",
    company: "Demir Gıda",
    phone: "0541 777 20 20",
    plate: "34 GDA 212",
    entryAt: minutesAgo(16),
    note: "Numune teslimi"
  }
];

const archivedVisitors = [
  {
    name: "Ayşe Demir",
    category: "Ziyaretçi",
    department: "Ön Büro",
    company: "Misafir",
    phone: "0555 310 44 22",
    plate: "34 ASD 060",
    note: "Misafir görüşmesi"
  },
  {
    name: "Burak Şahin",
    category: "Ziyaretçi",
    department: "Genel Müdürlük",
    company: "Misafir",
    phone: "0530 444 12 12",
    plate: "",
    note: "Randevulu ziyaret"
  },
  {
    name: "Nur Koç",
    category: "Tedarikçi",
    department: "Satın Alma",
    company: "Koç Tekstil",
    phone: "0542 600 70 80",
    plate: "35 NUR 035",
    note: "Malzeme teslimi"
  }
];

const visitorProfiles = new Map();

let appSettings = loadSettings();
let backupHistory = loadBackups();
let visitors = [...sampleVisitors];
let feed = [
  { at: minutesAgo(1), text: "Cem Arslan için olumsuz departman cevabı sonrası Güvenlik Müdürü ve Genel Müdür bilgilendirildi." },
  { at: minutesAgo(4), text: "Mehmet Kaya süre aşımı nedeniyle Teknik Servis amirine soruldu: Sizinle beraber mi?" },
  { at: minutesAgo(8), text: "Ahmet Yılmaz VIP kategorisiyle giriş yaptı. Patron ve Genel Müdür Telegram üzerinden bilgilendirildi." },
  { at: minutesAgo(17), text: "Elif Demir tedarikçi girişi yaptı. Satın Alma departmanı bilgilendirildi." },
  { at: minutesAgo(35), text: "Ayşe Demir çıkış yaptı. İçeride kalma süresi: 42 dakika." }
];

let nextId = 6;
let todayCount = 42;
let notificationCount = 18;

const entryForm = document.querySelector("#entryForm");
const visitorList = document.querySelector("#visitorList");
const searchInput = document.querySelector("#searchInput");
const feedBox = document.querySelector("#feed");
const alertBox = document.querySelector("#alertBox");
const rowTemplate = document.querySelector("#visitorRowTemplate");
const feedTemplate = document.querySelector("#feedItemTemplate");
const clock = document.querySelector("#clock");
const simulateBtn = document.querySelector("#simulateBtn");
const visitorNameInput = document.querySelector("#visitorNameInput");
const knownVisitorsList = document.querySelector("#knownVisitors");
const autofillStatus = document.querySelector("#autofillStatus");
const hotelNameLabel = document.querySelector("#hotelNameLabel");
const siteDomainLabel = document.querySelector("#siteDomainLabel");
const brandLogo = document.querySelector("#brandLogo");
const openWizardBtn = document.querySelector("#openWizardBtn");
const setupWizard = document.querySelector("#setupWizard");
const closeWizardBtn = document.querySelector("#closeWizardBtn");
const wizardForm = document.querySelector("#wizardForm");
const wizardStepLabel = document.querySelector("#wizardStepLabel");
const wizardNextBtn = document.querySelector("#wizardNextBtn");
const wizardBackBtn = document.querySelector("#wizardBackBtn");
const wizardLogoPreview = document.querySelector("#wizardLogoPreview");
const logoFileInput = document.querySelector("#logoFileInput");
const wizardBackupFile = document.querySelector("#wizardBackupFile");
const restoreBackupBtn = document.querySelector("#restoreBackupBtn");
const restoreWizardBtn = document.querySelector("#restoreWizardBtn");
const restoreStatus = document.querySelector("#restoreStatus");
const createBackupBtn = document.querySelector("#createBackupBtn");
const downloadBackupBtn = document.querySelector("#downloadBackupBtn");
const backupHistoryList = document.querySelector("#backupHistoryList");
const plateInput = entryForm.elements.plate;
let lastAutofilledName = "";
let wizardStep = 0;
let pendingLogoData = appSettings.logoData;

entryForm.addEventListener("submit", (event) => {
  event.preventDefault();
  const formData = new FormData(entryForm);
  const category = formData.get("category");
  const visitor = {
    id: nextId++,
    name: cleanText(formData.get("name")) || "İsimsiz Ziyaretçi",
    category,
    department: formData.get("department"),
    company: cleanText(formData.get("company")) || "Belirtilmedi",
    phone: cleanText(formData.get("phone")),
    plate: formatVehiclePlate(formData.get("plate")),
    note: cleanText(formData.get("note")),
    entryAt: new Date(),
    isNew: true
  };

  upsertVisitorProfile(visitor);
  visitors = [visitor, ...visitors];
  todayCount += 1;
  notificationCount += category === "VIP" || category === "Denetçi" ? 2 : 1;
  addFeed(`${visitor.name} ${category} kategorisiyle giriş yaptı. Bildirim: ${categorySettings[category].notify}.`);
  entryForm.reset();
  entryForm.querySelector('input[value="VIP"]').checked = true;
  lastAutofilledName = "";
  setAutofillStatus("");
  render();

  window.setTimeout(() => {
    visitor.isNew = false;
    render();
  }, 700);
});

searchInput.addEventListener("input", renderVisitors);
plateInput.addEventListener("input", () => {
  plateInput.value = formatVehiclePlate(plateInput.value);
});
plateInput.addEventListener("blur", () => {
  plateInput.value = formatVehiclePlate(plateInput.value);
});
visitorNameInput.addEventListener("input", handleKnownVisitorLookup);
visitorNameInput.addEventListener("change", handleKnownVisitorLookup);
openWizardBtn.addEventListener("click", () => openWizard("new"));
restoreWizardBtn.addEventListener("click", () => openWizard("restore"));
closeWizardBtn.addEventListener("click", closeWizard);
wizardBackBtn.addEventListener("click", () => setWizardStep(wizardStep - 1));
wizardNextBtn.addEventListener("click", handleWizardNext);
logoFileInput.addEventListener("change", handleLogoUpload);
restoreBackupBtn.addEventListener("click", () => restoreFromSelectedFile(wizardBackupFile));
createBackupBtn.addEventListener("click", () => createBackup("Manuel yedek"));
downloadBackupBtn.addEventListener("click", downloadLatestBackup);

simulateBtn.addEventListener("click", () => {
  const samples = [
    { name: "Burak Şahin", category: "Ziyaretçi", department: "Genel Müdürlük", company: "Misafir", plate: "" },
    { name: "Nur Koç", category: "Tedarikçi", department: "Satın Alma", company: "Koç Tekstil", plate: "35 NUR 035" },
    { name: "Mert Akın", category: "Denetçi", department: "Ön Büro", company: "Denetim Ofisi", plate: "34 DNT 780" }
  ];
  const picked = samples[Math.floor(Math.random() * samples.length)];
  const visitor = { id: nextId++, phone: "", note: "", entryAt: new Date(), isNew: true, ...picked };
  upsertVisitorProfile(visitor);
  visitors = [visitor, ...visitors];
  todayCount += 1;
  notificationCount += picked.category === "Denetçi" ? 2 : 1;
  addFeed(`${picked.name} için örnek ${picked.category.toLowerCase()} girişi oluşturuldu.`);
  render();
});

function render() {
  updateClock();
  processOverdueRules();
  renderVisitors();
  renderAlerts();
  renderFeed();
  renderMetrics();
  renderSettings();
  renderBackupPanel();
}

function renderMetrics() {
  document.querySelector("#metricToday").textContent = todayCount;
  document.querySelector("#metricInside").textContent = visitors.length;
  document.querySelector("#metricOverdue").textContent = visitors.filter((visitor) => {
    const state = getVisitorState(visitor);
    return state.level === "overdue" || state.level === "escalated";
  }).length;
  document.querySelector("#metricNotifications").textContent = notificationCount;
}

function renderVisitors() {
  const query = searchInput.value.trim().toLocaleLowerCase("tr-TR");
  visitorList.innerHTML = "";

  const filtered = visitors.filter((visitor) => {
    const haystack = `${visitor.name} ${visitor.category} ${visitor.department} ${visitor.company} ${visitor.plate}`.toLocaleLowerCase("tr-TR");
    return haystack.includes(query);
  });

  if (!filtered.length) {
    visitorList.innerHTML = '<div class="feed-item"><p>Aramaya uygun içeride kişi bulunamadı.</p></div>';
    return;
  }

  filtered.forEach((visitor) => {
    const state = getVisitorState(visitor);
    const config = categorySettings[visitor.category];
    const row = rowTemplate.content.firstElementChild.cloneNode(true);
    row.dataset.id = visitor.id;
    row.classList.add(state.level);
    if (visitor.isNew) row.classList.add("new");

    const badge = row.querySelector(".category-badge");
    badge.classList.add(config.badge);
    badge.textContent = config.short;

    row.querySelector(".person-name").textContent = visitor.name;
    row.querySelector(".person-meta").textContent = [visitor.company, visitor.plate].filter(Boolean).join(" | ");
    row.querySelector(".department-cell").textContent = visitor.department;
    row.querySelector(".duration-text").textContent = state.durationText;
    row.querySelector(".progress-track span").style.width = `${state.progress}%`;
    row.querySelector(".state-cell").innerHTML = `<span class="state-chip ${state.chip}">${state.text}</span>`;
    row.querySelector("[data-detail-phone]").textContent = visitor.phone || "-";
    row.querySelector("[data-detail-company]").textContent = visitor.company || "-";
    row.querySelector("[data-detail-plate]").textContent = visitor.plate || "-";
    row.querySelector("[data-detail-note]").textContent = visitor.note || "-";

    row.querySelector(".detail-button").addEventListener("click", () => toggleVisitorDetail(row));
    row.querySelector(".checkout-button").addEventListener("click", () => checkoutVisitor(visitor.id));
    visitorList.appendChild(row);
  });
}

function toggleVisitorDetail(row) {
  const panel = row.querySelector(".visitor-detail-panel");
  const button = row.querySelector(".detail-button");
  if (!panel || !button) return;

  const isOpen = !panel.hidden;
  panel.hidden = isOpen;
  button.textContent = isOpen ? "Detay" : "Gizle";
}

function renderAlerts() {
  const alerts = visitors
    .map((visitor) => ({ visitor, state: getVisitorState(visitor) }))
    .filter((item) => item.state.level === "overdue" || item.state.level === "escalated");

  alertBox.innerHTML = "";

  alerts.slice(0, 3).forEach(({ visitor, state }) => {
    const article = document.createElement("article");
    article.className = `department-alert ${state.level === "escalated" ? "risk" : ""}`;

    if (state.level === "escalated") {
      article.innerHTML = `
        <strong>${visitor.name} yöneticiye eskale edildi</strong>
        <p>${visitor.department} olumsuz cevap verdi veya kişi departmanda görünmüyor. Güvenlik Müdürü ve Genel Müdür bilgilendirildi.</p>
      `;
    } else {
      article.innerHTML = `
        <strong>${visitor.name} için departman sorusu</strong>
        <p>${visitor.department} amirine gönderilecek soru: Bu kişi sizinle beraber mi?</p>
        <div class="alert-actions">
          <button class="response-button" type="button" data-response="yes">Evet, beraber</button>
          <button class="response-button negative" type="button" data-response="no">Hayır</button>
        </div>
      `;
      article.querySelector('[data-response="yes"]').addEventListener("click", () => approveVisitor(visitor.id));
      article.querySelector('[data-response="no"]').addEventListener("click", () => escalateVisitor(visitor.id));
    }

    alertBox.appendChild(article);
  });
}

function renderFeed() {
  feedBox.innerHTML = "";
  feed.slice(0, 10).forEach((item, index) => {
    const row = feedTemplate.content.firstElementChild.cloneNode(true);
    if (index === 0) row.classList.add("live");
    row.querySelector("time").textContent = formatTime(item.at);
    row.querySelector("p").textContent = item.text;
    feedBox.appendChild(row);
  });
}

function processOverdueRules() {
  visitors.forEach((visitor) => {
    const config = categorySettings[visitor.category];
    if (!config.limit || visitor.escalated || visitor.departmentApproved) return;

    const elapsed = elapsedMinutes(visitor.entryAt);
    if (elapsed >= config.limit && !visitor.askedDepartment) {
      visitor.askedDepartment = true;
      notificationCount += 1;
      addFeed(`${visitor.name} süre aşımı nedeniyle ${visitor.department} amirine soruldu: Sizinle beraber mi?`, false);
    }
  });
}

function checkoutVisitor(id) {
  const visitor = visitors.find((item) => item.id === id);
  if (!visitor) return;

  if (!window.confirm(`${visitor.name} için çıkış kaydı oluşturulsun mu?`)) {
    return;
  }

  visitors = visitors.filter((item) => item.id !== id);
  addFeed(`${visitor.name} çıkış yaptı. İçeride kalma süresi: ${elapsedMinutes(visitor.entryAt)} dakika.`);
  render();
}

function approveVisitor(id) {
  const visitor = visitors.find((item) => item.id === id);
  if (!visitor) return;

  visitor.departmentApproved = true;
  visitor.extensionMinutes = (visitor.extensionMinutes || 0) + 30;
  visitor.askedDepartment = true;
  notificationCount += 1;
  addFeed(`${visitor.name} için ${visitor.department} amiri onay verdi. Süre 30 dakika uzatıldı.`);
  render();
}

function escalateVisitor(id) {
  const visitor = visitors.find((item) => item.id === id);
  if (!visitor) return;

  visitor.escalated = true;
  visitor.askedDepartment = true;
  notificationCount += 2;
  addFeed(`${visitor.name} için olumsuz cevap alındı. Güvenlik Müdürü ve Genel Müdür bilgilendirildi.`);
  render();
}

function renderSettings() {
  hotelNameLabel.textContent = appSettings.hotelName || defaultSettings.hotelName;
  siteDomainLabel.textContent = appSettings.domain || defaultSettings.domain;
  renderLogo(brandLogo, appSettings);

  document.querySelector("#installStatus").textContent = isInstalled() ? "Kurulu" : "İlk kurulum bekliyor";
  document.querySelector("#installDomain").textContent = `Domain: ${appSettings.domain || "-"}`;
  document.querySelector("#autoBackupStatus").textContent = appSettings.autoBackup ? "Aktif" : "Kapalı";
  document.querySelector("#backupFrequencyLabel").textContent = frequencyLabel(appSettings.backupFrequency);
  document.querySelector("#backupTargetLabel").textContent = appSettings.backupTarget;
  document.querySelector("#backupRetentionLabel").textContent = `${appSettings.retentionDays} gün`;

  const lastBackup = backupHistory[0];
  document.querySelector("#lastBackupTime").textContent = lastBackup ? formatDateTime(lastBackup.createdAt) : "Henüz yok";
}

function renderLogo(target, settings) {
  if (!target) return;

  if (settings.logoData) {
    target.innerHTML = `<img src="${settings.logoData}" alt="">`;
    return;
  }

  target.textContent = (settings.logoText || "OG").slice(0, 4).toLocaleUpperCase("tr-TR");
}

function renderBackupPanel() {
  backupHistoryList.innerHTML = "";

  if (!backupHistory.length) {
    backupHistoryList.innerHTML = '<div class="backup-history-item"><div><strong>Henüz yedek alınmadı</strong><small>İlk otomatik veya manuel yedek burada görünecek.</small></div></div>';
    return;
  }

  backupHistory.slice(0, 4).forEach((backup) => {
    const item = document.createElement("article");
    item.className = "backup-history-item";
    item.innerHTML = `
      <div>
        <strong>${backup.reason}</strong>
        <small>${formatDateTime(backup.createdAt)} | ${backup.visitors.length} içeride kayıt | ${backup.visitorProfiles.length} kişi hafızası</small>
      </div>
      <span class="state-chip ok">Hazır</span>
    `;
    backupHistoryList.appendChild(item);
  });
}

function openWizard(mode = "new") {
  populateWizardForm();
  wizardForm.elements.installMode.value = mode;
  setupWizard.classList.remove("hidden");
  setWizardStep(mode === "restore" ? 4 : 0);
}

function closeWizard() {
  setupWizard.classList.add("hidden");
  setRestoreStatus("");
}

function setWizardStep(step) {
  wizardStep = Math.max(0, Math.min(4, step));

  document.querySelectorAll(".wizard-step").forEach((element) => {
    element.classList.toggle("active", Number(element.dataset.step) === wizardStep);
  });

  wizardStepLabel.textContent = `${wizardStep + 1} / 5`;
  wizardBackBtn.disabled = wizardStep === 0;
  wizardBackBtn.style.visibility = wizardStep === 0 ? "hidden" : "visible";
  wizardNextBtn.textContent = wizardStep === 4 ? "Kurulumu Kaydet" : "Devam";
}

function handleWizardNext() {
  const installMode = new FormData(wizardForm).get("installMode");
  if (wizardStep === 0 && installMode === "restore") {
    setWizardStep(4);
    return;
  }

  if (wizardStep < 4) {
    setWizardStep(wizardStep + 1);
    return;
  }

  saveWizardSettings();
}

function populateWizardForm() {
  const form = wizardForm.elements;
  form.hotelName.value = appSettings.hotelName || "";
  form.domain.value = appSettings.domain || "";
  form.logoText.value = appSettings.logoText || "";
  form.adminName.value = appSettings.adminName || "";
  form.adminEmail.value = appSettings.adminEmail || "";
  form.telegramBot.value = appSettings.telegramBot || "";
  form.whatsappProvider.value = appSettings.whatsappProvider || defaultSettings.whatsappProvider;
  form.autoBackup.checked = Boolean(appSettings.autoBackup);
  form.backupFrequency.value = appSettings.backupFrequency || defaultSettings.backupFrequency;
  form.retentionDays.value = appSettings.retentionDays || defaultSettings.retentionDays;
  form.backupTarget.value = appSettings.backupTarget || defaultSettings.backupTarget;
  pendingLogoData = appSettings.logoData || "";
  renderLogo(wizardLogoPreview, { ...appSettings, logoData: pendingLogoData });
}

function saveWizardSettings() {
  const formData = new FormData(wizardForm);
  appSettings = {
    ...appSettings,
    hotelName: cleanText(formData.get("hotelName")) || defaultSettings.hotelName,
    domain: cleanText(formData.get("domain")) || defaultSettings.domain,
    logoText: cleanText(formData.get("logoText")) || defaultSettings.logoText,
    logoData: pendingLogoData,
    adminName: cleanText(formData.get("adminName")),
    adminEmail: cleanText(formData.get("adminEmail")),
    telegramBot: cleanText(formData.get("telegramBot")),
    whatsappProvider: formData.get("whatsappProvider"),
    autoBackup: formData.has("autoBackup"),
    backupFrequency: formData.get("backupFrequency"),
    retentionDays: Number(formData.get("retentionDays")) || defaultSettings.retentionDays,
    backupTarget: formData.get("backupTarget")
  };

  saveSettings();
  window.localStorage.setItem(storageKeys.installed, "1");
  createBackup("Kurulum yedeği");
  closeWizard();
  addFeed(`${appSettings.hotelName} kurulum ayarları kaydedildi.`);
}

function handleLogoUpload() {
  const file = logoFileInput.files && logoFileInput.files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.addEventListener("load", () => {
    pendingLogoData = String(reader.result || "");
    renderLogo(wizardLogoPreview, { ...appSettings, logoData: pendingLogoData });
  });
  reader.readAsDataURL(file);
}

async function restoreFromSelectedFile(input) {
  const file = input.files && input.files[0];
  if (!file) {
    setRestoreStatus("Önce bir yedek dosyası seçmelisin.");
    return;
  }

  try {
    const payload = JSON.parse(await file.text());
    const confirmed = window.confirm("Bu işlem mevcut demo ayarlarını ve panel verilerini seçilen yedekle değiştirecek. Devam edilsin mi?");
    if (!confirmed) return;

    restoreBackupPayload(payload);
    window.localStorage.setItem(storageKeys.installed, "1");
    setRestoreStatus("Yedek başarıyla geri yüklendi.");
  } catch (error) {
    setRestoreStatus("Yedek dosyası okunamadı. JSON formatını kontrol et.");
  }
}

function setRestoreStatus(message) {
  restoreStatus.textContent = message;
  restoreStatus.classList.toggle("visible", Boolean(message));
}

function createBackup(reason = "Manuel yedek") {
  const backup = {
    version: "hotel-security-demo-1",
    createdAt: new Date().toISOString(),
    reason,
    settings: { ...appSettings },
    visitors: visitors.map(serializeVisitor),
    feed: feed.map((item) => ({ ...item, at: new Date(item.at).toISOString() })),
    visitorProfiles: [...visitorProfiles.values()],
    counters: { nextId, todayCount, notificationCount }
  };

  backupHistory = pruneBackups([backup, ...backupHistory]);
  saveBackups();
  addFeed(`${reason} oluşturuldu.`, false);
  renderBackupPanel();
  renderSettings();
  return backup;
}

function downloadLatestBackup() {
  const backup = backupHistory[0] || createBackup("Manuel yedek");
  const blob = new Blob([JSON.stringify(backup, null, 2)], { type: "application/json" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = `otel-guvenlik-yedek-${backup.createdAt.slice(0, 16).replace(/[:T]/g, "-")}.json`;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(link.href);
}

function restoreBackupPayload(payload) {
  appSettings = { ...defaultSettings, ...(payload.settings || {}) };
  visitors = Array.isArray(payload.visitors)
    ? payload.visitors.map((visitor) => ({ ...visitor, entryAt: new Date(visitor.entryAt), isNew: false }))
    : [];
  feed = Array.isArray(payload.feed)
    ? payload.feed.map((item) => ({ ...item, at: new Date(item.at) }))
    : [];
  nextId = payload.counters?.nextId || nextId;
  todayCount = payload.counters?.todayCount || todayCount;
  notificationCount = payload.counters?.notificationCount || notificationCount;

  visitorProfiles.clear();
  const restoredProfiles = Array.isArray(payload.visitorProfiles) ? payload.visitorProfiles : [];
  [...restoredProfiles, ...visitors, ...archivedVisitors].forEach(upsertVisitorProfile);

  saveSettings();
  addFeed("Sistem yedekten geri yüklendi.");
  renderKnownVisitorsList();
  render();
}

function serializeVisitor(visitor) {
  return {
    ...visitor,
    entryAt: new Date(visitor.entryAt).toISOString(),
    isNew: false
  };
}

function pruneBackups(backups) {
  const retentionMs = Math.max(1, Number(appSettings.retentionDays) || 30) * 24 * 60 * 60 * 1000;
  const cutoff = Date.now() - retentionMs;
  return backups
    .filter((backup, index) => index === 0 || new Date(backup.createdAt).getTime() >= cutoff)
    .slice(0, 12);
}

function loadSettings() {
  const saved = readJson(storageKeys.settings);
  return { ...defaultSettings, ...(saved || {}) };
}

function saveSettings() {
  writeJson(storageKeys.settings, appSettings);
}

function loadBackups() {
  const saved = readJson(storageKeys.backups);
  return Array.isArray(saved) ? saved : [];
}

function saveBackups() {
  writeJson(storageKeys.backups, backupHistory);
}

function readJson(key) {
  try {
    const raw = window.localStorage.getItem(key);
    return raw ? JSON.parse(raw) : null;
  } catch (error) {
    return null;
  }
}

function writeJson(key, value) {
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
  } catch (error) {
    console.warn("Local storage yazılamadı", error);
  }
}

function isInstalled() {
  return window.localStorage.getItem(storageKeys.installed) === "1";
}

function frequencyLabel(value) {
  const labels = {
    hourly: "Saatlik",
    daily: "Günlük 23:30",
    weekly: "Haftalık"
  };
  return labels[value] || "Günlük 23:30";
}

function handleKnownVisitorLookup() {
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

  if (lastAutofilledName === normalizedName) return;

  fillVisitorProfile(profile);
  lastAutofilledName = normalizedName;
  setAutofillStatus(`${profile.name} kaydı bulundu, bilgiler dolduruldu.`);
}

function fillVisitorProfile(profile) {
  const categoryInput = entryForm.querySelector(`input[name="category"][value="${profile.category}"]`);
  if (categoryInput) categoryInput.checked = true;

  setEntryValue("phone", profile.phone);
  setEntryValue("plate", profile.plate);
  setEntryValue("company", profile.company);
  setEntryValue("department", profile.department);

  const noteInput = entryForm.elements.note;
  if (noteInput && !noteInput.value.trim() && profile.note) {
    noteInput.value = profile.note;
  }
}

function setEntryValue(name, value) {
  const field = entryForm.elements[name];
  if (!field || value === undefined || value === null) return;
  field.value = name === "plate" ? formatVehiclePlate(value) : value;
}

function setAutofillStatus(message) {
  autofillStatus.textContent = message;
  autofillStatus.classList.toggle("visible", Boolean(message));
}

function upsertVisitorProfile(visitor) {
  const normalizedName = normalizeName(visitor.name);
  if (!normalizedName) return;

  visitorProfiles.set(normalizedName, {
    name: cleanText(visitor.name),
    category: visitor.category || "Ziyaretçi",
    department: visitor.department || "Genel Müdürlük",
    company: visitor.company || "",
    phone: visitor.phone || "",
    plate: formatVehiclePlate(visitor.plate || ""),
    note: visitor.note || ""
  });

  renderKnownVisitorsList();
}

function renderKnownVisitorsList() {
  if (!knownVisitorsList) return;

  const options = [...visitorProfiles.values()]
    .sort((first, second) => first.name.localeCompare(second.name, "tr-TR"))
    .map((profile) => {
      const option = document.createElement("option");
      option.value = profile.name;
      option.label = [profile.company, profile.department].filter(Boolean).join(" | ");
      return option;
    });

  knownVisitorsList.replaceChildren(...options);
}

function getVisitorState(visitor) {
  const config = categorySettings[visitor.category];
  const elapsedSecondsValue = elapsedSeconds(visitor.entryAt);
  const elapsed = Math.floor(elapsedSecondsValue / 60);
  const elapsedText = formatDuration(elapsedSecondsValue);

  if (!config.limit) {
    return {
      level: "ok",
      chip: "ok",
      text: "VIP takip",
      durationText: elapsedText,
      progress: 18
    };
  }

  const totalLimit = config.limit + (visitor.extensionMinutes || 0);
  const totalLimitSeconds = totalLimit * 60;
  const progress = Math.min(100, Math.round((elapsedSecondsValue / totalLimitSeconds) * 100));
  const remainingSeconds = Math.max(0, totalLimitSeconds - elapsedSecondsValue);
  const activeDurationText = `${formatDuration(remainingSeconds)} kaldı`;
  const elapsedDurationText = `${elapsedText} / ${totalLimit} dk`;

  if (visitor.escalated) {
    return {
      level: "escalated",
      chip: "risk",
      text: "Yöneticiye gitti",
      durationText: elapsedDurationText,
      progress
    };
  }

  if (visitor.departmentApproved) {
    return {
      level: "ok",
      chip: "ok",
      text: "Amir onayladı",
      durationText: activeDurationText,
      progress
    };
  }

  if (elapsed >= totalLimit) {
    return {
      level: "overdue",
      chip: "waiting",
      text: "Amir sorusu",
      durationText: `Süre doldu · ${elapsedDurationText}`,
      progress: 100
    };
  }

  if (progress >= 80) {
    return {
      level: "warning",
      chip: "waiting",
      text: "Süre yaklaşıyor",
      durationText: activeDurationText,
      progress
    };
  }

  return {
    level: "ok",
    chip: "ok",
    text: "Normal",
    durationText: activeDurationText,
    progress
  };
}

function addFeed(text, rerender = true) {
  feed = [{ at: new Date(), text }, ...feed].slice(0, 20);
  if (rerender) render();
}

function updateClock() {
  clock.textContent = new Intl.DateTimeFormat("tr-TR", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit"
  }).format(new Date());
}

function formatTime(date) {
  return new Intl.DateTimeFormat("tr-TR", {
    hour: "2-digit",
    minute: "2-digit"
  }).format(date);
}

function formatDateTime(date) {
  return new Intl.DateTimeFormat("tr-TR", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit"
  }).format(new Date(date));
}

function elapsedMinutes(date) {
  return Math.floor(elapsedSeconds(date) / 60);
}

function elapsedSeconds(date) {
  return Math.max(0, Math.floor((Date.now() - new Date(date).getTime()) / 1000));
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

function minutesAgo(minutes) {
  return new Date(Date.now() - minutes * 60000);
}

function cleanText(value) {
  return String(value || "").trim();
}

function normalizeName(value) {
  return cleanText(value).replace(/\s+/g, " ").toLocaleLowerCase("tr-TR");
}

[...sampleVisitors, ...archivedVisitors].forEach(upsertVisitorProfile);
renderKnownVisitorsList();
backupHistory = pruneBackups(backupHistory);
saveBackups();
render();
if (!isInstalled()) openWizard("new");
window.setInterval(render, 1000);
window.setInterval(() => {
  if (isInstalled() && appSettings.autoBackup) createBackup("Otomatik yedek");
}, 45000);
