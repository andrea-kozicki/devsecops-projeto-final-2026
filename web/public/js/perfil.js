document.addEventListener("DOMContentLoaded", async () => {
  const container = document.getElementById("profile-data");
  if (!container) return;

  requireAuthOnPage();
  setupLogoutButtons();

  try {
    const result = await apiRequest("/auth/me");
    const user = result?.data || null;

    if (!user) {
      throw new Error("Perfil não encontrado.");
    }

    setStoredUser(user);

    container.innerHTML = `
      <div class="card">
        <h2>Perfil</h2>
        <p><strong>Nome:</strong> ${escapeHtml(user.name)}</p>
        <p><strong>E-mail:</strong> ${escapeHtml(user.email)}</p>
        <p><strong>Perfil:</strong> ${escapeHtml(user.role)}</p>
        <p><strong>Ativa:</strong> ${user.is_active ? "Sim" : "Não"}</p>
        <p><strong>Criado em:</strong> ${escapeHtml(user.created_at || "—")}</p>
      </div>
    `;
  } catch (error) {
    if (error.status === 401) {
      clearStoredToken();
      clearStoredUser();
      redirectToLogin();
      return;
    }

    container.innerHTML = `
      <div class="alert error">
        Falha ao carregar perfil.
      </div>
    `;
  }
});