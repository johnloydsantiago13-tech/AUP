function showForm(formName) {
  document.querySelectorAll(".form-box").forEach(function (form) {
    form.classList.toggle("active", form.id === formName + "Box");
  });
}

document.querySelectorAll("[data-form]").forEach(function (link) {
  link.addEventListener("click", function (event) {
    event.preventDefault();
    showForm(link.dataset.form);
  });
});

document.querySelectorAll("[data-toggle-link-editor]").forEach(function (button) {
  button.addEventListener("click", function () {
    var field = button.closest(".profile-field");
    var input = document.getElementById(button.getAttribute("aria-controls"));
    if (!field || !(input instanceof HTMLInputElement)) {
      return;
    }

    var value = field.querySelector("[data-link-value]");
    if (!input.hidden) {
      return;
    }

    input.hidden = false;
    button.hidden = true;
    button.setAttribute("aria-expanded", "true");
    if (value) {
      value.hidden = true;
    }
    input.focus();
  });
});

document.querySelectorAll("[data-account-menu]").forEach(function (menu) {
  var toggle = menu.querySelector("[data-menu-toggle]");
  var dropdown = menu.querySelector("[data-account-dropdown]");

  if (!toggle || !dropdown) {
    return;
  }

  function setMenuOpen(isOpen) {
    toggle.setAttribute("aria-expanded", String(isOpen));
    dropdown.hidden = !isOpen;
    menu.classList.toggle("is-open", isOpen);
  }

  toggle.addEventListener("click", function () {
    setMenuOpen(toggle.getAttribute("aria-expanded") !== "true");
  });

  document.addEventListener("click", function (event) {
    if (!menu.contains(event.target)) {
      setMenuOpen(false);
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && toggle.getAttribute("aria-expanded") === "true") {
      setMenuOpen(false);
      toggle.focus();
    }
  });

  dropdown.addEventListener("click", function (event) {
    if (event.target instanceof Element && event.target.closest("a")) {
      setMenuOpen(false);
    }

    if (
      event.target instanceof Element &&
      event.target.closest(
        "[data-open-developer-settings], [data-open-developer-profile], [data-open-client-profile]",
      )
    ) {
      setMenuOpen(false);
    }
  });

  menu.addEventListener("focusout", function (event) {
    if (!menu.contains(event.relatedTarget)) {
      setMenuOpen(false);
    }
  });
});

[
  {
    modal: document.querySelector("[data-client-profile-modal]"),
    trigger: document.querySelector("[data-open-client-profile]"),
    close: document.querySelector("[data-close-client-profile]"),
  },
  {
    modal: document.querySelector("[data-developer-profile-modal]"),
    trigger: document.querySelector("[data-open-developer-profile]"),
    close: document.querySelector("[data-close-developer-profile]"),
  },
  {
    modal: document.querySelector("[data-developer-settings-modal]"),
    trigger: document.querySelector("[data-open-developer-settings]"),
    close: document.querySelector("[data-close-developer-settings]"),
  },
].forEach(function (modalControl) {
  var modal = modalControl.modal;
  if (!(modal instanceof HTMLDialogElement)) {
    return;
  }

  if (modalControl.trigger instanceof HTMLElement) {
    modalControl.trigger.addEventListener("click", function () {
      modal.showModal();
    });
  }

  if (modalControl.close instanceof HTMLElement) {
    modalControl.close.addEventListener("click", function () {
      modal.close();
    });
  }

  modal.addEventListener("click", function (event) {
    if (event.target === modal) {
      modal.close();
    }
  });
});

var queryParams = new URLSearchParams(window.location.search);
var modalToOpen =
  queryParams.get("settings") === "1"
    ? document.querySelector("[data-developer-settings-modal]")
    : queryParams.get("profile") === "1" || queryParams.get("page") === "profile"
      ? document.querySelector("[data-developer-profile-modal], [data-client-profile-modal]")
      : null;

if (modalToOpen instanceof HTMLDialogElement) {
  modalToOpen.showModal();
  window.history.replaceState(null, "", window.location.pathname + window.location.hash);
}

document.querySelectorAll("[data-open-dialog]").forEach(function (trigger) {
  trigger.addEventListener("click", function () {
    var dialogId = trigger.getAttribute("data-open-dialog");
    var dialog = dialogId ? document.getElementById(dialogId) : null;
    if (dialog instanceof HTMLDialogElement) {
      dialog.showModal();
    }
  });
});

document.querySelectorAll("[data-action-dialog]").forEach(function (dialog) {
  if (!(dialog instanceof HTMLDialogElement)) {
    return;
  }

  dialog.querySelectorAll("[data-close-dialog]").forEach(function (button) {
    button.addEventListener("click", function () {
      dialog.close();
    });
  });

  dialog.addEventListener("click", function (event) {
    if (event.target === dialog) {
      dialog.close();
    }
  });

  if (dialog.hasAttribute("data-dialog-start-open")) {
    dialog.showModal();
  }
});
