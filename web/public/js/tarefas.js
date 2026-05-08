document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("task-form");
  const refreshButton = document.getElementById("refresh-tasks");
  const filtersForm = document.getElementById("task-filters");

  if (!form && !refreshButton && !filtersForm) return;

  requireAuthOnPage();
  setupLogoutButtons();
  loadTasks();

  if (form) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      clearMessage("task-message");

      const title = document.getElementById("task-title").value.trim();
      const description = document.getElementById("task-description").value.trim();
      const priority = document.getElementById("task-priority").value;
      const status = document.getElementById("task-status").value;
      const due_date = document.getElementById("task-due-date").value;

      try {
        await apiRequest("/tasks", {
          method: "POST",
          body: JSON.stringify({
            title,
            description,
            priority,
            status,
            due_date,
          }),
        });

        renderMessage("task-message", "success", "Tarefa criada com sucesso.");
        form.reset();
        await loadTasks();
      } catch (error) {
        const details = error?.data?.errors
          ? Object.values(error.data.errors).join(" ")
          : error.message;

        renderMessage("task-message", "error", details || "Falha ao criar tarefa.");
      }
    });
  }

  if (refreshButton) {
    refreshButton.addEventListener("click", () => loadTasks());
  }

  if (filtersForm) {
    filtersForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      await loadTasks();
    });
  }
});

async function loadTasks() {
  const list = document.getElementById("task-list");
  if (!list) return;

  clearMessage("task-list-message");
  list.innerHTML = `<p class="muted">Carregando tarefas...</p>`;

  const params = new URLSearchParams();
  const status = document.getElementById("filter-status")?.value || "";
  const priority = document.getElementById("filter-priority")?.value || "";

  if (status) params.set("status", status);
  if (priority) params.set("priority", priority);

  const suffix = params.toString() ? `?${params.toString()}` : "";

  try {
    const result = await apiRequest(`/tasks${suffix}`);
    const tasks = result?.data || [];

    if (!Array.isArray(tasks) || tasks.length === 0) {
      list.innerHTML = `<p class="muted">Nenhuma tarefa encontrada.</p>`;
      return;
    }

    list.innerHTML = tasks.map(renderTaskCard).join("");

    list.querySelectorAll("[data-delete-task]").forEach((button) => {
      button.addEventListener("click", async () => {
        const id = button.dataset.deleteTask;

        if (!confirm("Deseja realmente excluir esta tarefa?")) {
          return;
        }

        try {
          await apiRequest(`/tasks/${id}`, { method: "DELETE" });
          await loadTasks();
        } catch (error) {
          renderMessage("task-list-message", "error", error.message || "Falha ao excluir tarefa.");
        }
      });
    });

    list.querySelectorAll("[data-toggle-task]").forEach((button) => {
      button.addEventListener("click", async () => {
        const id = button.dataset.toggleTask;
        const currentStatus = button.dataset.currentStatus;
        const nextStatus = currentStatus === "concluida" ? "pendente" : "concluida";

        try {
          await apiRequest(`/tasks/${id}`, {
            method: "PUT",
            body: JSON.stringify({ status: nextStatus }),
          });

          await loadTasks();
        } catch (error) {
          renderMessage("task-list-message", "error", error.message || "Falha ao atualizar tarefa.");
        }
      });
    });
  } catch (error) {
    if (error.status === 401) {
      clearStoredToken();
      clearStoredUser();
      redirectToLogin();
      return;
    }

    list.innerHTML = "";
    renderMessage("task-list-message", "error", error.message || "Falha ao carregar tarefas.");
  }
}

function renderTaskCard(task) {
  const priorityClass = `priority-${escapeHtml(task.priority)}`;
  const statusClass = `status-${escapeHtml(task.status)}`;

  return `
    <article class="task-item">
      <h3>${escapeHtml(task.title)}</h3>
      <p>${escapeHtml(task.description || "Sem descrição.")}</p>

      <div class="task-meta">
        <span class="badge ${priorityClass}">Prioridade: ${escapeHtml(task.priority)}</span>
        <span class="badge ${statusClass}">Status: ${escapeHtml(task.status)}</span>
        <span>Prazo: ${escapeHtml(task.due_date || "—")}</span>
      </div>

      <div class="task-actions">
        <button
          class="secondary"
          data-toggle-task="${escapeHtml(task.id)}"
          data-current-status="${escapeHtml(task.status)}"
        >
          Alternar status
        </button>
        <button class="danger" data-delete-task="${escapeHtml(task.id)}">Excluir</button>
      </div>
    </article>
  `;
}