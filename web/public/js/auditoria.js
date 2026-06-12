document.addEventListener("DOMContentLoaded", async () => {
  setupLogoutButtons();
  renderAdminLinks();

  if (!requireAdminOnPage("audit-message")) {
    return;
  }

  await loadAuditLogs();
});

async function loadAuditLogs() {
  const list = document.getElementById("audit-list");
  if (!list) return;

  clearMessage("audit-message");

  try {
    const result = await apiRequest("/admin/audit");
    const logs = result?.data || [];

    list.replaceChildren();

    if (!Array.isArray(logs) || logs.length === 0) {
      const empty = document.createElement("p");
      empty.textContent = "Nenhum evento de auditoria encontrado.";
      list.appendChild(empty);
      return;
    }

    logs.forEach((log) => {
      list.appendChild(createAuditCard(log));
    });
  } catch (error) {
    const message = error?.status === 403
      ? "Acesso restrito a administradores."
      : (error.message || "Falha ao carregar auditoria.");

    renderMessage("audit-message", "error", message);
  }
}

function createAuditCard(log) {
  const card = document.createElement("article");
  card.className = "card";

  const title = document.createElement("h3");
  title.textContent = log.event_type || "Evento";

  const entity = document.createElement("p");
  entity.textContent = `Entidade: ${log.entity_type || "—"}${log.entity_id ? ` #${log.entity_id}` : ""}`;

  const actor = document.createElement("p");
  actor.textContent = `Ator: ${log.actor_name || "Sistema"} | Alvo: ${log.target_name || "—"}`;

  const info = document.createElement("p");
  info.textContent = `IP: ${log.ip_address || "—"} | Quando: ${log.created_at || "—"}`;

  const details = document.createElement("pre");
  details.textContent = formatAuditDetails(log.details);

  card.append(title, entity, actor, info, details);
  return card;
}

function formatAuditDetails(details) {
  if (!details) {
    return "Sem detalhes adicionais.";
  }

  if (typeof details === "string") {
    return details;
  }

  try {
    return JSON.stringify(details, null, 2);
  } catch {
    return "Detalhes indisponíveis.";
  }
}
