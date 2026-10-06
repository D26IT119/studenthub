/* ============================================================
 * faq-view.js  —  FAQ page module (Practical 6)
 * Responsibility: fetch faqs.json, apply search + sort,
 *                 render paginated accordion items.
 * Imports shared utilities from data.js (modular design).
 * ============================================================ */
import { fetchJson, paginate, pageControls } from "./data.js";

// ── DOM references ────────────────────────────────────────────
const listEl   = document.querySelector("#faq-data-list");
const searchEl = document.querySelector("#faq-search");
const sortEl   = document.querySelector("#faq-sort");
const statusEl = document.querySelector("#faq-status");
const paginEl  = document.querySelector("#faq-pagination");

// ── Module state ──────────────────────────────────────────────
let allFaqs = [];
let page    = 1;

// ── wireAccordion() ──────────────────────────────────────────
// Attaches click handlers to every rendered accordion button.
// Called after each render() because innerHTML replaces the DOM.
function wireAccordion() {
    listEl.querySelectorAll(".faq-question").forEach(btn => {
        btn.addEventListener("click", () => {
            const item = btn.closest(".faq-item");
            // Collapse all siblings (accordion pattern)
            listEl.querySelectorAll(".faq-item").forEach(fi => {
                fi.classList.remove("is-open");
                fi.querySelector(".faq-question").setAttribute("aria-expanded", "false");
                fi.querySelector(".faq-question span").textContent = "+";
            });
            // Toggle the clicked item
            item.classList.add("is-open");
            btn.setAttribute("aria-expanded", "true");
            btn.querySelector("span").textContent = "−";
        });
    });
}

// ── renderFaqItem(faq) ───────────────────────────────────────
// Pure helper — one FAQ object → HTML string.
function renderFaqItem(faq) {
    return `
    <article class="faq-item" data-id="${faq.id}">
      <button class="faq-question" type="button" aria-expanded="false">
        <span class="faq-num">Q${faq.id}</span>
        ${faq.question}
        <span aria-hidden="true">+</span>
      </button>
      <div class="faq-answer"><div><p>${faq.answer}</p></div></div>
    </article>`;
}

// ── render() ──────────────────────────────────────────────────
function render() {
    const query = searchEl.value.trim().toLowerCase();

    // 1. Filter by search query across question + answer fields
    let filtered = allFaqs.filter(faq =>
        `${faq.question} ${faq.answer}`.toLowerCase().includes(query)
    );

    // 2. Sort: Question A→Z or Z→A
    if (sortEl.value === "az") {
        filtered.sort((a, b) => a.question.localeCompare(b.question));
    } else if (sortEl.value === "za") {
        filtered.sort((a, b) => b.question.localeCompare(a.question));
    }
    // default "default" keeps original JSON order (id ascending)

    // 3. Paginate
    const result = paginate(filtered, page);
    page = result.page;

    // 4. Status bar
    statusEl.textContent =
        `${filtered.length} question${filtered.length === 1 ? "" : "s"} found`;

    // 5. Render items
    listEl.innerHTML = result.rows.length
        ? result.rows.map(renderFaqItem).join("")
        : `<div class="card"><p>No matching questions found.</p></div>`;

    // 6. Wire accordion behaviour on fresh DOM nodes
    wireAccordion();

    // 7. Pagination controls
    pageControls(paginEl, page, result.totalPages, nextPage => {
        page = nextPage;
        render();
    });
}

// ── Initialise ────────────────────────────────────────────────
fetchJson("../Data/faqs.json")
    .then(data => { allFaqs = data; render(); })
    .catch(err => {
        statusEl.textContent = "FAQs could not be loaded.";
        listEl.innerHTML = `<div class="card error-box"><p>${err.message}</p></div>`;
    });

[searchEl, sortEl].forEach(ctrl =>
    ctrl.addEventListener("input", () => { page = 1; render(); })
);
