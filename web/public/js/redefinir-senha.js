(function () {
  'use strict';

  function $(selector) {
    return document.querySelector(selector);
  }

  function showMessage(box, message, type) {
    box.classList.remove('hidden', 'alert-success', 'alert-error');
    box.classList.add(type === 'success' ? 'alert-success' : 'alert-error');
    box.textContent = message;
  }

  document.addEventListener('DOMContentLoaded', function () {
    const form = $('#reset-password-form') || $('form');
    if (!form) {
      console.warn('[redefinir-senha] Formulário não encontrado.');
      return;
    }

    const tokenInput = $('#reset-token')
      || form.querySelector('input[name="token"]')
      || form.querySelector('input[type="text"]');

    const passwordInput = $('#reset-password')
      || form.querySelector('input[name="password"]')
      || form.querySelector('input[type="password"]');

    const confirmInput = $('#reset-password-confirm')
      || form.querySelector('input[name="password_confirmation"]');

    let messageBox = $('#reset-password-message') || $('.alert');
    if (!messageBox) {
      messageBox = document.createElement('div');
      messageBox.id = 'reset-password-message';
      messageBox.className = 'alert hidden';
      form.insertAdjacentElement('afterend', messageBox);
    }

    const params = new URLSearchParams(window.location.search);
    const urlToken = params.get('token');

    if (tokenInput && urlToken && !tokenInput.value) {
      tokenInput.value = urlToken;
    }

    form.addEventListener('submit', async function (event) {
      event.preventDefault();

      const token = tokenInput ? String(tokenInput.value || '').trim() : '';
      const password = passwordInput ? String(passwordInput.value || '') : '';
      const confirmation = confirmInput ? String(confirmInput.value || '') : '';

      if (!token) {
        showMessage(messageBox, 'Token de redefinição não informado.', 'error');
        if (tokenInput) tokenInput.focus();
        return;
      }

      if (!password) {
        showMessage(messageBox, 'Informe a nova senha.', 'error');
        if (passwordInput) passwordInput.focus();
        return;
      }

      if (confirmInput && password !== confirmation) {
        showMessage(messageBox, 'A confirmação de senha não confere.', 'error');
        confirmInput.focus();
        return;
      }

      const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
      if (submitButton) submitButton.disabled = true;

      showMessage(messageBox, 'Redefinindo senha...', 'success');

      try {
        const response = await fetch('/api/auth/password/reset', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({ token, password })
        });

        const result = await response.json().catch(function () {
          return { success: false, message: 'Resposta inválida do servidor.' };
        });

        showMessage(
          messageBox,
          result.message || (response.ok ? 'Senha redefinida com sucesso.' : 'Não foi possível redefinir a senha.'),
          response.ok && result.success !== false ? 'success' : 'error'
        );

        if (response.ok && result.success !== false) {
          form.reset();
          if (tokenInput && urlToken) tokenInput.value = urlToken;
        }
      } catch (error) {
        showMessage(messageBox, 'Falha de comunicação com o servidor.', 'error');
        console.error('[redefinir-senha]', error);
      } finally {
        if (submitButton) submitButton.disabled = false;
      }
    });
  });
})();
