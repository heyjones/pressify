(() => {
  const cfg = window.Pressify || {};
  const base = (cfg.restBase || "").replace(/\/$/, "");

  async function api(path, opts = {}) {
    const url = `${base}${path}`;
    const res = await fetch(url, {
      method: opts.method || "GET",
      headers: { "Content-Type": "application/json" },
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

  const addToCart = (variantId, quantity) =>
    api(`/cart/lines/add`, { method: "POST", body: { variantId, quantity } });

  const getCart = () => api(`/cart`, { method: "GET" });

  const updateLine = (lineId, quantity) =>
    api(`/cart/lines/update`, { method: "POST", body: { lineId, quantity } });

  const removeLines = (lineIds) =>
    api(`/cart/lines/remove`, { method: "POST", body: { lineIds } });

  function bindProducts() {
    document.querySelectorAll("[data-pressify-products]").forEach((root) => {
      root.addEventListener("click", async (e) => {
        const btn = e.target.closest("[data-pressify-add-to-cart]");
        if (!btn) return;
        const card = btn.closest(".pressify-product-card");
        const sel = card ? card.querySelector("[data-pressify-variant-select]") : null;
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
    const status = root.querySelector("[data-pressify-cart-status]");
    const linesEl = root.querySelector("[data-pressify-cart-lines]");
    const summaryEl = root.querySelector("[data-pressify-cart-summary]");
    const checkoutEl = root.querySelector("[data-pressify-checkout]");

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
        const p = m.product || {};
        const img = m.image && m.image.url
          ? `<img class="pressify-cart-line-img" src="${m.image.url}" alt="">`
          : "";
        const price = m.price && m.price.amount ? money(m.price.amount, m.price.currencyCode) : "";
        return `
          <div class="pressify-cart-line" data-line-id="${line.id}">
            ${img}
            <div class="pressify-cart-line-main">
              <div class="pressify-cart-line-title">${p.title || "Item"}</div>
              <div class="pressify-cart-line-variant">${m.title || ""}</div>
              <div class="pressify-cart-line-price">${price}</div>
              <div class="pressify-cart-line-controls">
                <input class="pressify-cart-qty" type="number" min="0" step="1" value="${line.quantity}">
                <button class="pressify-cart-remove" type="button">Remove</button>
              </div>
            </div>
          </div>
        `;
      })
      .join("");

    const total =
      cart.cost && cart.cost.totalAmount
        ? money(cart.cost.totalAmount.amount, cart.cost.totalAmount.currencyCode)
        : "";
    summaryEl.innerHTML = total ? `<div class="pressify-cart-total">Total: <strong>${total}</strong></div>` : "";

    if (checkoutEl) checkoutEl.setAttribute("href", cart.checkoutUrl || "#");
  }

  function bindCart() {
    document.querySelectorAll("[data-pressify-cart]").forEach(async (root) => {
      const status = root.querySelector("[data-pressify-cart-status]");
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
        const qtyInput = e.target.closest(".pressify-cart-qty");
        if (!qtyInput) return;
        const line = qtyInput.closest(".pressify-cart-line");
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
        const rm = e.target.closest(".pressify-cart-remove");
        if (!rm) return;
        const line = rm.closest(".pressify-cart-line");
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

