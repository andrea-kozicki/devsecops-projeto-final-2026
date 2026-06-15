document.addEventListener("DOMContentLoaded", async () => {
  setupLogoutButtons();
  renderAdminLinks();

  if (!requireAdminOnPage("audit-message")) {
    return;
  }

  setupAuditFilters();
  await loadAuditUsers();
  await loadAuditLogs();
});

function setupAuditFilters() {
  const form = document.getElementById("audit-filter-form");
  const clearButton = document.getElementById("audit-filter-clear");

  if (form) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      await loadAuditLogs();
    });
  }

  if (clearButton && form) {
    clearButton.addEventListener("click", async () => {
      form.reset();
      await loadAuditLogs();
    });
  }
}

async function loadAuditUsers() {
  const select = document.getElementById("audit-user-filter");
  if (!select) return;

  try {
    const result = await apiRequest("/admin/users");
    const users = result?.data || [];

    users.forEach((user) => {
      const option = document.createElement("option");
      option.value = String(user.id);
      option.textContent = `${user.name || "Usuário"} (${user.email || "sem e-mail"})`;
      select.appendChild(option);
    });
  } catch {
    // A auditoria continua funcionando mesmo que a lista de usuários não carregue.
  }
}

function buildAuditPath() {
  const params = new URLSearchParams();
  const userId = document.getElementById("audit-user-filter")?.value || "";
  const dateFrom = document.getElementById("audit-date-from")?.value || "";
  const dateTo = document.getElementById("audit-date-to")?.value || "";

  params.set("limit", "100");

  if (userId) {
    params.set("user_id", userId);
  }

  if (dateFrom) {
    params.set("date_from", dateFrom);
  }

  if (dateTo) {
    params.set("date_to", dateTo);
  }

  return `/admin/audit?${params.toString()}`;
}

async function loadAuditLogs() {
  const list = document.getElementById("audit-list");
  if (!list) return;

  clearMessage("audit-message");

  try {
    const result = await apiRequest(buildAuditPath());
    const logs = result?.data || [];

    list.replaceChildren();

    if (!Array.isArray(logs) || logs.length === 0) {
      const empty = document.createElement("p");
      empty.textContent = "Nenhum evento de auditoria encontrado para os filtros informados.";
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
  actor.textContent = `Ator: ${formatUserLabel(log.actor_name, log.actor_email)} | Alvo: ${formatUserLabel(log.target_name, log.target_email)}`;

  const info = document.createElement("p");
  info.textContent = `IP: ${log.ip_address || "—"} | Quando: ${log.created_at || "—"}`;

  const details = document.createElement("pre");
  details.textContent = formatAuditDetails(log.details);

  card.append(title, entity, actor, info, details);
  return card;
}

function formatUserLabel(name, email) {
  if (!name && !email) {
    return "Sistema";
  }

  if (name && email) {
    return `${name} <${email}>`;
  }

  return name || email || "—";
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
