/* ============================================================
 * students-view.js  —  Student Directory module (Practical 6)
 * Responsibility: fetch students.json, apply search/filter/sort,
 *                 render paginated profile cards.
 * Imports shared utilities from data.js (modular design).
 *
 * Modularity note:
 *   ┌─────────────────────────────────────┐
 *   │           data.js                   │  ← shared utilities
 *   │  fetchJson · paginate · pageControls│
 *   └────────────┬──────┬──────┬──────────┘
 *                │      │      │
 *        events- │  faq-│  students-
 *        view.js │  view│  view.js
 *                │  .js │
 *   Each view module handles ONE dataset.
 *   No globals leak between modules (ES6 module scope).
 * ============================================================ */
import { fetchJson, paginate, pageControls, renderTag } from "./data.js";

// ── DOM references ────────────────────────────────────────────
const gridEl    = document.querySelector("#student-grid");
const statusEl  = document.querySelector("#student-status");
const searchEl  = document.querySelector("#student-search");
const courseEl  = document.querySelector("#student-course");
const yearEl    = document.querySelector("#student-year");
const sortEl    = document.querySelector("#student-sort");
const paginEl   = document.querySelector("#student-pagination");

// ── Module state ──────────────────────────────────────────────
let allStudents = [];
let page        = 1;

// ── Avatar helper ─────────────────────────────────────────────
// Generates initials-based avatar colour from the student's name.
const AVATAR_COLORS = [
    "#6941c6", "#3877d7", "#0d9488", "#b45309",
    "#7c3aed", "#1d4ed8", "#059669", "#d97706"
];
function avatarColor(name) {
    const code = [...name].reduce((acc, ch) => acc + ch.charCodeAt(0), 0);
    return AVATAR_COLORS[code % AVATAR_COLORS.length];
}
function initials(name) {
    return name.split(" ").slice(0, 2).map(w => w[0]).join("").toUpperCase();
}

// ── Status badge helper ───────────────────────────────────────
const STATUS_CLASS = { Active: "tag-active", "On leave": "tag-leave", Inactive: "tag-inactive" };

// ── renderStudentCard(student) ────────────────────────────────
// Pure helper — one student object → HTML string.
function renderStudentCard(s) {
    const bg  = avatarColor(s.name);
    const ini = initials(s.name);
    const tagClass = STATUS_CLASS[s.status] || "";
    return `
    <article class="card student-card" data-id="${s.id}">
      <div class="student-avatar" style="background:${bg}" aria-hidden="true">${ini}</div>
      <div class="student-info">
        <h2 class="student-name">${s.name}</h2>
        <p class="student-meta">${s.course} &nbsp;&middot;&nbsp; Year ${s.year}</p>
        <p class="student-email">${s.email}</p>
        ${renderTag(s.status, tagClass)}
      </div>
    </article>`;
}

// ── render() ──────────────────────────────────────────────────
function render() {
    const query = searchEl.value.trim().toLowerCase();

    // 1. Filter by search: name, email, course
    let filtered = allStudents.filter(s =>
        `${s.name} ${s.email} ${s.course}`.toLowerCase().includes(query)
    );

    // 2. Filter by selected course
    if (courseEl.value) {
        filtered = filtered.filter(s => s.course === courseEl.value);
    }

    // 3. Filter by year
    if (yearEl.value) {
        filtered = filtered.filter(s => String(s.year) === yearEl.value);
    }

    // 4. Sort
    switch (sortEl.value) {
        case "name-az":  filtered.sort((a, b) => a.name.localeCompare(b.name)); break;
        case "name-za":  filtered.sort((a, b) => b.name.localeCompare(a.name)); break;
        case "year-asc": filtered.sort((a, b) => a.year - b.year);              break;
        case "year-desc":filtered.sort((a, b) => b.year - a.year);              break;
        default: /* id ascending — original JSON order */                        break;
    }

    // 5. Paginate
    const result = paginate(filtered, page);
    page = result.page;

    // 6. Status bar
    statusEl.textContent =
        `${filtered.length} student${filtered.length === 1 ? "" : "s"} found`;

    // 7. Render cards
    gridEl.innerHTML = result.rows.length
        ? result.rows.map(renderStudentCard).join("")
        : `<div class="card"><p>No matching students found.</p></div>`;

    // 8. Pagination controls
    pageControls(paginEl, page, result.totalPages, nextPage => {
        page = nextPage;
        render();
    });
}

// ── Initialise ────────────────────────────────────────────────
fetchJson("../Data/students.json")
    .then(data => {
        allStudents = data;

        // Populate course filter dynamically via Set + map
        [...new Set(allStudents.map(s => s.course))].sort()
            .forEach(c =>
                courseEl.insertAdjacentHTML("beforeend",
                    `<option value="${c}">${c}</option>`)
            );

        // Populate year filter dynamically
        [...new Set(allStudents.map(s => s.year))].sort((a, b) => a - b)
            .forEach(y =>
                yearEl.insertAdjacentHTML("beforeend",
                    `<option value="${y}">Year ${y}</option>`)
            );

        render();
    })
    .catch(err => {
        statusEl.textContent = "Student data could not be loaded.";
        gridEl.innerHTML = `<div class="card error-box"><p>${err.message}</p></div>`;
    });

// Reset to page 1 on any control change
[searchEl, courseEl, yearEl, sortEl].forEach(ctrl =>
    ctrl.addEventListener("input", () => { page = 1; render(); })
);

