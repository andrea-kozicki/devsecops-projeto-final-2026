document.addEventListener("DOMContentLoaded", async () => {
  requireAuthOnPage();
  setupLogoutButtons();
  renderAdminLinks();

  const form = document.getElementById("profile-form");
  if (!form) return;

  const cachedUser = getStoredUser();
  if (cachedUser) {
    fillProfileForm(cachedUser);
    renderProfileSummary(cachedUser);
  }

  try {
    await loadProfile();
  } catch (error) {
    if (error.status === 401) {
      clearStoredToken();
      clearStoredUser();
      redirectToLogin();
      return;
    }

    renderMessage("profile-message", "error", error.message || "Falha ao carregar perfil.");
    if (!cachedUser) {
      renderProfileSummary(null);
    }
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage("profile-message");

    const payload = {
      name: document.getElementById("profile-name").value.trim(),
      email: document.getElementById("profile-email").value.trim(),
    };

    const password = document.getElementById("profile-password").value;
    if (password.trim() !== "") {
      payload.password = password;
    }

    try {
      const result = await apiRequest("/auth/me", {
        method: "PUT",
        body: JSON.stringify(payload),
      });

      if (result?.data) {
        setStoredUser(result.data);
        fillProfileForm(result.data);
        renderProfileSummary(result.data);
      }

      document.getElementById("profile-password").value = "";
      renderMessage("profile-message", "success", "Perfil atualizado com sucesso.");
    } catch (error) {
      if (error.status === 401) {
        clearStoredToken();
        clearStoredUser();
        redirectToLogin();
        return;
      }

      const details = error?.data?.errors
        ? Object.values(error.data.errors).join(" ")
        : error.message;

      renderMessage("profile-message", "error", details || "Falha ao atualizar perfil.");
    }
  });
});

async function loadProfile() {
  const result = await apiRequest("/auth/me");
  const user = result?.data || null;

  if (!user) {
    throw new Error("Perfil não encontrado.");
  }

  setStoredUser(user);
  fillProfileForm(user);
  renderProfileSummary(user);
}

function fillProfileForm(user) {
  document.getElementById("profile-name").value = user.name || "";
  document.getElementById("profile-email").value = user.email || "";
}

function renderProfileSummary(user) {
  const summary = document.getElementById("profile-summary");
  if (!summary) return;

  if (!user) {
    summary.innerHTML = `<p class="muted">Não foi possível carregar os dados do perfil.</p>`;
    return;
  }

  summary.innerHTML = `
    <p><strong>Nome:</strong> ${escapeHtml(user.name || "—")}</p>
    <p><strong>E-mail:</strong> ${escapeHtml(user.email || "—")}</p>
    <p><strong>Perfil:</strong> ${escapeHtml(user.role || "user")}</p>
    <p><strong>Ativo:</strong> ${user.is_active ? "Sim" : "Não"}</p>
    <p><strong>Criado em:</strong> ${escapeHtml(user.created_at || "—")}</p>
    <p><strong>Atualizado em:</strong> ${escapeHtml(user.updated_at || "—")}</p>
  `;
}