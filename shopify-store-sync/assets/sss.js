(() => {
  const cfg = window.SSS || {};
  const base = (cfg.restBase || "").replace(/\/$/, "");

  async function api(path, opts = {}) {
    const url = `${base}${path}`;
    const res = await fetch(url, {
      method: opts.method || "GET",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "same-origin",
      body: opts.body ? JSON.stringify(opts.body) : undefined,
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = (json && (json.message || json.error)) || `Request failed (${res.status})`;
      throw new Error(msg);
    }
    return json;
  }

  function money(amount, currency) {
    if (!amount) return "";
    const n = Number(amount);
    if (Number.isFinite(n)) {
      try {
        return new Intl.NumberFormat(undefined, { style: "currency", currency: currency || "USD" }).format(n);
      } catch (_) {}
    }
    return `${amount} ${currency || ""}`.trim();
  }

  async function addToCart(variantId, quantity) {
    return api(`/cart/lines/add`, { method: "POST", body: { variantId, quantity } });
  }

  async function getCart() {
    return api(`/cart`, { method: "GET" });
  }

  async function updateLine(lineId, quantity) {
    return api(`/cart/lines/update`, { method: "POST", body: { lineId, quantity } });
  }

  async function removeLines(lineIds) {
    return api(`/cart/lines/remove`, { method: "POST", body: { lineIds } });
  }

  function bindProducts() {
    document.querySelectorAll("[data-sss-products]").forEach((root) => {
      root.addEventListener("click", async (e) => {
        const btn = e.target.closest("[data-sss-add-to-cart]");
        if (!btn) return;
        const card = btn.closest(".sss-product-card");
        const sel = card ? card.querySelector("[data-sss-variant-select]") : null;
        const variantId = sel ? sel.value : "";
        if (!variantId) return;

        btn.disabled = true;
        const prev = btn.textContent;
        btn.textContent = "Adding…";
        try {
          await addToCart(variantId, 1);
          btn.textContent = "Added";
          setTimeout(() => (btn.textContent = prev), 900);
        } catch (err) {
          btn.textContent = "Error";
          console.error(err);
          alert(err.message || "Failed to add to cart");
          btn.textContent = prev;
        } finally {
          btn.disabled = false;
        }
      });
    });
  }

  function renderCart(cart, root) {
    const status = root.querySelector("[data-sss-cart-status]");
    const linesEl = root.querySelector("[data-sss-cart-lines]");
    const summaryEl = root.querySelector("[data-sss-cart-summary]");
    const checkoutEl = root.querySelector("[data-sss-checkout]");

    if (!cart || !cart.id) {
      if (status) status.textContent = "Your cart is empty.";
      if (linesEl) linesEl.innerHTML = "";
      if (summaryEl) summaryEl.innerHTML = "";
      if (checkoutEl) checkoutEl.setAttribute("href", "#");
      return;
    }

    if (status) status.textContent = "";

    const lines = Array.isArray(cart.lines) ? cart.lines : [];
    linesEl.innerHTML = lines
      .map((line) => {
        const m = line.merchandise || {};
        const p = (m.product || {});
        const img = (m.image && m.image.url) ? `<img class="sss-cart-line-img" src="${m.image.url}" alt="">` : "";
        const price = (m.price && m.price.amount) ? money(m.price.amount, m.price.currencyCode) : "";
        return `
          <div class="sss-cart-line" data-line-id="${line.id}">
            ${img}
            <div class="sss-cart-line-main">
              <div class="sss-cart-line-title">${(p.title || "Item")}</div>
              <div class="sss-cart-line-variant">${(m.title || "")}</div>
              <div class="sss-cart-line-price">${price}</div>
              <div class="sss-cart-line-controls">
                <input class="sss-cart-qty" type="number" min="0" step="1" value="${line.quantity}">
                <button class="sss-cart-remove" type="button">Remove</button>
              </div>
            </div>
          </div>
        `;
      })
      .join("");

    const total = cart.cost && cart.cost.totalAmount ? money(cart.cost.totalAmount.amount, cart.cost.totalAmount.currencyCode) : "";
    summaryEl.innerHTML = total ? `<div class="sss-cart-total">Total: <strong>${total}</strong></div>` : "";

    if (checkoutEl) checkoutEl.setAttribute("href", cart.checkoutUrl || "#");
  }

  function bindCart() {
    document.querySelectorAll("[data-sss-cart]").forEach(async (root) => {
      const status = root.querySelector("[data-sss-cart-status]");
      if (status) status.textContent = "Loading cart…";

      async function refresh() {
        const { cart } = await getCart();
        renderCart(cart, root);
      }

      try {
        await refresh();
      } catch (err) {
        console.error(err);
        if (status) status.textContent = "Could not load cart.";
      }

      root.addEventListener("change", async (e) => {
        const qtyInput = e.target.closest(".sss-cart-qty");
        if (!qtyInput) return;
        const line = qtyInput.closest(".sss-cart-line");
        if (!line) return;
        const lineId = line.getAttribute("data-line-id");
        const qty = Number(qtyInput.value || 0);
        try {
          await updateLine(lineId, qty);
          await refresh();
        } catch (err) {
          console.error(err);
          alert(err.message || "Failed to update cart");
        }
      });

      root.addEventListener("click", async (e) => {
        const rm = e.target.closest(".sss-cart-remove");
        if (!rm) return;
        const line = rm.closest(".sss-cart-line");
        if (!line) return;
        const lineId = line.getAttribute("data-line-id");
        try {
          await removeLines([lineId]);
          await refresh();
        } catch (err) {
          console.error(err);
          alert(err.message || "Failed to remove item");
        }
      });
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    bindProducts();
    bindCart();
  });
})();

