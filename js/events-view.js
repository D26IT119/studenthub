/* Practical 13: StudentHub event list and asynchronous JSON CRUD. */
import { paginate, pageControls } from "./data.js";

const API_URL = "../php/events_api.php";
const list = document.querySelector("#event-list");
const statusEl = document.querySelector("#event-status");
const feedbackEl = document.querySelector("#event-feedback");
const searchEl = document.querySelector("#event-search");
const categoryFilter = document.querySelector("#event-category");
const sortEl = document.querySelector("#event-sort");
const paginationEl = document.querySelector("#event-pagination");
const formPanel = document.querySelector("#event-form-panel");
const auditPanel = document.querySelector("#event-audit-panel");
const auditRows = document.querySelector("#event-audit-rows");
const signInEl = document.querySelector("#event-sign-in");
const form = document.querySelector("#event-form");
const formHeading = document.querySelector("#event-form-heading");
const saveButton = document.querySelector("#event-save");
const cancelButton = document.querySelector("#event-cancel-edit");

let allEvents = [];
let page = 1;
let canManage = false;
let csrfToken = "";

function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, character => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[character]);
}

function setFeedback(message, kind = "") {
    feedbackEl.textContent = message;
    feedbackEl.dataset.kind = kind;
    feedbackEl.setAttribute("role", kind === "error" ? "alert" : "status");
}

async function readResponse(response) {
    let result;
    try {
        result = await response.json();
    } catch {
        throw new Error(`The server returned an unreadable response (HTTP ${response.status}).`);
    }
    if (!response.ok || result.success !== true) {
        throw new Error(result.message || `Request failed (HTTP ${response.status}).`);
    }
    return result;
}

async function loadEvents({ quiet = false } = {}) {
    if (!quiet) {
        statusEl.textContent = "Loading events…";
        statusEl.setAttribute("aria-busy", "true");
        list.setAttribute("aria-busy", "true");
    }
    try {
        const response = await fetch(API_URL, {
            method: "GET",
            headers: { Accept: "application/json" },
            credentials: "same-origin",
            cache: "no-store"
        });
        const result = await readResponse(response);
        allEvents = result.data;
        canManage = result.permissions?.canManage === true;
        csrfToken = result.csrfToken || "";
        formPanel.hidden = !canManage;
        auditPanel.hidden = !canManage;
        signInEl.hidden = canManage;
        populateCategories();
        renderAuditLog(result.auditLog || []);
        render();
        if (!quiet && allEvents.length === 0) setFeedback("No events yet. Administrators can add the first one above.");
    } catch (error) {
        statusEl.textContent = "Events could not be loaded.";
        list.innerHTML = `<div class="card error-box"><p>${escapeHtml(error.message)}</p></div>`;
        paginationEl.replaceChildren();
        if (!quiet) setFeedback(error.message, "error");
    } finally {
        statusEl.removeAttribute("aria-busy");
        list.removeAttribute("aria-busy");
    }
}

function renderAuditLog(entries) {
    if (!entries.length) {
        auditRows.innerHTML = '<tr><td colspan="4" class="p13-audit-empty">No event changes recorded yet.</td></tr>';
        return;
    }
    auditRows.innerHTML = entries.map(entry => {
        const eventKey = entry.event_id ? `#${escapeHtml(entry.event_id)} · ` : "";
        const occurredAt = new Date(String(entry.occurred_at).replace(" ", "T"));
        const time = Number.isNaN(occurredAt.getTime()) ? entry.occurred_at : occurredAt.toLocaleString();
        return `<tr>
          <td>${eventKey}${escapeHtml(entry.event_title)}</td>
          <td>${escapeHtml(entry.action)}</td>
          <td>${escapeHtml(entry.admin_name)}</td>
          <td>${escapeHtml(time)}</td>
        </tr>`;
    }).join("");
}

function populateCategories() {
    const selected = categoryFilter.value;
    const categories = [...new Set(allEvents.map(event => event.category))].sort((a, b) => a.localeCompare(b));
    categoryFilter.innerHTML = '<option value="">All categories</option>' +
        categories.map(category => `<option value="${escapeHtml(category)}">${escapeHtml(category)}</option>`).join("");
    categoryFilter.value = categories.includes(selected) ? selected : "";
}

function renderEventCard(event) {
    const date = new Date(`${event.date}T00:00:00`);
    const month = date.toLocaleDateString("en-IN", { month: "short" }).toUpperCase();
    const day = date.getDate();
    const management = canManage ? `
        <div class="p13-event-actions" aria-label="Manage ${escapeHtml(event.title)}">
          <button type="button" data-edit-event="${event.id}">Edit</button>
          <button type="button" class="p13-delete" data-delete-event="${event.id}">Delete</button>
        </div>` : "";
    const state = event.status === "closed" ? "Closed" : "Open";
    return `
    <article class="card event" data-id="${event.id}">
      <div class="event-date">${escapeHtml(month)}<span>${day}</span></div>
      <div class="event-body">
        <h2>${escapeHtml(event.title)}</h2>
        <p>${escapeHtml(event.time)} &nbsp;·&nbsp; ${escapeHtml(event.venue)}</p>
        <p class="data-tag">${escapeHtml(event.category)}</p>
        <p>${escapeHtml(new Date(`${event.date}T00:00:00`).toLocaleDateString("en-IN", { day: "numeric", month: "short", year: "numeric" }))} · ${state} · ${event.capacity} seats</p>
        ${event.description ? `<p>${escapeHtml(event.description)}</p>` : ""}
        ${management}
      </div>
    </article>`;
}

