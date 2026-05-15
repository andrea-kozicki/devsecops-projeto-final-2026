document.addEventListener("DOMContentLoaded", () => {
  setupLoginForm();
  setupRegisterForm();
  setupLogoutButtons();
  setupHomeUserInfo();
  renderAdminLinks();
});

function setupLoginForm() {
  const form = document.getElementById("login-form");
  if (!form) return;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage("login-message");

    const email = document.getElementById("login-email").value.trim();
    const password = document.getElementById("login-password").value;

    try {
      const result = await apiRequest("/auth/login", {
        method: "POST",
        body: JSON.stringify({ email, password }),
      });

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

function renderAdminLinks() {
  const target = document.getElementById("admin-links");
  if (!target) return;

  const user = getStoredUser();

  if (!user || user.role !== "admin") {
    target.innerHTML = "";
    return;
  }

  target.innerHTML = `
    <a class="button secondary" href="/usuarios">Usuários</a>
    <a class="button secondary" href="/auditoria">Auditoria</a>
  `;
}