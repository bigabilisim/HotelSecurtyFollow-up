(() => {
  const modal = document.querySelector("[data-grapesjs-modal]");

  if (!modal) {
    return;
  }

  const root = modal.querySelector("[data-grapesjs-root]");
  const title = modal.querySelector("[data-grapesjs-title]");
  const closeButtons = modal.querySelectorAll("[data-grapesjs-close]");
  const applyButton = modal.querySelector("[data-grapesjs-apply]");
  const syncButton = modal.querySelector("[data-grapesjs-sync]");
  const notice = modal.querySelector("[data-grapesjs-notice]");
  let editor = null;
  let activeSource = null;
  let activeCss = null;
  let activeButton = null;

  function showNotice(message) {
    if (!notice) {
      return;
    }

    notice.textContent = message;
    notice.hidden = message === "";
  }

  function hasEditor() {
    return typeof window.grapesjs !== "undefined" && root;
  }

  function initEditor() {
    if (editor || !hasEditor()) {
      return editor;
    }

    editor = window.grapesjs.init({
      container: root,
      height: "68vh",
      width: "auto",
      storageManager: false,
      fromElement: false,
      blockManager: {
        appendTo: modal.querySelector("[data-grapesjs-blocks]"),
        blocks: [
          {
            id: "text",
            label: "Metin",
            category: "Temel",
            content: "<p>Metninizi buraya yazın.</p>"
          },
          {
            id: "heading",
            label: "Başlık",
            category: "Temel",
            content: "<h2>Başlık</h2>"
          },
          {
            id: "box",
            label: "Bilgi Kutusu",
            category: "Temel",
            content: "<div style=\"padding:16px;border:1px solid #d9ded5;border-radius:8px;background:#ffffff\"><h3>Bilgi</h3><p>Açıklama yazısı.</p></div>"
          },
          {
            id: "two-columns",
            label: "2 Sütun",
            category: "Yerleşim",
            content: "<div style=\"display:grid;grid-template-columns:1fr 1fr;gap:12px\"><div style=\"padding:14px;background:#ffffff;border:1px solid #d9ded5;border-radius:8px\">Sol alan</div><div style=\"padding:14px;background:#ffffff;border:1px solid #d9ded5;border-radius:8px\">Sağ alan</div></div>"
          },
          {
            id: "button",
            label: "Buton",
            category: "Temel",
            content: "<a href=\"#\" style=\"display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:11px 18px;border-radius:7px;font-weight:700\">Buton</a>"
          },
          {
            id: "report-table",
            label: "Rapor Tablosu",
            category: "Rapor",
            content: "<table style=\"border-collapse:collapse;width:100%\"><thead><tr><th style=\"border-bottom:1px solid #d9ded5;padding:8px;text-align:left\">Ad</th><th style=\"border-bottom:1px solid #d9ded5;padding:8px;text-align:left\">Adet</th></tr></thead><tbody>{category_rows}</tbody></table>"
          }
        ]
      },
      selectorManager: { appendTo: modal.querySelector("[data-grapesjs-selectors]") },
      styleManager: { appendTo: modal.querySelector("[data-grapesjs-styles]") },
      traitManager: { appendTo: modal.querySelector("[data-grapesjs-traits]") },
      layerManager: { appendTo: modal.querySelector("[data-grapesjs-layers]") },
      panels: { defaults: [] }
    });

    return editor;
  }

  function syncFields() {
    if (!editor || !activeSource) {
      return;
    }

    activeSource.value = editor.getHtml();
    if (activeCss) {
      activeCss.value = editor.getCss();
    }

    activeSource.dispatchEvent(new Event("input", { bubbles: true }));
    if (activeCss) {
      activeCss.dispatchEvent(new Event("input", { bubbles: true }));
    }
  }

  function openEditor(button) {
    activeButton = button;
    activeSource = document.getElementById(button.dataset.grapesjsSource || "");
    activeCss = document.getElementById(button.dataset.grapesjsCss || "");

    if (!activeSource) {
      return;
    }

    modal.hidden = false;
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("visual-editor-open");

    if (title) {
      title.textContent = button.dataset.grapesjsTitle || "Görsel Editör";
    }

    const instance = initEditor();
    if (!instance) {
      showNotice("GrapesJS yüklenemedi. İnternet veya dosya izinlerini kontrol edin; metin alanından düzenlemeye devam edebilirsiniz.");
      return;
    }

    showNotice("");
    instance.setComponents(activeSource.value || button.dataset.grapesjsDefault || "<p></p>");
    instance.setStyle(activeCss ? activeCss.value : "");

    setTimeout(() => instance.refresh(), 80);
  }

  function closeEditor(applyChanges = false) {
    if (applyChanges) {
      syncFields();
    }

    modal.hidden = true;
    modal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("visual-editor-open");
    activeSource = null;
    activeCss = null;
    activeButton = null;
  }

  function insertToken(token) {
    if (!editor || modal.hidden || !token) {
      return false;
    }

    const selected = editor.getSelected();
    if (selected) {
      selected.append(token);
    } else {
      editor.addComponents({ type: "text", content: token });
    }

    syncFields();
    return true;
  }

  document.querySelectorAll("[data-grapesjs-open]").forEach((button) => {
    button.addEventListener("click", () => openEditor(button));
  });

  closeButtons.forEach((button) => {
    button.addEventListener("click", () => closeEditor(false));
  });

  if (applyButton) {
    applyButton.addEventListener("click", () => closeEditor(true));
  }

  if (syncButton) {
    syncButton.addEventListener("click", () => {
      syncFields();
      showNotice("Tasarım metin alanına aktarıldı. Kaydetmeyi unutmayın.");
    });
  }

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeEditor(false);
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !modal.hidden) {
      closeEditor(false);
    }
  });

  window.hotelTemplateVisualEditors = {
    insertToken,
    currentButton: () => activeButton
  };
})();
