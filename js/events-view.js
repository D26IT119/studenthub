/* ============================================================
 * events-view.js  —  Events page module (Practical 6)
 * Responsibility: fetch events.json, apply search/filter/sort,
 *                 render paginated cards, wire UI controls.
 * Imports shared utilities from data.js (modular design).
 * ============================================================ */
import { fetchJson, paginate, pageControls, renderTag } from "./data.js";

// ── DOM references ────────────────────────────────────────────
const list       = document.querySelector("#event-list");
const statusEl   = document.querySelector("#event-status");
const searchEl   = document.querySelector("#event-search");
const categoryEl = document.querySelector("#event-category");
const sortEl     = document.querySelector("#event-sort");
const paginEl    = document.querySelector("#event-pagination");

// ── Module state ──────────────────────────────────────────────
let allEvents = [];   // full dataset from JSON
let page      = 1;   // current page (reset on filter change)

// ── renderEventCard(event) ────────────────────────────────────
// Pure helper — converts one event object into an HTML string.
// Kept separate so it can be unit-tested independently.
function renderEventCard(event) {
    const d   = new Date(event.date);
    const mon = d.toLocaleDateString("en-IN", { month: "short" }).toUpperCase();
    const day = d.getDate();
    return `
    <article class="card event" data-id="${event.id}">
      <div class="event-date">${mon}<span>${day}</span></div>
      <div class="event-body">
        <h2>${event.title}</h2>
        <p>${event.time} &nbsp;&middot;&nbsp; ${event.venue}</p>
        ${renderTag(event.category)}
      </div>
      <a class="button" href="register.html">Join</a>
    </article>`;
}

// ── render() ──────────────────────────────────────────────────
// Called on every filter / sort / pagination change.
// Uses Array.prototype.filter, map, sort — key ES6 array methods.
function render() {
    const query = searchEl.value.trim().toLowerCase();

    // 1. Filter by search query (map fields → string → includes)
    let filtered = allEvents.filter(ev =>
        `${ev.title} ${ev.venue} ${ev.category}`.toLowerCase().includes(query)
    );

    // 2. Filter by selected category
    if (categoryEl.value) {
        filtered = filtered.filter(ev => ev.category === categoryEl.value);
    }

    // 3. Sort: date ascending or title alphabetically
    filtered.sort((a, b) =>
        sortEl.value === "title"
            ? a.title.localeCompare(b.title)
            : new Date(a.date) - new Date(b.date)
    );

    // 4. Paginate (shared utility — 5 records per page)
    const result = paginate(filtered, page);
    page = result.page; // clamp if filter reduced total pages

    // 5. Status bar
    statusEl.textContent =
        `${filtered.length} event${filtered.length === 1 ? "" : "s"} found`;

    // 6. Render cards via Array.map then join
    list.innerHTML = result.rows.length
        ? result.rows.map(renderEventCard).join("")
        : `<div class="card"><p>No matching events found.</p></div>`;

    // 7. Pagination controls (shared utility)
    pageControls(paginEl, page, result.totalPages, nextPage => {
        page = nextPage;
        render();
    });
}

// ── Initialise ────────────────────────────────────────────────
fetchJson("../Data/events.json")
    .then(data => {
        allEvents = data;
        // Build category <option> list dynamically using Set + map
        [...new Set(allEvents.map(ev => ev.category))].sort()
            .forEach(cat =>
                categoryEl.insertAdjacentHTML(
                    "beforeend",
                    `<option value="${cat}">${cat}</option>`
                )
            );
        render();
    })
    .catch(err => {
        statusEl.textContent = "Events could not be loaded.";
        list.innerHTML = `<div class="card error-box"><p>${err.message}</p></div>`;
    });

// Reset to page 1 whenever search / filter / sort controls change
[searchEl, categoryEl, sortEl].forEach(ctrl =>
    ctrl.addEventListener("input", () => { page = 1; render(); })
);
