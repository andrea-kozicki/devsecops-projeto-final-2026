document.addEventListener("DOMContentLoaded", () => {
  const createForm = document.getElementById("task-form");
  const editForm = document.getElementById("edit-task-form");
  const refreshButton = document.getElementById("refresh-tasks");
  const clearFiltersButton = document.getElementById("clear-filters");
  const filterStatus = document.getElementById("filter-status");
  const filterPriority = document.getElementById("filter-priority");
  const cancelEditButton = document.getElementById("cancel-edit-task");

  requireAuthOnPage();
  setupLogoutButtons();
  renderAdminLinks();
  loadTasks();

  if (createForm) {
    createForm.addEventListener("submit", async (event) => {
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
        createForm.reset();
        await loadTasks();
      } catch (error) {
        const details = error?.data?.errors
          ? Object.values(error.data.errors).join(" ")
          : error.message;

        renderMessage("task-message", "error", details || "Falha ao criar tarefa.");
      }
    });
  }

  if (editForm) {
    editForm.addEventListener("submit", async (event) => {
      event.preventDefault();
      clearMessage("edit-task-message");

      const id = document.getElementById("edit-task-id").value;
      const title = document.getElementById("edit-task-title").value.trim();
      const description = document.getElementById("edit-task-description").value.trim();
      const priority = document.getElementById("edit-task-priority").value;
      const status = document.getElementById("edit-task-status").value;
      const due_date = document.getElementById("edit-task-due-date").value;

      try {
        await apiRequest(`/tasks/${id}`, {
          method: "PUT",
          body: JSON.stringify({
            title,
            description,
            priority,
            status,
            due_date,
          }),
        });

        closeEditTask();
        renderMessage("task-list-message", "success", "Tarefa atualizada com sucesso.");
        await loadTasks();
      } catch (error) {
        const details = error?.data?.errors
          ? Object.values(error.data.errors).join(" ")
          : error.message;

        renderMessage("edit-task-message", "error", details || "Falha ao atualizar tarefa.");
      }
    });
  }

  if (refreshButton) {
    refreshButton.addEventListener("click", () => loadTasks());
  }

  if (clearFiltersButton) {
    clearFiltersButton.addEventListener("click", async () => {
      document.getElementById("filter-status").value = "";
      document.getElementById("filter-priority").value = "";
      document.getElementById("filter-due-date").value = "";
      await loadTasks();
    });
  }

  if (filterStatus) {
    filterStatus.addEventListener("change", () => loadTasks());
  }

  if (filterPriority) {
    filterPriority.addEventListener("change", () => loadTasks());
  }

  if (cancelEditButton) {
    cancelEditButton.addEventListener("click", () => closeEditTask());
  }
});

