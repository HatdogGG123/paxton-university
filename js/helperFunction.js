/**
 * Displays a responsive, auto-dismissing toast notification.
 * Mobile: Top Center | Desktop: Top Right
 *
 * @param {string} type - 'success', 'error', 'warning', or 'info'
 * @param {string} message - The text message to display
 */
function showToast(type, message, duration = 3000) {
  // 1. Ensure container exists in DOM with responsive positioning classes
  let $container = $("#toast_container");
  if ($container.length === 0) {
    $("body").append(`
            <div id="toast_container" 
                 class="fixed top-4 z-50 flex flex-col space-y-3 w-full px-4 pointer-events-none 
                        left-1/2 -translate-x-1/2 items-center 
                        sm:left-auto sm:right-4 sm:translate-x-0 sm:items-end sm:max-w-sm">
            </div>
        `);
    $container = $("#toast_container");
  }

  let config = {
    bg: "bg-emerald-100 border border-emerald-500",
    icon: `<svg class="w-5 h-5 text- shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`,
  };

  switch (type.toLowerCase()) {
    case "error":
      config.bg = "bg-red-100 border border-red-500";
      config.icon = `<svg class="w-5 h-5 text- shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>`;
      break;

    case "warning":
      config.bg = "bg-amber-100 border border-amber-500";
      config.icon = `<svg class="w-5 h-5 text- shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>`;
      break;

    case "info":
      config.bg = "bg-blue-100 border border-blue-500";
      config.icon = `<svg class="w-5 h-5 text- shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
      break;

    case "success":
    default:
      config.bg = "bg-emerald-100 border border-emerald-500";
      config.icon = `<svg class="w-5 h-5 text- shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>`;
      break;
  }

  // 3. Construct Toast HTML Element
  const toastId = "toast_" + Date.now();
  const toastHtml = `
        <div id="${toastId}" 
             class="pointer-events-auto flex items-center w-full max-w-sm p-4 rounded-xl shadow-lg ${config.bg} text- 
                    transform transition-all duration-300 -translate-y-10 opacity-0 sm:translate-y-0 sm:translate-x-10">
            <div class="mr-3">
                ${config.icon}
            </div>
            <div class="text-sm font-medium pr-2 leading-tight flex-1">
                ${message}
            </div>
            <button onclick="dismissToast('#${toastId}')" class="ml-auto text-/80 hover:text- focus:outline-none p-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
    `;

  // 4. Append to container and animate in
  const $toast = $(toastHtml);
  $container.append($toast);

  setTimeout(() => {
    $toast
      .removeClass("-translate-y-10 sm:translate-x-10 opacity-0")
      .addClass("translate-y-0 sm:translate-x-0 opacity-100");
  }, 10);

  // 5. Auto-remove after the requested duration (3 seconds by default)
  setTimeout(
    () => {
      dismissToast(`#${toastId}`);
    },
    Math.max(3000, Number(duration) || 3000),
  );
}

/**
 * Helper function to handle smooth dismissal animation
 */
function dismissToast(toastSelector) {
  const $toast = $(toastSelector);
  if ($toast.length > 0) {
    $toast
      .removeClass("translate-y-0 sm:translate-x-0 opacity-100")
      .addClass("-translate-y-10 sm:translate-x-10 opacity-0");
    setTimeout(() => {
      $toast.remove();
    }, 300);
  }
}

/** Shared jQuery request helpers. Call from async functions with await. */
window.SSIS = window.SSIS || {};
window.SSIS.showFieldErrors = function (form, errors = {}) {
  if (!form) return;
  form.querySelectorAll("[data-field-error]").forEach(function (message) {
    message.textContent = "";
    message.classList.add("hidden");
  });
  Object.entries(errors || {}).forEach(function ([name, text]) {
    const message = Array.from(
      form.querySelectorAll("[data-field-error]"),
    ).find(function (item) {
      return item.dataset.fieldError === name;
    });
    const field = form.elements.namedItem(name);
    if (!message) return;
    message.textContent = text;
    message.classList.remove("hidden");
    if (field && field.setAttribute) field.setAttribute("aria-invalid", "true");
  });
};

