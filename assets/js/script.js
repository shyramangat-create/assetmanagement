document.addEventListener("DOMContentLoaded", () => {
  const sidebar = document.getElementById("sidebar");
  const backdrop = document.getElementById("sidebarBackdrop");
  const open = document.getElementById("openSidebar");
  const close = document.getElementById("closeSidebar");
  const toggle = (show) => {
    if (sidebar) sidebar.classList.toggle("open", show);
    if (backdrop) backdrop.classList.toggle("show", show);
  };
  open?.addEventListener("click", () => toggle(true));
  close?.addEventListener("click", () => toggle(false));
  backdrop?.addEventListener("click", () => toggle(false));
  document.querySelectorAll("[data-confirm]").forEach((el) =>
    el.addEventListener("click", (e) => {
      if (!window.confirm(el.getAttribute("data-confirm") || "Are you sure?"))
        e.preventDefault();
    }),
  );
  document
    .querySelectorAll("[data-auto-submit]")
    .forEach((el) => el.addEventListener("change", () => el.form?.submit()));
  document
    .querySelectorAll('[data-dependent="sub-category"]')
    .forEach((select) => {
      const category = document.querySelector('[name="category_id"]');
      const refresh = async () => {
        const id = category?.value || "";
        if (!id) {
          select.innerHTML = '<option value="">Select category first</option>';
          return;
        }
        try {
          const url = `${window.APP_BASE || "/asset_management/"}assets_pages/subcategories.php?category_id=${encodeURIComponent(id)}`;
          const response = await fetch(url, {
            headers: { "X-Requested-With": "XMLHttpRequest" },
          });
          if (!response.ok) return;
          const rows = await response.json();
          const old = select.value;
          select.innerHTML =
            '<option value="">Select sub-category</option>' +
            rows
              .map(
                (r) =>
                  `<option value="${Number(r.id)}">${String(r.name).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c])}</option>`,
              )
              .join("");
          if (rows.some((r) => String(r.id) === old)) select.value = old;
        } catch (e) {
          console.error(e);
        }
      };
      category?.addEventListener("change", refresh);
    });
  const purchaseDate = document.querySelector('[name="purchase_date"]');
  const warrantyMonths = document.querySelector('[name="warranty_months"]');
  const warrantyOutput = document.querySelector("[data-warranty-expiry]");
  const updateWarranty = () => {
    if (!purchaseDate?.value || !warrantyOutput) return;
    const d = new Date(`${purchaseDate.value}T12:00:00`);
    d.setMonth(d.getMonth() + Number(warrantyMonths?.value || 0));
    warrantyOutput.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
  };
  purchaseDate?.addEventListener("change", updateWarranty);
  warrantyMonths?.addEventListener("input", updateWarranty);
  document.querySelectorAll("[data-select-all]").forEach((master) =>
    master.addEventListener("change", () => {
      document.querySelectorAll(master.dataset.selectAll).forEach((child) => {
        child.checked = master.checked;
      });
    }),
  );
});
