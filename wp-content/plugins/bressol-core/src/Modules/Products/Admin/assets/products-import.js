(() => {
  const form = document.getElementById('bressol-products-import-form');
  const progress = document.getElementById('bressol-products-import-progress');
  const summary = document.getElementById('bressol-products-import-summary');
  const errorsBox = document.getElementById('bressol-products-import-errors');
  const reportLink = document.getElementById('bressol-products-import-report');
  const startBtn = document.getElementById('bressol-products-import-start');

  if (!form || !progress || !summary || !errorsBox || !reportLink || !startBtn) {
    return;
  }

  const formatStats = (stats) => {
    return `Procesadas: ${stats.processed || 0} | Creadas: ${stats.created || 0} | Actualizadas: ${stats.updated || 0} | Omitidas: ${stats.skipped || 0} | Errores: ${stats.errors || 0}`;
  };

  const renderErrors = (errors) => {
    if (!errors || errors.length === 0) {
      errorsBox.innerHTML = '';
      return;
    }
    const rows = errors
      .map(
        (err) =>
          `<tr><td>${err.row}</td><td>${err.sku || ''}</td><td>${err.message}</td></tr>`
      )
      .join('');
    errorsBox.innerHTML = `
      <h3>Primeros 20 errores</h3>
      <table class="widefat striped">
        <thead><tr><th>Row</th><th>SKU</th><th>Mensaje</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    `;
  };

  const updateReportLink = (url) => {
    if (!url) {
      reportLink.style.display = 'none';
      return;
    }
    reportLink.href = url;
    reportLink.style.display = 'inline';
  };

  const runStep = async (jobId, reportUrl) => {
    const body = new FormData();
    body.append('action', 'bressol_products_import_step');
    body.append('nonce', bressolProductsImport.nonce);
    body.append('job_id', jobId);

    const res = await fetch(bressolProductsImport.ajaxUrl, {
      method: 'POST',
      body,
      credentials: 'same-origin',
    });
    const json = await res.json();
    if (!json.success) {
      progress.textContent = `Error: ${json.data && json.data.message ? json.data.message : 'falló el step'}`;
      startBtn.disabled = false;
      return;
    }

    const data = json.data;
    progress.textContent = data.done ? 'Import finalizado.' : 'Procesando...';
    summary.textContent = formatStats(data.stats || {});
    renderErrors(data.errors_preview || []);
    updateReportLink(data.report_url || reportUrl);

    if (!data.done) {
      setTimeout(() => runStep(jobId, reportUrl), 200);
    } else {
      startBtn.disabled = false;
    }
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    startBtn.disabled = true;
    progress.textContent = 'Subiendo CSV...';
    summary.textContent = '';
    errorsBox.innerHTML = '';
    reportLink.style.display = 'none';

    const body = new FormData(form);
    body.append('action', 'bressol_products_import_start');
    body.append('nonce', bressolProductsImport.nonce);

    try {
      const res = await fetch(bressolProductsImport.ajaxUrl, {
        method: 'POST',
        body,
        credentials: 'same-origin',
      });
      const json = await res.json();
      if (!json.success) {
        progress.textContent = `Error: ${json.data && json.data.message ? json.data.message : 'falló el start'}`;
        startBtn.disabled = false;
        return;
      }
      const data = json.data;
      progress.textContent = 'Procesando...';
      summary.textContent = formatStats(data.stats || {});
      updateReportLink(data.report_url || '');
      runStep(data.job_id, data.report_url || '');
    } catch (err) {
      progress.textContent = 'Error inesperado en la subida.';
      startBtn.disabled = false;
    }
  });
})();
