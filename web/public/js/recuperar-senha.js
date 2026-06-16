(function () {
  'use strict';

  function $(selector) {
    return document.querySelector(selector);
  }

  function findForm() {
    return $('#forgot-password-form')
      || $('#recuperar-senha-form')
      || $('#password-recovery-form')
      || $('form');
  }

  function findEmailInput(form) {
    return $('#forgot-email')
      || $('#recovery-email')
      || $('#email')
      || form.querySelector('input[type="email"]')
      || form.querySelector('input[name="email"]');
  }

  function getMessageBox(form) {
    let box = $('#forgot-password-message')
      || $('#recuperar-senha-message')
      || $('#password-recovery-message')
      || $('#reset-request-message')
      || $('.alert');

    if (!box) {
      box = document.createElement('div');
      box.id = 'recuperar-senha-message';
      box.className = 'alert hidden';
      form.insertAdjacentElement('afterend', box);
    }

    return box;
  }

  function showMessage(box, message, type) {
    box.classList.remove('hidden', 'alert-success', 'alert-error');
    box.classList.add(type === 'success' ? 'alert-success' : 'alert-error');
    box.textContent = message;
  }

  function showResetLink(box, resetUrl) {
    const absoluteUrl = new URL(resetUrl, window.location.origin).toString();

    const wrapper = document.createElement('div');
    wrapper.className = 'reset-link-box';

    const text = document.createElement('p');
    text.textContent = 'Ambiente de teste: foi gerado um link de redefinição. Em produção, esse link seria enviado por e-mail.';

    const link = document.createElement('a');
    link.href = absoluteUrl;
    link.textContent = 'Abrir tela de redefinição de senha';
    link.className = 'btn btn-primary';

    wrapper.appendChild(text);
    wrapper.appendChild(link);
    box.appendChild(wrapper);
  }

  document.addEventListener('DOMContentLoaded', function () {
    const form = findForm();

    if (!form) {
      console.warn('[recuperar-senha] Formulário não encontrado.');
      return;
    }

    const emailInput = findEmailInput(form);
    const messageBox = getMessageBox(form);

    if (!emailInput) {
      showMessage(messageBox, 'Campo de e-mail não encontrado na página.', 'error');
      console.warn('[recuperar-senha] Campo de e-mail não encontrado.');
      return;
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      const email = String(emailInput.value || '').trim();

      if (!email) {
        showMessage(messageBox, 'Informe o e-mail cadastrado.', 'error');
        emailInput.focus();
        return;
      }

      const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submitButton) submitButton.disabled = true;

      showMessage(messageBox, 'Solicitando recuperação de senha...', 'success');

      try {
        const response = await fetch('/api/auth/password/forgot', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ email })
        });

        const result = await response.json().catch(function () {
          return { success: false, message: 'Resposta inválida do servidor.' };
        });

        showMessage(
          messageBox,
          result.message || (response.ok ? 'Processo de recuperação iniciado.' : 'Não foi possível iniciar a recuperação.'),
          response.ok && result.success !== false ? 'success' : 'error'
        );

        if (response.ok && result && result.data && result.data.reset_url) {
          showResetLink(messageBox, result.data.reset_url);
        }
      } catch (error) {
        showMessage(messageBox, 'Falha de comunicação com o servidor.', 'error');
        console.error('[recuperar-senha]', error);
      } finally {
        if (submitButton) submitButton.disabled = false;
      }
    });
  });
})();