async function loadTasks() {
  const list = document.getElementById("task-list");
  if (!list) return;

  clearMessage("task-list-message");
  clearMessage("edit-task-message");
  list.innerHTML = `<p class="muted">Carregando tarefas...</p>`;

  const params = new URLSearchParams();
  const status = document.getElementById("filter-status")?.value || "";
  const priority = document.getElementById("filter-priority")?.value || "";
  const dueDate = document.getElementById("filter-due-date")?.value || "";

  if (status) params.set("status", status);
  if (priority) params.set("priority", priority);
  if (dueDate) params.set("due_date", dueDate);

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
          closeEditTaskIfMatches(id);
          renderMessage("task-list-message", "success", "Tarefa excluída com sucesso.");
          await loadTasks();
        } catch (error) {
          renderMessage("task-list-message", "error", error.message || "Falha ao excluir tarefa.");
        }
      });
    });

    list.querySelectorAll("[data-advance-task]").forEach((button) => {
      button.addEventListener("click", async () => {
        const id = button.dataset.advanceTask;
        const currentStatus = button.dataset.currentStatus;
        const nextStatus = getNextStatus(currentStatus);
        const activeStatusFilter = document.getElementById("filter-status")?.value || "";

        if (!nextStatus) {
          renderMessage(
            "task-list-message",
            "error",
            "A tarefa já está concluída. Use Editar para reabrir ou alterar manualmente."
          );
          return;
        }

        try {
          await apiRequest(`/tasks/${id}`, {
            method: "PUT",
            body: JSON.stringify({ status: nextStatus }),
          });

          await loadTasks();

          if (activeStatusFilter && activeStatusFilter !== nextStatus) {
            renderMessage(
              "task-list-message",
              "success",
              `Status alterado para ${formatStatus(nextStatus)}. A tarefa pode ter saído da lista por causa do filtro atual.`
            );
          } else {
            renderMessage(
              "task-list-message",
              "success",
              `Status alterado para ${formatStatus(nextStatus)}.`
            );
          }
        } catch (error) {
          renderMessage("task-list-message", "error", error.message || "Falha ao atualizar tarefa.");
        }
      });
    });

    list.querySelectorAll("[data-edit-task]").forEach((button) => {
      button.addEventListener("click", () => {
        openEditTask({
          id: button.dataset.id,
          title: button.dataset.title,
          description: button.dataset.description,
          priority: button.dataset.priority,
          status: button.dataset.status,
          due_date: button.dataset.dueDate,
        });
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
  const nextStatus = getNextStatus(task.status);

  return `
    <article class="task-item">
      <h3>${escapeHtml(task.title)}</h3>
      <p>${escapeHtml(task.description || "Sem descrição.")}</p>

      <div class="task-meta">
        <span class="badge ${priorityClass}">Prioridade: ${escapeHtml(formatPriority(task.priority))}</span>
        <span class="badge ${statusClass}">Status: ${escapeHtml(formatStatus(task.status))}</span>
        <span>Prazo: ${escapeHtml(task.due_date || "—")}</span>
      </div>

      <div class="task-actions">
        <button
          class="secondary"
          data-edit-task="1"
          data-id="${escapeHtml(task.id)}"
          data-title="${escapeHtml(task.title)}"
          data-description="${escapeHtml(task.description || "")}"
          data-priority="${escapeHtml(task.priority)}"
          data-status="${escapeHtml(task.status)}"
          data-due-date="${escapeHtml(task.due_date || "")}"
        >
          Editar
        </button>

        ${
          nextStatus
            ? `<button
                class="secondary"
                data-advance-task="${escapeHtml(task.id)}"
                data-current-status="${escapeHtml(task.status)}"
              >
                Avançar status
              </button>`
            : `<button class="secondary" disabled>Status final</button>`
        }

        <button class="danger" data-delete-task="${escapeHtml(task.id)}">Excluir</button>
      </div>
    </article>
  `;
}

function openEditTask(task) {
  const card = document.getElementById("edit-task-card");
  if (!card) return;

  clearMessage("edit-task-message");

  document.getElementById("edit-task-id").value = task.id || "";
  document.getElementById("edit-task-title").value = task.title || "";
  document.getElementById("edit-task-description").value = task.description || "";
  document.getElementById("edit-task-priority").value = task.priority || "media";
  document.getElementById("edit-task-status").value = task.status || "pendente";
  document.getElementById("edit-task-due-date").value = task.due_date || "";

  card.classList.remove("hidden");
  card.scrollIntoView({ behavior: "smooth", block: "start" });
}

function closeEditTask() {
  const form = document.getElementById("edit-task-form");
  const card = document.getElementById("edit-task-card");

  clearMessage("edit-task-message");

  if (form) form.reset();
  if (card) card.classList.add("hidden");

  const hiddenId = document.getElementById("edit-task-id");
  if (hiddenId) hiddenId.value = "";
}

function closeEditTaskIfMatches(taskId) {
  const currentId = document.getElementById("edit-task-id")?.value || "";
  if (String(currentId) === String(taskId)) {
    closeEditTask();
  }
}

function formatStatus(status) {
  if (status === "pendente") return "Pendente";
  if (status === "em_andamento") return "Em andamento";
  if (status === "concluida") return "Concluída";
  return status;
}

function formatPriority(priority) {
  if (priority === "baixa") return "Baixa";
  if (priority === "media") return "Média";
  if (priority === "alta") return "Alta";
  return priority;
}

function getNextStatus(currentStatus) {
  if (currentStatus === "pendente") return "em_andamento";
  if (currentStatus === "em_andamento") return "concluida";
  return null;
}