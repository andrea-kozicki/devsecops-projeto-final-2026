document.addEventListener("DOMContentLoaded", async () => {
  setupLogoutButtons();
  renderAdminLinks();

  if (!requireAdminOnPage("users-message")) {
    return;
  }

  setupUsersFilters();
  await loadUsers();
});

function setupUsersFilters() {
  const form = document.getElementById("users-filter-form");
  const clearButton = document.getElementById("users-filter-clear");

  if (form) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      await loadUsers();
    });
  }

  if (clearButton) {
    clearButton.addEventListener("click", async () => {
      const roleFilter = document.getElementById("users-role-filter");
      if (roleFilter) {
        roleFilter.value = "";
      }
      await loadUsers();
    });
  }
}

function getSelectedUserRoleFilter() {
  const roleFilter = document.getElementById("users-role-filter");
  const value = roleFilter ? roleFilter.value : "";

  return value === "admin" || value === "user" ? value : "";
}

async function loadUsers() {
  const list = document.getElementById("users-list");
  if (!list) return;

  clearMessage("users-message");

  try {
    const roleFilter = getSelectedUserRoleFilter();
    const query = roleFilter ? `?role=${encodeURIComponent(roleFilter)}` : "";
    const result = await apiRequest(`/admin/users${query}`);
    const users = result?.data || [];
    const currentUser = getStoredUser();

    list.replaceChildren();

    if (!Array.isArray(users) || users.length === 0) {
      const empty = document.createElement("p");
      empty.textContent = "Nenhum usuário encontrado.";
      list.appendChild(empty);
      return;
    }

    users.forEach((user) => {
      list.appendChild(createUserCard(user, currentUser));
    });

    bindUserActions();
  } catch (error) {
    const message = error?.status === 403
      ? "Acesso restrito a administradores."
      : (error.message || "Falha ao carregar usuários.");

    renderMessage("users-message", "error", message);
  }
}

function createUserCard(user, currentUser) {
  const card = document.createElement("article");
  card.className = "card";

  const title = document.createElement("h3");
  title.textContent = user.name || "Usuário sem nome";

  const email = document.createElement("p");
  email.textContent = user.email || "E-mail não informado";

  const meta = document.createElement("p");
  meta.textContent = `Perfil: ${user.role || "—"} | Ativo: ${user.is_active ? "Sim" : "Não"} | Criado em: ${user.created_at || "—"}`;

  card.append(title, email, meta);

  const isSelf = currentUser && Number(currentUser.id) === Number(user.id);
  const actions = document.createElement("p");

  if (user.is_active) {
    const blockButton = document.createElement("button");
    blockButton.type = "button";
    blockButton.className = "button danger";
    blockButton.dataset.blockUser = String(user.id);
    blockButton.textContent = isSelf ? "Não é possível bloquear a si mesma" : "Bloquear";
    blockButton.disabled = Boolean(isSelf);
    actions.appendChild(blockButton);
  } else {
    const reactivateButton = document.createElement("button");
    reactivateButton.type = "button";
    reactivateButton.className = "button secondary";
    reactivateButton.dataset.reactivateUser = String(user.id);
    reactivateButton.textContent = "Reativar";
    actions.appendChild(reactivateButton);
  }

  card.appendChild(actions);
  return card;
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
