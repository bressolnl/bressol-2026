(function () {
    function qs(sel) {
      return document.querySelector(sel);
    }
  
    document.addEventListener("click", async function (e) {
      const btn = e.target.closest("#bressol-pack-save");
      if (!btn) return;
  
      e.preventDefault();
  
      const postId = btn.getAttribute("data-post-id");
      const nonce = btn.getAttribute("data-nonce");
      const textarea = qs('textarea[name="bressol_pack_definition"]');
      const status = qs("#bressol-pack-save-status");
  
      if (!postId || !nonce || !textarea) return;
  
      status.textContent = "Guardando…";
  
      const form = new FormData();
      form.append("action", "bressol_save_pack_definition");
      form.append("post_id", postId);
      form.append("nonce", nonce);
      form.append("json", textarea.value);
  
      try {
        const res = await fetch(window.ajaxurl, { method: "POST", body: form });
        const data = await res.json();
  
        if (data && data.success) {
          status.textContent = "Guardado correctamente.";
        } else {
          status.textContent = (data && data.data && data.data.message) ? data.data.message : "Error al guardar.";
        }
      } catch (err) {
        status.textContent = "Error de red al guardar.";
      }
    });
  })();