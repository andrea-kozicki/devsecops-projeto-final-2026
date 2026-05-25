function clearMfaSetupBox() {
  const setupBox = document.getElementById("mfa-setup-result");
  const qrSection = document.getElementById("mfa-qr-section");
  const secretSection = document.getElementById("mfa-secret-section");
  const urlSection = document.getElementById("mfa-url-section");
  const qrTarget = document.getElementById("mfa-qrcode");
  const secretCode = document.getElementById("mfa-secret-code");
  const otpAuthUrl = document.getElementById("mfa-otpauth-url");

  if (setupBox) {
    setupBox.classList.add("hidden");
  }

  if (qrSection) {
    qrSection.classList.add("hidden");
  }

  if (secretSection) {
    secretSection.classList.add("hidden");
  }

  if (urlSection) {
    urlSection.classList.add("hidden");
  }

  if (qrTarget) {
    qrTarget.innerHTML = "";
  }

  if (secretCode) {
    secretCode.textContent = "";
  }

  if (otpAuthUrl) {
    otpAuthUrl.textContent = "";
  }
}

function renderMfaQrCode(otpauthUrl) {
  const qrTarget = document.getElementById("mfa-qrcode");
  if (!qrTarget || !otpauthUrl) return;

  qrTarget.innerHTML = "";

  if (typeof QRCode === "undefined") {
    qrTarget.innerHTML = "<p>Biblioteca de QR code não carregada.</p>";
    return;
  }

  new QRCode(qrTarget, {
    text: otpauthUrl,
    width: 180,
    height: 180,
    correctLevel: QRCode.CorrectLevel.M,
  });
}

function fillProfileForm(user) {
  const nameInput = document.getElementById("profile-name");
  const emailInput = document.getElementById("profile-email");
  const passwordInput = document.getElementById("profile-password");

  if (nameInput) {
    nameInput.value = user?.name || "";
  }

  if (emailInput) {
    emailInput.value = user?.email || "";
  }

  if (passwordInput) {
    passwordInput.value = "";
  }
}

function renderProfileSummary(user) {
  const target = document.getElementById("profile-summary");
  if (!target) return;

  if (!user) {
    target.innerHTML = `<p class="muted">Não foi possível carregar os dados do perfil.</p>`;
    return;
  }

  target.innerHTML = `
    <p><strong>Nome:</strong> ${escapeHtml(user.name || "")}</p>
    <p><strong>E-mail:</strong> ${escapeHtml(user.email || "")}</p>
    <p><strong>Perfil:</strong> ${escapeHtml(user.role || "")}</p>
    <p><strong>Status:</strong> ${user.is_active === 1 ? "Ativo" : "Inativo"}</p>
    <p><strong>Criado em:</strong> ${escapeHtml(user.created_at || "")}</p>
    <p><strong>Atualizado em:</strong> ${escapeHtml(user.updated_at || "")}</p>
  `;
}

async function loadProfile() {
  const result = await apiRequest("/auth/me");
  const user = result?.data || null;

  if (user) {
    setStoredUser(user);
    fillProfileForm(user);
    renderProfileSummary(user);
  }

  return user;
}

async function loadMfaStatus() {
  const statusText = document.getElementById("mfa-status");
  const badge = document.getElementById("mfa-status-badge");
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

      if (setupBtn) {
        setupBtn.textContent = "Reconfigurar MFA";
      }

      clearMfaSetupBox();
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

        const setupBox = document.getElementById("mfa-setup-result");
        const qrSection = document.getElementById("mfa-qr-section");
        const secretSection = document.getElementById("mfa-secret-section");
        const urlSection = document.getElementById("mfa-url-section");
        const secretCode = document.getElementById("mfa-secret-code");
        const otpAuthUrl = document.getElementById("mfa-otpauth-url");

        if (setupBox) {
          setupBox.classList.remove("hidden");
        }

        if (qrSection) {
          qrSection.classList.remove("hidden");
        }

        if (secretSection) {
          secretSection.classList.remove("hidden");
        }

        if (urlSection) {
          urlSection.classList.remove("hidden");
        }

        if (secretCode) {
          secretCode.textContent = data.secret || "";
        }

        if (otpAuthUrl) {
          otpAuthUrl.textContent = data.otpauth_url || "";
        }

        renderMfaQrCode(data.otpauth_url || "");

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
      const code = document.getElementById("mfa-code")?.value.trim() || "";

      try {
        await apiRequest("/auth/mfa/enable", {
          method: "POST",
          body: JSON.stringify({ code }),
        });

        renderMessage("mfa-message", "success", "MFA habilitado com sucesso.");
        clearMfaSetupBox();
        await loadMfaStatus();
      } catch (error) {
        renderMessage("mfa-message", "error", error.message || "Falha ao habilitar MFA.");
      }
    });
  }

  if (disableBtn) {
    disableBtn.addEventListener("click", async () => {
      clearMessage("mfa-message");
      const code = document.getElementById("mfa-code")?.value.trim() || "";

      try {
        await apiRequest("/auth/mfa/disable", {
          method: "POST",
          body: JSON.stringify({ code }),
        });

        renderMessage("mfa-message", "success", "MFA desabilitado com sucesso.");
        clearMfaSetupBox();
        await loadMfaStatus();
      } catch (error) {
        renderMessage("mfa-message", "error", error.message || "Falha ao desabilitar MFA.");
      }
    });
  }
}

document.addEventListener("DOMContentLoaded", async () => {
  requireAuthOnPage();
  setupLogoutButtons();
  renderAdminLinks();
  setupMfaButtons();

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

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage("profile-message");

    const payload = {
      name: document.getElementById("profile-name")?.value.trim() || "",
      email: document.getElementById("profile-email")?.value.trim() || "",
    };

    const password = document.getElementById("profile-password")?.value || "";
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

      const passwordInput = document.getElementById("profile-password");
      if (passwordInput) {
        passwordInput.value = "";
      }

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