function render() {
    const query = searchEl.value.trim().toLowerCase();
    let filtered = allEvents.filter(event =>
        `${event.title} ${event.venue} ${event.category} ${event.description}`.toLowerCase().includes(query)
    );
    if (categoryFilter.value) filtered = filtered.filter(event => event.category === categoryFilter.value);
    filtered.sort((a, b) => sortEl.value === "title"
        ? a.title.localeCompare(b.title)
        : new Date(`${a.date}T${a.time}`) - new Date(`${b.date}T${b.time}`));

    const result = paginate(filtered, page);
    page = result.page;
    statusEl.textContent = `${filtered.length} event${filtered.length === 1 ? "" : "s"} found`;
    list.innerHTML = result.rows.length
        ? result.rows.map(renderEventCard).join("")
        : '<div class="card"><p>No matching events found.</p></div>';
    pageControls(paginationEl, page, result.totalPages, nextPage => {
        page = nextPage;
        render();
    });

    list.querySelectorAll("[data-edit-event]").forEach(button =>
        button.addEventListener("click", () => beginEdit(Number(button.dataset.editEvent))));
    list.querySelectorAll("[data-delete-event]").forEach(button =>
        button.addEventListener("click", () => deleteEvent(Number(button.dataset.deleteEvent))));
}

function resetForm() {
    form.reset();
    document.querySelector("#event-id").value = "";
    document.querySelector("#event-capacity").value = "100";
    formHeading.textContent = "Add an event";
    saveButton.textContent = "Add event";
    cancelButton.hidden = true;
}

function beginEdit(id) {
    const event = allEvents.find(item => item.id === id);
    if (!event) return;
    document.querySelector("#event-id").value = event.id;
    document.querySelector("#event-title").value = event.title;
    document.querySelector("#event-date").value = event.date;
    document.querySelector("#event-time").value = event.time;
    document.querySelector("#event-venue").value = event.venue;
    document.querySelector("#event-category-input").value = event.category;
    document.querySelector("#event-capacity").value = event.capacity;
    document.querySelector("#event-description").value = event.description;
    document.querySelector("#event-record-status").value = event.status;
    formHeading.textContent = "Edit event";
    saveButton.textContent = "Save changes";
    cancelButton.hidden = false;
    formPanel.scrollIntoView({ behavior: "smooth", block: "start" });
    document.querySelector("#event-title").focus({ preventScroll: true });
    setFeedback(`Editing ${event.title}.`);
}

async function sendChange(payload) {
    const response = await fetch(API_URL, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-CSRF-Token": csrfToken
        },
        credentials: "same-origin",
        body: JSON.stringify(payload)
    });
    return readResponse(response);
}

async function deleteEvent(id) {
    const event = allEvents.find(item => item.id === id);
    if (!event || !window.confirm(`Delete “${event.title}”? Registrations for this event will also be deleted.`)) return;
    setFeedback("Deleting event…");
    try {
        const result = await sendChange({ action: "delete", id });
        await loadEvents({ quiet: true });
        setFeedback(result.message, "success");
    } catch (error) {
        setFeedback(error.message, "error");
    }
}

form.addEventListener("submit", async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    const id = document.querySelector("#event-id").value;
    const payload = {
        action: id ? "update" : "add",
        ...(id ? { id: Number(id) } : {}),
        title: document.querySelector("#event-title").value.trim(),
        date: document.querySelector("#event-date").value,
        time: document.querySelector("#event-time").value,
        venue: document.querySelector("#event-venue").value.trim(),
        category: document.querySelector("#event-category-input").value.trim(),
        capacity: Number(document.querySelector("#event-capacity").value),
        description: document.querySelector("#event-description").value.trim(),
        status: document.querySelector("#event-record-status").value
    };

    saveButton.disabled = true;
    cancelButton.disabled = true;
    setFeedback(id ? "Saving event changes…" : "Adding event…");
    try {
        const result = await sendChange(payload);
        resetForm();
        page = 1;
        await loadEvents({ quiet: true });
        setFeedback(result.message, "success");
    } catch (error) {
        setFeedback(error.message, "error");
    } finally {
        saveButton.disabled = false;
        cancelButton.disabled = false;
    }
});

cancelButton.addEventListener("click", () => {
    resetForm();
    setFeedback("Edit cancelled.");
});

[searchEl, categoryFilter, sortEl].forEach(control => control.addEventListener("input", () => {
    page = 1;
    render();
}));

loadEvents();
