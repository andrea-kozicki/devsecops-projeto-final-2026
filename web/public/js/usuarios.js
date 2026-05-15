document.addEventListener("DOMContentLoaded", async () => {
  requireAuthOnPage();
  setupLogoutButtons();
  renderAdminLinks();
  await loadUsers();
});

async function loadUsers() {
  const list = document.getElementById("users-list");
  if (!list) return;

  clearMessage("users-message");

  try {
    const result = await apiRequest("/admin/users");
    const users = result?.data || [];
    const currentUser = getStoredUser();

    if (!Array.isArray(users) || users.length === 0) {
      list.innerHTML = `<p class="muted">Nenhum usuário encontrado.</p>`;
      return;
    }

    list.innerHTML = users.map((user) => {
      const isSelf = currentUser && Number(currentUser.id) === Number(user.id);
      const actionButton = user.is_active
        ? `<button class="danger" data-block-user="${user.id}" ${isSelf ? 'disabled' : ''}>Bloquear</button>`
        : `<button class="secondary" data-reactivate-user="${user.id}">Reativar</button>`;

      return `
        <article class="task-item">
          <h3>${escapeHtml(user.name)}</h3>
          <p>${escapeHtml(user.email)}</p>
          <div class="task-meta">
            <span>Perfil: ${escapeHtml(user.role)}</span>
            <span>Ativo: ${user.is_active ? "Sim" : "Não"}</span>
            <span>Criado em: ${escapeHtml(user.created_at || "—")}</span>
          </div>
          <div class="task-actions">
            ${actionButton}
          </div>
        </article>
      `;
    }).join("");

    bindUserActions();
  } catch (error) {
    const message = error?.status === 403
      ? "Acesso restrito a administradores."
      : (error.message || "Falha ao carregar usuários.");

    renderMessage("users-message", "error", message);
  }
}

function bindUserActions() {
  document.querySelectorAll("[data-block-user]").forEach((button) => {
    button.addEventListener("click", async () => {
      const userId = button.dataset.blockUser;

      if (!confirm("Deseja realmente bloquear este usuário?")) {
        return;
      }

      try {
        await apiRequest(`/admin/users/${userId}/block`, { method: "POST" });
        renderMessage("users-message", "success", "Usuário bloqueado com sucesso.");
        await loadUsers();
      } catch (error) {
        renderMessage("users-message", "error", error.message || "Falha ao bloquear usuário.");
      }
    });
  });

  document.querySelectorAll("[data-reactivate-user]").forEach((button) => {
    button.addEventListener("click", async () => {
      const userId = button.dataset.reactivateUser;

      try {
        await apiRequest(`/admin/users/${userId}/reactivate`, { method: "POST" });
        renderMessage("users-message", "success", "Usuário reativado com sucesso.");
        await loadUsers();
      } catch (error) {
        renderMessage("users-message", "error", error.message || "Falha ao reativar usuário.");
      }
    });
  });
}