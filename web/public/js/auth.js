document.addEventListener("DOMContentLoaded", () => {
  setupLoginForm();
  setupLoginMfaForm();
  setupRegisterForm();
  setupLogoutButtons();
  setupHomeUserInfo();
  renderAdminLinks();
});

function setupLoginForm() {
  const form = document.getElementById("login-form");
  const submitButton = document.getElementById("login-submit");
  const emailInput = document.getElementById("login-email");
  const passwordInput = document.getElementById("login-password");

  if (!form || !submitButton || !emailInput || !passwordInput) return;

  async function performLogin() {
    clearMessage("login-message");

    const email = emailInput.value.trim();
    const password = passwordInput.value;

    try {
      const result = await apiRequest("/auth/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });

      const mfaRequired = result?.data?.mfa_required === true;

      if (mfaRequired) {
        const challengeToken = result?.data?.challenge_token || "";

        if (!challengeToken) {
          throw new Error("Challenge MFA ausente.");
        }

        sessionStorage.setItem("studyboard_mfa_challenge", challengeToken);
        sessionStorage.setItem("studyboard_mfa_email", email);

        showLoginMfaStep();
        renderMessage("login-message", "success", "MFA necessário. Informe o código de 6 dígitos.");
        return;
      }

      const token = result?.data?.access_token || "";
      const user = result?.data?.user || null;

      if (!token || !user) {
        throw new Error("Resposta de login incompleta.");
      }

      setStoredToken(token);
      setStoredUser(user);

      renderMessage("login-message", "success", "Login realizado com sucesso.");
      setTimeout(() => {
        window.location.href = "/tarefas";
      }, 700);
    } catch (error) {
      renderMessage("login-message", "error", error.message || "Falha no login.");
    }
  }

  submitButton.addEventListener("click", async () => {
    await performLogin();
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    await performLogin();
  });

  [emailInput, passwordInput].forEach((input) => {
    input.addEventListener("keydown", async (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        await performLogin();
      }
    });
  });
}

function setupRegisterForm() {
  const form = document.getElementById("register-form");
  if (!form) return;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage("register-message");

    const name = document.getElementById("register-name").value.trim();
    const email = document.getElementById("register-email").value.trim();
    const password = document.getElementById("register-password").value;

    try {
      await apiRequest("/auth/register", {
        method: "POST",
        body: JSON.stringify({ name, email, password }),
      });

      renderMessage("register-message", "success", "Cadastro realizado com sucesso. Agora faça login.");
      form.reset();
    } catch (error) {
      const details = error?.data?.errors
        ? Object.values(error.data.errors).join(" ")
        : error.message;

      renderMessage("register-message", "error", details || "Falha no cadastro.");
    }
  });
}

function setupLogoutButtons() {
  document.querySelectorAll("[data-logout]").forEach((button) => {
    button.addEventListener("click", async () => {
      try {
        await apiRequest("/auth/logout", { method: "POST" });
      } catch {
      } finally {
        clearStoredToken();
        clearStoredUser();
        redirectToLogin();
      }
    });
  });
}

function setupHomeUserInfo() {
  const target = document.getElementById("home-user-info");
  if (!target) return;

  const user = getStoredUser();

  if (!user) {
    target.innerHTML = `
      <p>Você ainda não está autenticada.</p>
      <div class="nav-links">
        <a class="button" href="/login">Entrar</a>
        <a class="button secondary" href="/cadastro">Cadastrar</a>
      </div>
    `;
    return;
  }

  target.innerHTML = `
    <p>Usuária autenticada: <strong>${escapeHtml(user.name)}</strong> <span class="muted">(${escapeHtml(user.email)})</span></p>
    <div class="nav-links">
      <a class="button" href="/tarefas">Ir para tarefas</a>
      <a class="button secondary" href="/perfil">Ver perfil</a>
      <button class="danger" data-logout>Logout</button>
    </div>
  `;

  setupLogoutButtons();
}

function isCurrentUserAdmin() {
  const user = getStoredUser();
  return Boolean(user && user.role === "admin");
}

function requireAdminOnPage(messageContainerId = "page-message") {
  requireAuthOnPage();

  if (!getStoredToken()) {
    return false;
  }

  renderAdminLinks();

  if (isCurrentUserAdmin()) {
    return true;
  }

  if (messageContainerId) {
    renderMessage(messageContainerId, "error", "Acesso restrito a administradores.");
  }

  setTimeout(() => {
    window.location.href = "/tarefas";
  }, 900);

  return false;
}

function renderAdminLinks() {
  const target = document.getElementById("admin-links");
  if (!target) return;

  target.replaceChildren();

  if (!isCurrentUserAdmin()) {
    return;
  }

  const usersLink = document.createElement("a");
  usersLink.className = "button secondary";
  usersLink.href = "/usuarios";
  usersLink.textContent = "Usuários";

  const auditLink = document.createElement("a");
  auditLink.className = "button secondary";
  auditLink.href = "/auditoria";
  auditLink.textContent = "Auditoria";

  target.append(usersLink, document.createTextNode(" "), auditLink);
}

function setupLoginMfaForm() {
  const form = document.getElementById("login-mfa-form");
  const cancelButton = document.getElementById("login-mfa-cancel");

  if (form) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      clearMessage("login-message");

      const challengeToken = sessionStorage.getItem("studyboard_mfa_challenge") || "";
      const code = document.getElementById("login-mfa-code").value.trim();

      if (!challengeToken) {
        renderMessage("login-message", "error", "Challenge MFA ausente. Faça login novamente.");
        hideLoginMfaStep();
        return;
      }

      try {
        const result = await apiRequest("/auth/mfa/verify-login", {
          method: "POST",
          body: JSON.stringify({
            challenge_token: challengeToken,
            code,
          }),
        });

        const token = result?.data?.access_token || "";
        const user = result?.data?.user || null;

        if (!token || !user) {
          throw new Error("Resposta MFA incompleta.");
        }

        setStoredToken(token);
        setStoredUser(user);

        sessionStorage.removeItem("studyboard_mfa_challenge");
        sessionStorage.removeItem("studyboard_mfa_email");

        renderMessage("login-message", "success", "Login MFA realizado com sucesso.");

        setTimeout(() => {
          window.location.href = "/tarefas";
        }, 700);
      } catch (error) {
        renderMessage("login-message", "error", error.message || "Falha na validação MFA.");
      }
    });
  }

  if (cancelButton) {
    cancelButton.addEventListener("click", () => {
      sessionStorage.removeItem("studyboard_mfa_challenge");
      sessionStorage.removeItem("studyboard_mfa_email");
      hideLoginMfaStep();
      clearMessage("login-message");
    });
  }
}

function showLoginMfaStep() {
  const loginForm = document.getElementById("login-form");
  const mfaForm = document.getElementById("login-mfa-form");
  const mfaCode = document.getElementById("login-mfa-code");

  if (loginForm) {
    loginForm.classList.add("hidden");
  }

  if (mfaForm) {
    mfaForm.classList.remove("hidden");
  }

  if (mfaCode) {
    mfaCode.value = "";
    mfaCode.focus();
  }
}

function hideLoginMfaStep() {
  const loginForm = document.getElementById("login-form");
  const mfaForm = document.getElementById("login-mfa-form");
  const passwordInput = document.getElementById("login-password");

  if (mfaForm) {
    mfaForm.classList.add("hidden");
  }

  if (loginForm) {
    loginForm.classList.remove("hidden");
  }

  if (passwordInput) {
    passwordInput.focus();
  }
}