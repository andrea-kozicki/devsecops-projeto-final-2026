const API_BASE = "/api";

function getStoredToken() {
  return localStorage.getItem("studyboard_token") || "";
}

function setStoredToken(token) {
  localStorage.setItem("studyboard_token", token);
}

function clearStoredToken() {
  localStorage.removeItem("studyboard_token");
}

function getStoredUser() {
  const raw = localStorage.getItem("studyboard_user");
  if (!raw) return null;

  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function setStoredUser(user) {
  localStorage.setItem("studyboard_user", JSON.stringify(user));
}

function clearStoredUser() {
  localStorage.removeItem("studyboard_user");
}

async function apiRequest(path, options = {}) {
  const token = getStoredToken();

  const headers = {
    ...(options.headers || {}),
  };

  if (!(options.body instanceof FormData)) {
    headers["Content-Type"] = headers["Content-Type"] || "application/json";
  }

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE}${path}`, {
    ...options,
    headers,
  });

  let data = null;

  try {
    data = await response.json();
  } catch {
    data = null;
  }

  if (!response.ok) {
    const error = new Error(data?.message || "Erro na requisição.");
    error.status = response.status;
    error.data = data;
    throw error;
  }

  return data;
}

function redirectToLogin() {
  window.location.href = "/login";
}

function requireAuthOnPage() {
  const token = getStoredToken();
  if (!token) {
    redirectToLogin();
  }
}

function renderMessage(containerId, type, text) {
  const el = document.getElementById(containerId);
  if (!el) return;

  el.className = `alert ${type}`;
  el.textContent = text;
  el.classList.remove("hidden");
}

function clearMessage(containerId) {
  const el = document.getElementById(containerId);
  if (!el) return;

  el.textContent = "";
  el.className = "alert hidden";
}

function escapeHtml(value) {
  const span = document.createElement("span");
  span.textContent = String(value ?? "");
  return span.innerHTML;
}