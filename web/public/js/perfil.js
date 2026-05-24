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
    await loadMfaStatus();
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

  setupMfaButtons();

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

async function loadMfaStatus() {
  const statusText = document.getElementById("mfa-status");
  const badge = document.getElementById("mfa-status-badge");
  const setupBox = document.getElementById("mfa-setup-result");
  const setupBtn = document.getElementById("mfa-setup-btn");

  if (!statusText || !badge) return;

  try {
    const result = await apiRequest("/auth/mfa/status");
    const enabled = result?.data?.enabled === true;

    if (enabled) {
      statusText.textContent = "MFA habilitado e ativo para esta conta.";
      badge.textContent = "Habilitado";
      badge.classList.remove("badge-danger");
      badge.classList.add("badge-success");

      if (setupBox) {
        setupBox.classList.add("hidden");
        setupBox.innerHTML = "";
      }

      if (setupBtn) {
        setupBtn.textContent = "Reconfigurar MFA";
      }
    } else {
      statusText.textContent = "MFA desabilitado.";
      badge.textContent = "Desabilitado";
      badge.classList.remove("badge-success");
      badge.classList.add("badge-danger");

      if (setupBtn) {
        setupBtn.textContent = "Iniciar configuração MFA";
      }
    }
  } catch (error) {
    statusText.textContent = "Falha ao carregar status MFA.";
    badge.textContent = "Erro";
    badge.classList.remove("badge-success");
    badge.classList.add("badge-danger");
  }
}

function setupMfaButtons() {
  const setupBtn = document.getElementById("mfa-setup-btn");
  const enableBtn = document.getElementById("mfa-enable-btn");
  const disableBtn = document.getElementById("mfa-disable-btn");

  if (setupBtn) {
    setupBtn.addEventListener("click", async () => {
      clearMessage("mfa-message");

      try {
        const result = await apiRequest("/auth/mfa/setup", { method: "POST" });
        const data = result?.data || {};
        const target = document.getElementById("mfa-setup-result");

        if (target) {
          target.classList.remove("hidden");
          target.innerHTML = `
            <div>
              <p><strong>Secret</strong></p>
              <code>${escapeHtml(data.secret || "")}</code>
            </div>
            <div>
              <p><strong>otpauth URL</strong></p>
              <code>${escapeHtml(data.otpauth_url || "")}</code>
            </div>
            <p class="muted">Adicione esse secret em um aplicativo autenticador e informe o código gerado abaixo.</p>
          `;
        }

        renderMessage("mfa-message", "success", "Configuração MFA iniciada.");
        await loadMfaStatus();
      } catch (error) {
        renderMessage("mfa-message", "error", error.message || "Falha ao iniciar MFA.");
      }
    });
  }

  if (enableBtn) {
    enableBtn.addEventListener("click", async () => {
      clearMessage("mfa-message");
      const code = document.getElementById("mfa-code").value.trim();

      try {
        await apiRequest("/auth/mfa/enable", {
          method: "POST",
          body: JSON.stringify({ code }),
        });

        renderMessage("mfa-message", "success", "MFA habilitado com sucesso.");

        const setupBox = document.getElementById("mfa-setup-result");
        if (setupBox) {
          setupBox.classList.add("hidden");
          setupBox.innerHTML = "";
        }

        await loadMfaStatus();
      } catch (error) {
        renderMessage("mfa-message", "error", error.message || "Falha ao habilitar MFA.");
      }
    });
  }

  if (disableBtn) {
    disableBtn.addEventListener("click", async () => {
      clearMessage("mfa-message");
      const code = document.getElementById("mfa-code").value.trim();

      try {
        await apiRequest("/auth/mfa/disable", {
          method: "POST",
          body: JSON.stringify({ code }),
        });

        renderMessage("mfa-message", "success", "MFA desabilitado com sucesso.");
        await loadMfaStatus();
      } catch (error) {
        renderMessage("mfa-message", "error", error.message || "Falha ao desabilitar MFA.");
      }
    });
  }
}