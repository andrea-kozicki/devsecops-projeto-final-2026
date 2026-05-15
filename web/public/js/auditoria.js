document.addEventListener("DOMContentLoaded", async () => {
  requireAuthOnPage();
  setupLogoutButtons();
  renderAdminLinks();

  const list = document.getElementById("audit-list");
  if (!list) return;

  try {
    const result = await apiRequest("/admin/audit");
    const logs = result?.data || [];

    if (!Array.isArray(logs) || logs.length === 0) {
      list.innerHTML = `<p class="muted">Nenhum evento de auditoria encontrado.</p>`;
      return;
    }

    list.innerHTML = logs.map((log) => `
      <article class="task-item">
        <h3>${escapeHtml(log.event_type)}</h3>
        <p>Entidade: ${escapeHtml(log.entity_type)} ${log.entity_id ? `#${escapeHtml(String(log.entity_id))}` : ""}</p>
        <div class="task-meta">
          <span>Ator: ${escapeHtml(log.actor_name || "Sistema")}</span>
          <span>Alvo: ${escapeHtml(log.target_name || "—")}</span>
          <span>IP: ${escapeHtml(log.ip_address || "—")}</span>
          <span>Quando: ${escapeHtml(log.created_at || "—")}</span>
        </div>
      </article>
    `).join("");
  } catch (error) {
    const message = error?.status === 403
      ? "Acesso restrito a administradores."
      : (error.message || "Falha ao carregar auditoria.");

    renderMessage("audit-message", "error", message);
  }
});