window.SSIS.clearFieldError = function (form, name) {
  if (!form) return;
  const message = Array.from(form.querySelectorAll("[data-field-error]")).find(
    function (item) {
      return item.dataset.fieldError === name;
    },
  );
  const field = form.elements.namedItem(name);
  if (message) {
    message.textContent = "";
    message.classList.add("hidden");
  }
  if (field && field.removeAttribute) field.removeAttribute("aria-invalid");
};

window.SSIS.ajax = {
  get: async function (url, data = {}, dataType = "json") {
    return $.get(url, data, null, dataType);
  },
  post: async function (url, data = {}, dataType = "json") {
    return $.post(url, data, null, dataType);
  },
  errorMessage: function (xhr) {
    return (
      xhr.responseJSON?.message ||
      xhr.responseJSON?.error ||
      "The request could not be completed. Please try again."
    );
  },
  updateAfterSuccess: function (form, response = {}, options = {}) {
    const redirect = response.data?.redirect || response.redirect;
    if (redirect) {
      const destination = new URL(redirect, window.location.href);
      if (
        destination.origin !== window.location.origin ||
        destination.pathname !== window.location.pathname
      ) {
        window.location.assign(destination.href);
        return;
      }
    }

    if (options.showToast !== false)
      showToast("success", response.message || "Saved successfully.");
    if (!form) return;

    const values = new FormData(form);
    const action = String(
      values.get("action") ||
        values.get("notification_action") ||
        values.get("request_action") ||
        values.get("communication_action") ||
        "",
    );
    const statusMessage = document.createElement("p");
    statusMessage.dataset.ajaxSuccess = "";
    statusMessage.className = "mt-2 text-xs font-semibold text-emerald-700";
    statusMessage.setAttribute("role", "status");
    statusMessage.textContent = response.message || "Saved successfully.";

    const existingMessage = form.parentElement?.querySelector(
      ":scope > [data-ajax-success]",
    );
    if (existingMessage) existingMessage.remove();

    if (action === "read") {
      const item = form.closest(".notification-item");
      if (item) {
        item.classList.remove(
          "unread",
          "bg-brand-accent/20",
          "bg-emerald-50/60",
          "border-l-4",
          "border-l-brand-primary",
          "border-l-emerald-600",
        );
        item.classList.add(
          "read",
          "bg-white",
          "border",
          "border-gray-200",
          "opacity-90",
        );
        item.querySelector(".bg-red-600")?.remove();
        form.replaceWith(
          Object.assign(document.createElement("span"), {
            className:
              "text-[10px] font-semibold text-gray-400 uppercase tracking-wider shrink-0",
            textContent: "Read",
          }),
        );
        const countLabel = document.querySelector(
          "#container_notifications_list > div > p",
        );
        if (countLabel) {
          const count = Math.max(
            0,
            (Number(countLabel.textContent.match(/\d+/)?.[0]) || 0) - 1,
          );
          countLabel.textContent =
            count + " unread notification" + (count === 1 ? "" : "s");
        }
        const unreadTotal = document.querySelector("#badge_unread_total");
        if (unreadTotal) {
          const count = Math.max(
            0,
            (Number(unreadTotal.textContent.match(/\d+/)?.[0]) || 0) - 1,
          );
          unreadTotal.textContent = count + " Unread";
          if (!count) unreadTotal.classList.add("bg-gray-100", "text-gray-600");
        }
        const navCount = document.querySelector("#nav_unread_count");
        if (navCount) {
          const count = Math.max(
            0,
            (Number(navCount.textContent.match(/\d+/)?.[0]) || 0) - 1,
          );
          if (!count) navCount.remove();
          else navCount.textContent = String(count);
        }
        return;
      }
    }

    if (action === "read_all") {
      document.querySelectorAll(".notification-item").forEach(function (item) {
        item.classList.remove(
          "unread",
          "bg-brand-accent/20",
          "bg-emerald-50/60",
          "border-l-4",
          "border-l-brand-primary",
          "border-l-emerald-600",
        );
        item.classList.add(
          "read",
          "bg-white",
          "border",
          "border-gray-200",
          "opacity-90",
        );
        item.querySelector(".bg-red-600")?.remove();
        item.querySelector("form")?.replaceWith(
          Object.assign(document.createElement("span"), {
            className:
              "text-[10px] font-semibold text-gray-400 uppercase tracking-wider shrink-0",
            textContent: "Read",
          }),
        );
      });
      const unreadTotal = document.querySelector("#badge_unread_total");
      if (unreadTotal) {
        unreadTotal.textContent = "0 Unread";
        unreadTotal.classList.add("bg-gray-100", "text-gray-600");
      }
      const countLabel = document.querySelector(
        "#container_notifications_list > div > p",
      );
      if (countLabel) countLabel.textContent = "0 unread notifications";
      document.querySelector("#nav_unread_count")?.remove();
      form.remove();
      return;
    }

    if (action === "submit_face_to_face") {
      const documentCard = form.closest("[data-admission-document]");
      const status = documentCard?.querySelector("[data-document-status]");
      if (status) status.textContent = "Status: Pending · Face to Face";
      if (documentCard && documentCard.dataset.documentSubmitted !== "1") {
        documentCard.dataset.documentSubmitted = "1";
        const count = documentCard
          .closest(".application-card")
          ?.querySelector("[data-submitted-count]");
        if (count) {
          const match = count.textContent.match(
            /^(\s*)(\d+)(\s+of\s+\d+\s+submitted\s*)$/,
          );
          if (match)
            count.textContent = match[1] + (Number(match[2]) + 1) + match[3];
        }
      }
      if (
        documentCard &&
        !documentCard.querySelector("[data-document-received]")
      ) {
        documentCard
          .querySelector('a[href*="admissionDocumentController.php"]')
          ?.remove();
        const receipt = document.createElement("span");
        receipt.dataset.documentReceived = "";
        receipt.className =
          "rounded bg-gray-100 px-3 py-2 text-xs text-gray-600";
        receipt.textContent = "Received in person";
        documentCard
          .querySelector(".flex.flex-wrap.items-start.justify-between")
          ?.append(receipt);
      }
      documentCard?.querySelector("[data-document-empty]")?.remove();
      documentCard?.querySelector("[data-document-decision]")?.remove();
      documentCard?.querySelector("[data-review-document-form]")?.remove();
      const template = documentCard?.querySelector(
        "[data-review-document-template]",
      );
      const documentId = Number(
        response.document_id || response.data?.document_id || 0,
      );
      if (template && documentId > 0) {
        const reviewForm = template.content.firstElementChild.cloneNode(true);
        reviewForm.elements.namedItem("document_id").value = String(documentId);
        documentCard
          .querySelector(".flex.flex-wrap.items-start.justify-between")
          ?.insertAdjacentElement("afterend", reviewForm);
      }
      form.remove();
      statusMessage.classList.add("block");
      documentCard?.append(statusMessage);
      return;
    }

    if (action === "submit_document") {
      const status = form.closest("article, section")?.querySelector("p");
      if (status && status.textContent.trim().startsWith("Status:"))
        status.textContent = "Status: Pending · Online";
      form.replaceWith(statusMessage);
      return;
    }

    if (action === "review_document") {
      const decision = values.get("document_status");
      const documentCard = form.closest("[data-admission-document]");
      const status = documentCard?.querySelector("[data-document-status]");
      const decisionLabel =
        decision === "Confirmed"
          ? "Document approved"
          : "Resubmission requested";
      if (status)
        status.textContent = "Status: " + String(decision || "Updated");
      documentCard?.querySelector("[data-document-decision]")?.remove();
      documentCard?.querySelector("[data-review-document-form]")?.remove();
      documentCard?.querySelector("[data-document-empty]")?.remove();
      if (decision === "Confirmed")
        documentCard?.querySelector(".bg-amber-50")?.remove();
      if (documentCard) {
        const result = document.createElement("p");
        result.dataset.documentDecision = "";
        result.className =
          "mt-3 text-xs font-semibold " +
          (decision === "Confirmed" ? "text-emerald-700" : "text-amber-700");
        result.textContent = decisionLabel;
        documentCard.append(result);
      }
      return;
    }

    if (action === "review_application") {
      const card = form.closest(".application-card");
      const badge = card?.querySelector("header span");
      const decision = String(values.get("status") || "");
      if (["Approved", "Enrolled", "Completed"].includes(decision)) {
        card?.remove();
        const queueEmpty = document.querySelector("#application_queue_empty");
        const hasApplications = Boolean(
          document.querySelector(".application-card"),
        );
        if (queueEmpty) queueEmpty.classList.toggle("hidden", hasApplications);
        if (hasApplications)
          document
            .querySelector("#application_search")
            ?.dispatchEvent(new Event("input", { bubbles: true }));
        else
          document
            .querySelector("#application_search_empty")
            ?.classList.add("hidden");
        return;
      }
      if (badge && decision) badge.textContent = decision;
      const select = form.querySelector('[name="status"]');
      if (select && decision) select.value = decision;
    }

    if (action === "delete_requirement") {
      const row = form.closest(".grid.gap-3.py-3");
      if (row) {
        row.remove();
        return;
      }
    }

    if (action === "cancel_request") {
      const row = form.closest("tr");
      if (row) {
        row.remove();
        return;
      }
      form.replaceWith(statusMessage);
      return;
    }

    form.insertAdjacentElement("afterend", statusMessage);
  },
  bindForms: function (selector = "form[data-ajax]") {
    $(document).on("submit", selector, async function (event) {
      event.preventDefault();
      const form = this;
      const $form = $(form);
      const requestUrl = form.getAttribute("action") || window.location.href;
      const requestMethod = (
        form.getAttribute("method") || "GET"
      ).toUpperCase();
      const $button = $form.find("[type=submit]").first();
      if ($button.prop("disabled")) return;
      const originalText = $button.text();
      $button.prop("disabled", true).attr("aria-busy", "true");
      if ($button.length)
        $button.text($button.data("loading-text") || "Please wait...");
      $form.attr("aria-busy", "true");
      try {
        let result;
        if (form.enctype === "multipart/form-data") {
          result = await $.ajax({
            url: requestUrl,
            method: requestMethod,
            data: new FormData(form),
            processData: false,
            contentType: false,
            dataType: "json",
          });
        } else if (requestMethod === "GET") {
          result = await window.SSIS.ajax.get(requestUrl, $form.serialize());
        } else {
          result = await window.SSIS.ajax.post(requestUrl, $form.serialize());
        }
        showToast("success", result.message || "Saved successfully.");
        $form.trigger("ajax:success", [result]);
        if (new FormData(form).get("action") !== "submit_application") {
          window.SSIS.ajax.updateAfterSuccess(form, result, {
            showToast: false,
          });
        } else if (result.redirect) {
          window.SSIS.ajax.updateAfterSuccess(form, result, {
            showToast: false,
          });
        }
      } catch (xhr) {
        window.SSIS.showFieldErrors(form, xhr.responseJSON?.field_errors || {});
        showToast("error", window.SSIS.ajax.errorMessage(xhr));
        $form.trigger("ajax:error", [xhr]);
      } finally {
        $button
          .prop("disabled", false)
          .removeAttr("aria-busy")
          .text(originalText);
        $form.removeAttr("aria-busy");
      }
    });
  },
};

$(function () {
  window.SSIS.ajax.bindForms();
});

$(document).on("input change", "form", function (event) {
  const field = event.target;
  if (field.name && field.getAttribute("aria-invalid") === "true") {
    window.SSIS.clearFieldError(this, field.name);
  }
});
