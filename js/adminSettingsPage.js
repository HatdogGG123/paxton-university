(function ($) {
  "use strict";

  const endpoint = "../../controller/adminSettingsController.php";
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || "";
  const bodies = Array.from(document.querySelectorAll("table tbody"));

  const cell = (value, className) => {
    const el = document.createElement("td");
    el.className = className || "py-2.5 px-4";
    el.textContent = value ?? "";
    return el;
  };
  const row = (values) => {
    const el = document.createElement("tr");
    values.forEach((value) => el.append(value));
    return el;
  };

  function actions(type, item) {
    const td = cell("", "py-2.5 px-4 text-right space-x-2");
    const active = Number(item.is_active) === 1;
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = active
      ? "btn-toggle-item text-red-600 hover:underline font-semibold text-xs"
      : "btn-toggle-item text-green-700 hover:underline font-semibold text-xs";
    btn.textContent = active ? "Disable" : "Enable";
    btn.dataset.type = type;
    btn.dataset.id = item.id;
    btn.dataset.item = item.name;
    btn.dataset.active = active ? "0" : "1";
    td.append(btn);
    return td;
  }

  function render(data) {
    const specs = [
      [
        data.departments,
        (x) =>
          row([
            cell(x.code, "py-2.5 px-4 font-semibold row-code"),
            cell(x.name, "py-2.5 px-4 row-name"),
            actions("department", x),
          ]),
      ],
      [
        data.programs,
        (x) =>
          row([
            cell(x.code, "py-2.5 px-4 font-semibold row-code"),
            cell(x.name, "py-2.5 px-4 row-name"),
            cell(x.department_name, "py-2.5 px-4 row-dept"),
            actions("program", x),
          ]),
      ],
      [
        data.subjects,
        (x) =>
          row([
            cell(x.code, "py-2.5 px-4 font-semibold row-code"),
            cell(x.name, "py-2.5 px-4 row-title"),
            cell(x.units, "py-2.5 px-4 text-center row-units"),
            actions("subject", x),
          ]),
      ],
      [
        data.terms,
        (x) => {
          const status = cell(
            Number(x.is_current) ? "Active Current" : "Archived",
            "py-2.5 px-4 text-center row-status",
          );
          status.dataset.current = Number(x.is_current) ? "true" : "false";
          const actionsCell = cell("", "py-2.5 px-4 text-right");
          if (!Number(x.is_current)) {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className =
              "btn-set-current-term text-amber-600 hover:underline font-semibold text-xs";
            btn.textContent = "Set as Current";
            btn.dataset.termId = x.id;
            actionsCell.append(btn);
          }
          return row([
            cell(
              x.academic_year + " " + x.semester,
              "py-2.5 px-4 font-semibold row-code",
            ),
            cell(
              "A.Y. " + x.academic_year + ", " + x.semester,
              "py-2.5 px-4 row-name",
            ),
            status,
            actionsCell,
          ]);
        },
      ],
      [
        data.documents,
        (x) =>
          row([
            cell(x.name, "py-2.5 px-4 font-semibold row-doc-name"),
            cell(
              "\u20b1" + Number(x.fee).toFixed(2),
              "py-2.5 px-4 font-display row-doc-fee",
            ),
            cell(
              Number(x.is_active) ? "Active" : "Inactive",
              "py-2.5 px-4 text-center row-doc-status",
            ),
            actions("document", x),
          ]),
      ],
      [
        data.clearance,
        (x) =>
          row([
            cell(x.name, "py-2.5 px-4 font-semibold row-clr-name"),
            cell(x.office, "py-2.5 px-4 row-clr-office"),
            cell(
              Number(x.is_active) ? "Active" : "Inactive",
              "py-2.5 px-4 text-center row-clr-status",
            ),
            actions("clearance", x),
          ]),
      ],
    ];

    specs.forEach(([items, makeRow], index) => {
      const body = bodies[index];
      if (!body) return;
      body.replaceChildren(...items.map(makeRow));
      if (!items.length) {
        const tr = document.createElement("tr");
        const td = cell(
          "No records to show",
          "py-8 px-4 text-center text-gray-500",
        );
        td.colSpan = index === 0 ? 3 : 4;
        tr.append(td);
        body.append(tr);
      }
    });

    const setOptions = (selector, items, label) => {
      const select = document.querySelector(selector);
      if (select)
        select.replaceChildren(
          new Option(label, ""),
          ...items
            .filter((x) => Number(x.is_active))
            .map((x) => new Option(x.name, x.id)),
        );
    };

    setOptions("#prog_dept_id", data.departments, "-- Select Department --");
    setOptions("#sub_program_id", data.programs, "-- Select Program --");
    setOptions(
      "#clearance_department_id",
      data.departments,
      "-- Select Department --",
    );

    const keys = {
      school_name: "school_name",
      max_units_per_enrollment: "max_units_per_enrollment",
      login_attempt_limit: "login_attempt_limit",
      lockout_minutes: "lockout_time_minutes",
      session_timeout_minutes: "session_timeout_minutes",
    };

    Object.entries(keys).forEach(([key, name]) => {
      const input = document.querySelector('[name="' + name + '"]');
      if (input && data.settings[key] !== undefined)
        input.value = data.settings[key];
    });

    const currentTerm = data.terms.find((term) => Number(term.is_current));

    if (currentTerm) {
      const open = document.querySelector('[name="enrollment_open_at"]'),
        close = document.querySelector('[name="enrollment_close_at"]');
      if (open)
        open.value = (currentTerm.enrollment_open_at || "")
          .replace(" ", "T")
          .slice(0, 16);
      if (close)
        close.value = (currentTerm.enrollment_close_at || "")
          .replace(" ", "T")
          .slice(0, 16);
    }
  }
  function reload() {
    return $.getJSON(endpoint, { action: "list" }).then((response) => {
      if (response?.status !== "success")
        throw new Error(
          response?.message || "System settings could not be loaded.",
        );
      render(response.data);
    });
  }
  function toastError(message) {
    if (typeof showToast === "function") showToast("error", message);
  }
  // Remove legacy success-only form listeners on this page.
  $(
    "#form_system_settings, #form_enrollment_window, #form_add_department, #form_add_program, #form_add_subject, #form_add_term, #form_add_document, #form_add_clearance",
  ).off("submit");
  $(
    "#form_system_settings, #form_enrollment_window, #form_add_department, #form_add_program, #form_add_subject, #form_add_term, #form_add_document, #form_add_clearance",
  ).on("submit", function (event) {
    event.preventDefault();
    const form = this;
    const action =
      form.id === "form_system_settings"
        ? "save_settings"
        : form.id === "form_enrollment_window"
          ? "save_enrollment_window"
          : form.id.replace("form_add_", "add_");
    const payload = $(form).serializeArray();
    payload.push(
      { name: "action", value: action },
      { name: "csrf_token", value: csrf },
    );
    const submit = $(form).find('[type="submit"]').prop("disabled", true);
    $.ajax({
      url: endpoint,
      method: "POST",
      data: $.param(payload),
      dataType: "json",
    })
      .done((response) => {
        if (response?.status !== "success") {
          toastError(response?.message || "The request could not be saved.");
          return;
        }
        if (form.id !== "form_system_settings") {
          $(form).closest(".fixed").addClass("hidden");
          form.reset();
        }
        reload()
          .then(() =>
            showToast("success", response.message || "Settings saved."),
          )
          .catch((error) => toastError(error.message));
      })
      .fail((xhr) =>
        toastError(
          xhr.responseJSON?.message ||
            "The request could not be saved. Please try again.",
        ),
      )
      .always(() => submit.prop("disabled", false));
  });
  $("#clearance_office")
    .off("change")
    .on("change", function () {
      const visible = this.value === "Department";
      $("#clearance_department_wrapper").toggleClass("hidden", !visible);
      $("#clearance_department_id").prop("required", visible);
    });
  $(document)
    .off("click", ".btn-toggle-item")
    .on("click", ".btn-toggle-item", function () {
      const button = this;
      const enable = button.dataset.active === "1";
      $("#disable_item_name").text('"' + (button.dataset.item || "") + '"');
      $("#modal_confirm_disable [data-toggle-prompt]").text(
        enable ? "Enable this record?" : "Disable this record?",
      );
      $("#btn_confirm_disable_action").text(
        enable ? "Yes, Enable" : "Yes, Disable",
      );
      $("#modal_confirm_disable").removeClass("hidden");
      $("#btn_confirm_disable_action")
        .off("click")
        .on("click", function () {
          const confirm = $(this).prop("disabled", true);
          $.ajax({
            url: endpoint,
            method: "POST",
            data: {
              action: "set_active",
              type: button.dataset.type,
              id: button.dataset.id,
              is_active: button.dataset.active,
              csrf_token: csrf,
            },
            dataType: "json",
          })
            .done((response) => {
              if (response?.status !== "success") {
                toastError(
                  response?.message ||
                    "The record status could not be updated.",
                );
                return;
              }
              $("#modal_confirm_disable").addClass("hidden");
              reload()
                .then(() =>
                  showToast(
                    "success",
                    response.message ||
                      (enable ? "Record enabled." : "Record disabled."),
                  ),
                )
                .catch((error) => toastError(error.message));
            })
            .fail((xhr) =>
              toastError(
                xhr.responseJSON?.message ||
                  "The record status could not be updated. Please try again.",
              ),
            )
            .always(() => confirm.prop("disabled", false));
        });
    });
  $(document)
    .off("click", ".btn-set-current-term")
    .on("click", ".btn-set-current-term", function () {
      $.ajax({
        url: endpoint,
        method: "POST",
        data: {
          action: "set_current_term",
          id: this.dataset.termId,
          csrf_token: csrf,
        },
        dataType: "json",
      })
        .done((response) => {
          if (response?.status !== "success") {
            toastError(response?.message || "The term could not be updated.");
            return;
          }
          reload()
            .then(() =>
              showToast("success", response.message || "Current term updated."),
            )
            .catch((error) => toastError(error.message));
        })
        .fail((xhr) =>
          toastError(
            xhr.responseJSON?.message ||
              "The term could not be updated. Please try again.",
          ),
        );
    });
  reload().catch((error) =>
    toastError(error.message || "System settings could not be loaded."),
  );
})(jQuery);
